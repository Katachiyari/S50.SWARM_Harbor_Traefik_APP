# 🚗 Plateforme Covoiturage – Docker Swarm / Traefik / MariaDB / Harbor

Déploiement d’une application PHP de covoiturage sur un cluster **Docker Swarm** orchestré via **Ansible**.

- 🐘 MariaDB hors Swarm (VM dédiée)
- 🌐 Traefik en reverse‑proxy (HTTPS `websecure`)
- 📦 Harbor (registry privé HTTP, backend `http://192.168.56.10`)
- 📊 Portainer pour l’administration du cluster

***

## 🧱 Architecture

- 🖥️ **Machines virtuelles (Vagrant)**
    - `swarm-mgr1` : manager Swarm, Traefik, Portainer
    - `swarm-node1`, `swarm-node2` : workers Swarm
    - `dbSrv1` : MariaDB standalone
- 🕸️ **Réseaux**
    - `traefik-net` : réseau overlay pour Traefik + services exposés
- 🧩 **Services**
    - `app_covoit` : app PHP/Apache (3 replicas) connectée à MariaDB
    - `traefik` : reverse‑proxy HTTPS (443) + dashboard via `api@internal`
    - `portainer` : interface Web de gestion Docker Swarm
    - `mariadb` : base de données sur `dbSrv1`
    - `harbor` : registry Docker (HTTP, insecure)

***

## 📂 Arborescence

```txt
ansible/
  inventory.ini
  playbook.yml
  roles/
    common/          # Docker + prérequis
    database/        # MariaDB sur dbSrv1
    swarm-manager/   # Swarm + Traefik + Portainer
    swarm-worker/    # Join Swarm
app-php/
  Dockerfile         # Image PHP/Apache
  src/               # Code de l'application
docker/
  app/
    docker-stack.yml # Stack Swarm finale app
  covoit/
    docker-compose.yml
  database/
    docker-compose.yml
  portainer/
    docker-compose.yml
    portainer-stack.yml
  traefik/
    docker-compose.yml       # stack Traefik (exemple manuel)
  templates/
    traefik-app.yml.j2
  tests/
    whoami-stack.yml
sql/
  covoit-schema.sql
traefik-extra/
  app.yml
  harbor.yml                 # routes statiques supplémentaires (optionnel)
Vagrantfile
docker-compose.yml   # Stack app (déploiement manuel)
```


***

## ⚙️ Prérequis

- Vagrant + VirtualBox
- Accès HTTP au registry Harbor depuis les nœuds Swarm
- Entrées `/etc/hosts` sur la machine cliente, par exemple :

```txt
192.168.56.121  app.local
192.168.56.121  portainer.app.local
192.168.56.121  traefik.app.local
192.168.56.121  harbor.app.local
```


***

## 🚀 Mise en route

### 1️⃣ Lancer les VMs

Sur la machine hôte :

```bash
cd Swarm_project
vagrant up
```

Se connecter au manager :

```bash
vagrant ssh swarm-mgr1
cd /vagrant
```

Le dossier projet côté hôte est monté dans `/vagrant` dans les VMs.[3]

***

### 2️⃣ Provisioning (Ansible)

Depuis le répertoire Ansible :

```bash
cd ansible
ansible-playbook -i inventory.ini playbook.yml
```

Le playbook installe Docker, configure l’insecure registry (Harbor), déploie MariaDB, init Swarm, déploie Traefik/Portainer et l’app.

***

### 3️⃣ Application PHP \& registry

#### 🐳 Build & push de l’image

Sur `swarm-mgr1` (Harbor doit être up) :

```bash
cd /vagrant/app-php
docker build -t 192.168.56.10/my_app/app-php:1.2 .
docker push 192.168.56.10/my_app/app-php:1.2
```

- Base : PHP 8.1 + Apache + extensions PDO MySQL
- Code : `app-php/src`


#### 🐘 MariaDB (dbSrv1)

Déployée par Ansible sur `dbSrv1` (conteneur `mariadb`), schéma importé depuis `sql/covoit-schema.sql`.

***

### 4️⃣ Traefik \& Portainer (déployés par Ansible)

- Traefik écoute en 80/443, router dashboard via `api@internal` : `https://traefik.app.local/dashboard/`
- Portainer exposé via Traefik : `https://portainer.app.local`

***

### 5️⃣ Application PHP (stack Swarm)

Stack (exemple déploiement manuel) : `docker-compose.yml` à la racine :

```yaml
version: '3.8'

services:
  covoit:
    image: harbor.local/my_app/app-php:1.2
    networks:
      - traefik-net
    deploy:
      replicas: 3
      labels:
        - "traefik.enable=true"
        - "traefik.http.routers.covoit.rule=Host(`app.local`)"
        - "traefik.http.routers.covoit.entrypoints=web"
        - "traefik.http.services.covoit.loadbalancer.server.port=80"
        - "traefik.docker.network=traefik-net"
    environment:
      - DB_HOST=192.168.56.112
      - DB_PORT=3306
      - DB_NAME=covoit
      - DB_USER=covoit_user
      - DB_PASSWORD=motdepasse

networks:
  traefik-net:
    external: true
```

Déploiement :

```bash
cd /vagrant
docker stack deploy -c docker-compose.yml app
docker service ps app_covoit
```


***

## ✅ Vérifications

### 🔍 Application

Depuis la machine cliente :

```bash
curl -v http://app.local
# ou
curl -v -H "Host: app.local" http://192.168.56.121
```

Réponse attendue : HTTP 200 et page HTML de l’application.

### 🔍 Depuis Traefik

```bash
docker exec -it $(docker ps -q -f name=traefik_traefik) sh
curl -I http://covoit:80
```

Réponse attendue : `HTTP/1.1 200 OK` (Apache dans le conteneur de l’app).

### 🔍 Portainer

- URL : `http://192.168.56.121:9000` (ou route Traefik dédiée)
- Vérifier :
    - état des nœuds Swarm
    - stacks `traefik`, `portainer`, `app`
    - distribution des tâches `app_covoit` sur les workers

***

Si tu veux, on peut rajouter à la fin une petite section “📌 Troubleshooting” avec 3–4 cas typiques (502 Traefik, problème Harbor, base inaccessible) mais le cœur du README est là, propre et lisible.

```
                             ┌───────────────────────────┐
                             │        Machine hôte       │
                             │───────────────────────────│
                             │  Vagrant + VirtualBox     │
                             │  VS Code / Git / Ansible  │
                             └────────────┬──────────────┘
                                          │
                        vagrant up        │   ansible-playbook
                                          ▼
        ┌───────────────────────────────────────────────────────────┐
        │                      Infra Vagrant                         │
        │                (réseau 192.168.56.0/24)                    │
        └───────────────────────────────────────────────────────────┘

┌──────────────────────┐        ┌──────────────────────┐       ┌──────────────────────┐
│      swarm-mgr1      │        │     swarm-node1      │       │     swarm-node2      │
│  (manager Swarm)     │        │   (worker Swarm)     │       │   (worker Swarm)     │
│──────────────────────│        │──────────────────────│       │──────────────────────│
│ - Docker Engine      │        │ - Docker Engine      │       │ - Docker Engine      │
│ - Swarm init         │        │ - Swarm worker       │       │ - Swarm worker       │
│ - Réseau overlay     │◄───────┴───────── traefik-net ┴───────►│ - Réseau overlay     │
│   traefik-net        │                                        │   traefik-net        │
│                      │                                        │                      │
│  Stacks Swarm :      │                                        │  Tâches app_covoit   │
│  ──────────────      │                                        │  (PHP/Apache)        │
│  • traefik_traefik   │                                        └──────────────────────┘
│    - Entrypoint :80  │
│    - Dashboard :8080 │
│    - Provider Swarm  │         ┌─────────────────────────────────────────────────┐
│  • portainer         │         │                 dbSrv1                          │
│  • app_covoit (3x)   │         │─────────────────────────────────────────────────│
│    - Image :         │         │ - Docker Engine                               │
│      harbor.local/   │         │ - Container MariaDB                           │
│      my_app/app-php  │         │   • Port 3306 exposé                          │
│    - Labels Traefik  │         │   • Schéma covoit (sql/covoit-schema.sql)     │
└──────────────────────┘         └─────────────────────────────────────────────────┘


                    ┌─────────────────────────────────────────┐
                    │                 Harbor                   │
                    │ (registry privé, HTTP insecure)         │
                    │  Ex : harbor.local/my_app/app-php:1.2   │
                    └─────────────────────────────────────────┘


Flux HTTP utilisateur :
───────────────────────────────────────────────────────────────────────────────
 navigateur / curl (app.local)              Swarm
        │                                    │
        │  HTTP :80                          │
        ▼                                    ▼
  192.168.56.121:80  ───►  Traefik (swarm-mgr1, stack traefik_traefik)
                              │
                              │ règle Host(`app.local`)
                              ▼
                         service app_covoit
                     (3 replicas sur mgr1/node1/node2)
                              │
                              │ PDO MySQL
                              ▼
                         MariaDB (dbSrv1:3306)

```
