# 🚗 Plateforme Covoiturage – Docker Swarm / Traefik / MariaDB

Déploiement d’une application PHP de covoiturage sur un cluster **Docker Swarm** avec :

- 🐘 **MariaDB** hors Swarm
- 🌐 **Traefik** comme reverse‑proxy
- 📦 **Harbor** comme registry privé
- 📊 **Portainer** pour l’administration du cluster

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
    - `traefik` : reverse‑proxy HTTP (80) + dashboard (8080)
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
    docker-compose.yml
  templates/
    traefik-app.yml.j2
  tests/
    whoami-stack.yml
sql/
  covoit-schema.sql
traefik-extra/
  app.yml
  harbor.yml
Vagrantfile
docker-compose.yml   # Stack app (déploiement manuel)
```


***

## ⚙️ Prérequis

- Vagrant + VirtualBox installés
- Accès au registry Harbor depuis les nœuds Swarm
- Entrées `/etc/hosts` sur la machine cliente, par exemple :

```txt
192.168.56.121  app.local
192.168.56.121  portainer.local
192.168.56.121  harbor.local
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

Le playbook :

- installe Docker sur les 4 VMs (rôle `common`)
- déploie MariaDB sur `dbSrv1` (rôle `database`)
- initialise le Swarm + réseaux + Traefik + Portainer (rôle `swarm-manager`)
- fait rejoindre les workers au cluster (rôle `swarm-worker`)[6]

***

### 3️⃣ Application PHP \& registry

#### 🐳 Build \& push de l’image

Sur `swarm-mgr1` :

```bash
cd /vagrant/app-php
docker build -t harbor.local/my_app/app-php:1.2 .
docker push harbor.local/my_app/app-php:1.2
```

- Base : PHP 8.1 + Apache + extensions PDO MySQL
- Code : dossier `app-php/src`


#### 🐘 MariaDB (dbSrv1)

Sur `dbSrv1` :

```bash
cd /vagrant/docker/database
docker-compose up -d
docker exec -i mariadb mysql -u covoit_user -pmotdepasse covoit < /vagrant/sql/covoit-schema.sql
```

La base `covoit` et ses tables sont créées à partir de `sql/covoit-schema.sql`.

***

### 4️⃣ Traefik \& Portainer

#### 🌐 Traefik (stack Swarm)

```bash
cd /vagrant/docker/traefik
docker stack deploy -c docker-compose.yml traefik
docker service ps traefik_traefik
```

Points clés :

```yaml
command:
  - "--entrypoints.web.address=0.0.0.0:80"
  - "--entrypoints.traefik.address=:8080"
  - "--providers.docker=true"
  - "--providers.docker.swarmMode=true"
  - "--providers.docker.exposedbydefault=false"
  - "--providers.docker.endpoint=unix:///var/run/docker.sock"
  - "--providers.docker.network=traefik-net"
networks:
  traefik-net:
    external: true
```

Dashboard : `http://192.168.56.121:8080`[1]

#### 📊 Portainer

```bash
cd /vagrant/docker/portainer
docker stack deploy -c portainer-stack.yml portainer
```

- Déployé sur `swarm-mgr1` via `placement`
- Socket Docker monté, volume persistant

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

