#!/bin/bash

################################################################################
# Docker Swarm Deployment Script
# Orchestre : Vagrant UP → Ansible Playbook → Validation
################################################################################

set -e  # Exit on first error

# ==================== COULEURS & UTILITIES ====================
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'  # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[⚠]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# ==================== PRÉALABLES ====================
log_info "Vérification des prérequis..."

command -v vagrant &> /dev/null || { log_error "Vagrant not installed"; exit 1; }
command -v ansible-playbook &> /dev/null || { log_error "Ansible not installed"; exit 1; }
command -v docker &> /dev/null || { log_error "Docker not installed locally"; exit 1; }

log_success "Tous les prérequis sont présents"

# ==================== ÉTAPE 1 : VAGRANT UP ====================
log_info "Étape 1/4 : Création/démarrage des VMs Vagrant..."
echo ""
vagrant up

log_success "VMs démarrées"

# ⏳ ATTENDRE QUE LES VMs SOIENT VRAIMENT PRÊTES (SSH accessible)
log_info "Attente de stabilisation des VMs (SSH)..."
for i in {1..30}; do
    if ansible all -i ansible/inventory.ini -m ping &>/dev/null; then
        log_success "Toutes les VMs sont accessibles en SSH"
        break
    fi
    echo -n "."
    sleep 2
done
echo ""


# ==================== ÉTAPE 2 : ANSIBLE PLAYBOOK ====================
log_info "Étape 2/4 : Exécution du playbook Ansible..."
echo ""
ansible-playbook -i ansible/inventory.ini ansible/playbook.yml -v

log_success "Playbook Ansible terminé"
sleep 10

# ==================== ÉTAPE 3 : VALIDATION SWARM ====================
log_info "Étape 3/4 : Validation du cluster Swarm..."
echo ""

log_info "Affichage des nœuds du cluster..."
vagrant ssh swarm-mgr1 -c "docker node ls"
echo ""

log_info "Vérification du nombre de nœuds..."
NODES=$(vagrant ssh swarm-mgr1 -c "docker node ls --format '{{.Hostname}}'" | wc -l)
if [ "$NODES" -eq 3 ]; then
    log_success "Cluster Swarm validé : 3 nœuds (1 manager + 2 workers)"
else
    log_warning "Nombre de nœuds attendu : 3, obtenu : $NODES"
fi

# ==================== ÉTAPE 4 : VÉRIFICATION DES SERVICES ====================
log_info "Étape 4/4 : Vérification des services déployés..."
echo ""

vagrant ssh swarm-mgr1 -c "docker service ls"
echo ""

log_info "Vérification de Traefik..."
if vagrant ssh swarm-mgr1 -c "docker service ls | grep -q traefik_traefik"; then
    log_success "Service Traefik trouvé"
else
    log_warning "Service Traefik non trouvé (vérifier les stacks)"
fi

# ==================== RÉSUMÉ FINAL ====================
echo ""
echo "═══════════════════════════════════════════════════════════════"
log_success "🎉 Déploiement terminé avec succès!"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo -e "${BLUE}📍 Points d'accès :${NC}"
echo "   • App:             https://app.local/"
echo "   • Traefik:         https://traefik.app.local/"
echo "   • Portainer:       https://portainer.app.local/"
echo ""
echo -e "${BLUE}🔧 Commandes utiles :${NC}"
echo "   • SSH Manager:     vagrant ssh swarm-mgr1"
echo "   • SSH Worker 1:    vagrant ssh swarm-node1"
echo "   • SSH Worker 2:    vagrant ssh swarm-node2"
echo "   • SSH Database:    vagrant ssh dbSrv1"
echo ""
echo -e "${BLUE}💾 Database :${NC}"
echo "   • Host:            192.168.56.112:3306"
echo "   • User:            app_user"
echo "   • Password:        app_password"
echo "   • Database:        app_db"
echo ""
echo -e "${BLUE}🧹 Cleanup :${NC}"
echo "   • Détruire les VMs: ${YELLOW}./destroy.sh${NC} ou ${YELLOW}vagrant destroy -f${NC}"
echo ""
echo "═══════════════════════════════════════════════════════════════"
