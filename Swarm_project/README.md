# 🚗 Plateforme Covoiturage – Docker Swarm / Traefik / MariaDB / Harbor

Déploiement d’une application PHP de covoiturage sur un cluster **Docker Swarm** orchestré via **Ansible**.

- 🐘 MariaDB hors Swarm (VM dédiée)
- 🌐 Traefik en reverse-proxy (HTTPS `websecure`)
- 📦 Harbor (registry privé HTTP sur `http://192.168.56.10`)
- 📊 Portainer pour l’administration du cluster

***

## 🧱 Architecture
- VMs Vagrant : `swarm-mgr1` (Traefik, Portainer), `swarm-node1`, `swarm-node2`, `dbSrv1` (MariaDB)
- Réseau overlay : `traefik-net`
- Services : Traefik, Portainer, app PHP (`app_covoit` 3 replicas), MariaDB, Harbor (hors Swarm)

***

## 📂 Arborescence (principale)
```txt
ansible/
  inventory.ini
  playbook.yml
  roles/
    common/
    database/
    swarm-manager/
    swarm-worker/
app-php/
  Dockerfile
  src/
docker/
  app/ docker-stack.yml (exemple)
  covoit/ docker-compose.yml (exemple)
  portainer/ (exemples)
  traefik/ docker-compose.yml (exemple)
sql/
  covoit-schema.sql
traefik-extra/ (routes statiques optionnelles)
Vagrantfile
```

***

## ⚙️ Prérequis
- Vagrant + VirtualBox
- Accès HTTP au registry Harbor depuis les nœuds Swarm
- `/etc/hosts` sur la machine cliente :
```txt
192.168.56.121  app.local
192.168.56.121  portainer.app.local
192.168.56.121  traefik.app.local
192.168.56.121  harbor.app.local
```

***

## 🚀 Mise en route
1) Lancer les VMs (hôte) :
```bash
cd Swarm_project
vagrant up
```
2) Provisionner (depuis `Swarm_project/ansible`) :
```bash
ansible-playbook -i inventory.ini playbook.yml
```
3) (Optionnel) Builder/pusher l’image app depuis `swarm-mgr1` :
```bash
cd /vagrant/app-php
docker build -t 192.168.56.10/my_app/app-php:1.2 .
docker push 192.168.56.10/my_app/app-php:1.2
```

***

## ✅ Points d’accès
- App : `https://app.local`
- Traefik dashboard : `https://traefik.app.local/dashboard/`
- Portainer : `https://portainer.app.local`
- Harbor (via Traefik) : `https://harbor.app.local` → backend `http://192.168.56.10`

## 🧪 Vérifications rapides
- App : `curl -v -k https://app.local`
- Traefik : `https://traefik.app.local/dashboard/`
- Portainer : `https://portainer.app.local`
- Harbor direct (depuis mgr) : `curl -v http://192.168.56.10/api/v2.0/systeminfo`

## 🛠️ Troubleshooting
- 502 sur Harbor : vérifier que Harbor écoute sur `192.168.56.10:80` (depuis `swarm-mgr1`: `curl -v http://192.168.56.10/api/v2.0/systeminfo`).
- 404 dashboard : le router Traefik doit cibler `api@internal` (déployé par Ansible). Ajouter `-k` si certificat autosigné.
- Images introuvables : vérifier que l’image existe dans Harbor (`192.168.56.10/my_app/app-php:1.2`) et que l’insecure registry est bien pris en compte (`/etc/docker/daemon.json`).
