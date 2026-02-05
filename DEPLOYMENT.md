# Deployment Guide - Laravel Ticket API

Kompletní průvodce pro automatický deployment Laravel aplikace na VPS s Dokploy a Traefik.

## 📋 Přehled procesu

```
git push → GitHub Actions → ghcr.io → Dokploy Webhook → Docker Pull → Traefik SSL → 🚀 Live
```

1. **Developer** pushne kód do GitHub `main` větve
2. **GitHub Actions** automaticky vytvoří Docker image
3. **GitHub Actions** spustí webhook na Dokploy
4. **Dokploy** stáhne nový image z ghcr.io a restartuje aplikaci
5. **Traefik** automaticky vyřeší SSL certifikáty a routing

## ✅ Požadavky

- VPS s nainstalovaným Dokploy
- Traefik nakonfigurovaný jako reverse proxy v Dokploy
- Doména `laravel.reinerttomas.com` ukazující na IP adresu VPS
- GitHub Personal Access Token s oprávněním `read:packages`

---

## 🚀 Krok 1: Nastavení GitHub Secrets

V GitHub repository přejděte do **Settings → Secrets and variables → Actions**.

### Secret: DOKPLOY_WEBHOOK_URL

Webhook URL pro automatický deployment z Dokploy.

**Jak získat:**
1. V Dokploy vytvořte nový projekt nebo otevřete existující
2. Přejděte do **Settings → Webhooks** (nebo **Deployment** sekce)
3. Zkopírujte webhook URL ve formátu:
   ```
   https://your-dokploy.com/api/deploy/webhook/your-webhook-id
   ```
4. V GitHub přidejte jako secret `DOKPLOY_WEBHOOK_URL`

---

## 🔐 Krok 2: Přihlášení k GitHub Container Registry

Na VPS se přihlaste k ghcr.io, aby Docker mohl stahovat vaše images:

```bash
# Vytvořte GitHub Personal Access Token
# GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
# Vyberte scope: read:packages

echo "YOUR_GITHUB_TOKEN" | docker login ghcr.io -u YOUR_GITHUB_USERNAME --password-stdin
```

**Ověření:**
```bash
docker pull ghcr.io/reinerttomas/laravel-ticket-api:latest
```

---

## ⚙️ Krok 3: Konfigurace Dokploy

### 3.1 Vytvoření projektu

1. Přihlaste se do Dokploy dashboard
2. Vytvořte nový projekt: **Laravel Ticket API**
3. Zvolte typ: **Docker Compose**

### 3.2 Nastavení Docker Compose

V Dokploy nastavte následující konfiguraci:

**Repository:** `https://github.com/reinerttomas/laravel-ticket-api`
**Branch:** `main`
**Docker Compose file:** `docker-compose.prod.yml`

Nebo vložte obsah souboru manuálně:

```yaml
services:
  php:
    image: ghcr.io/${GITHUB_REPOSITORY:-reinerttomas/laravel-ticket-api}:${IMAGE_TAG:-latest}
    pull_policy: always
    restart: unless-stopped
    networks:
      - web-public
    volumes:
      - "database:/var/www/html/.infrastructure/volume_data/sqlite/"
      - "storage_private:/var/www/html/storage/app/private/"
      - "storage_public:/var/www/html/storage/app/public/"
      - "storage_sessions:/var/www/html/storage/framework/sessions"
      - "storage_logs:/var/www/html/storage/logs"
    environment:
      AUTORUN_ENABLED: "true"
      PHP_OPCACHE_ENABLE: "1"
      SSL_MODE: "full"
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.laravel-ticket-api.rule=Host(`${APP_DOMAIN:-laravel.reinerttomas.com}`)"
      - "traefik.http.routers.laravel-ticket-api.entrypoints=websecure"
      - "traefik.http.routers.laravel-ticket-api.tls=true"
      - "traefik.http.routers.laravel-ticket-api.tls.certresolver=letsencrypt"
      - "traefik.http.services.laravel-ticket-api.loadbalancer.server.port=8080"
      # HTTP to HTTPS redirect
      - "traefik.http.routers.laravel-ticket-api-http.rule=Host(`${APP_DOMAIN:-laravel.reinerttomas.com}`)"
      - "traefik.http.routers.laravel-ticket-api-http.entrypoints=web"
      - "traefik.http.routers.laravel-ticket-api-http.middlewares=redirect-to-https"
      - "traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https"

volumes:
  database:
  storage_private:
  storage_public:
  storage_sessions:
  storage_logs:

networks:
  web-public:
    external: true
```

### 3.3 Environment proměnné v Dokploy

Přidejte následující environment proměnné v Dokploy GUI:

#### 🔑 Povinné proměnné

```env
# GitHub Repository
GITHUB_REPOSITORY=reinerttomas/laravel-ticket-api
IMAGE_TAG=latest

# Domain
APP_DOMAIN=laravel.reinerttomas.com

# Laravel Core
APP_NAME="Laravel Ticket API"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://laravel.reinerttomas.com

# Database (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/.infrastructure/volume_data/sqlite/database.sqlite

# Cache & Sessions
CACHE_STORE=file
SESSION_DRIVER=file

# Queue
QUEUE_CONNECTION=sync

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# Docker Features
AUTORUN_ENABLED=true
PHP_OPCACHE_ENABLE=1
SSL_MODE=full
```

#### 🔐 Generování APP_KEY

**Lokálně:**
```bash
php artisan key:generate --show
```

**Pomocí Docker:**
```bash
docker run --rm ghcr.io/reinerttomas/laravel-ticket-api:latest php artisan key:generate --show
```

Zkopírujte výstup (např. `base64:xyz...`) a použijte jako `APP_KEY`.

---

## 🌐 Krok 4: Konfigurace Traefik

### 4.1 Ověření Traefik instalace

Zkontrolujte, že Traefik běží v Dokploy:

```bash
docker ps | grep traefik
```

### 4.2 Traefik Static Configuration

Ujistěte se, že Traefik má správnou konfiguraci (obvykle Dokploy to nastavuje automaticky):

```yaml
# /etc/traefik/traefik.yml nebo podobná cesta
entryPoints:
  web:
    address: ":80"

  websecure:
    address: ":443"

certificatesResolvers:
  letsencrypt:
    acme:
      email: your-email@example.com
      storage: /letsencrypt/acme.json
      httpChallenge:
        entryPoint: web
```

### 4.3 Vytvoření Traefik sítě

Pokud síť `web-public` neexistuje, vytvořte ji:

```bash
docker network create web-public
```

Ověření:
```bash
docker network ls | grep web-public
```

### 4.4 DNS konfigurace

Ujistěte se, že doména ukazuje na správnou IP:

```bash
dig laravel.reinerttomas.com +short
# Mělo by vrátit IP adresu vašeho VPS
```

Pokud ne, přidejte A záznam v DNS:

```
Type: A
Name: laravel
Value: YOUR_VPS_IP
TTL: 3600
```

---

## 🎯 Krok 5: První deployment

### 5.1 Automatický deployment (doporučeno)

Po správné konfiguraci webhooků stačí:

```bash
git add .
git commit -m "Initial production deployment"
git push origin main
```

GitHub Actions automaticky:
1. ✅ Vytvoří Docker image
2. ✅ Nahraje do ghcr.io s tagem `latest` a `sha-XXXXX`
3. ✅ Spustí Dokploy webhook
4. ✅ Dokploy stáhne image a spustí aplikaci

### 5.2 Manuální deployment

Pokud webhook nefunguje, v Dokploy:

1. Otevřete projekt **Laravel Ticket API**
2. Klikněte na **Deploy** nebo **Rebuild**
3. Dokploy stáhne nejnovější image a spustí

---

## ✅ Krok 6: Ověření deploymentu

### 6.1 Kontrola běžících kontejnerů

```bash
docker ps | grep laravel-ticket-api
```

### 6.2 Kontrola logů aplikace

```bash
# Najděte container ID
CONTAINER_ID=$(docker ps --filter "name=laravel-ticket-api" --format "{{.ID}}")

# Logy aplikace
docker logs -f $CONTAINER_ID

# Laravel logy
docker exec $CONTAINER_ID tail -f storage/logs/laravel.log
```

### 6.3 Kontrola SSL certifikátu

```bash
curl -I https://laravel.reinerttomas.com
```

Měli byste vidět:
```
HTTP/2 200
server: Caddy
...
```

### 6.4 Test API endpointu

```bash
# Health check
curl https://laravel.reinerttomas.com/up

# API endpoint (pokud existuje)
curl https://laravel.reinerttomas.com/api/health
```

### 6.5 Ověření Traefik

```bash
# Traefik logs
docker logs traefik | grep laravel

# Traefik dashboard (pokud je povolený)
https://traefik.your-dokploy.com/dashboard/
```

---

## 🔄 Workflow a aktualizace

### Automatický deployment workflow

```bash
# Vývojářský workflow
git checkout -b feature/new-endpoint
# ... změny v kódu ...
git add .
git commit -m "Add new endpoint"
git push origin feature/new-endpoint

# Vytvoření Pull Requestu a merge do main
# Po merge:
git checkout main
git pull
# 🚀 Automaticky se spustí GitHub Actions → Deploy
```

### Manuální deploy přes GitHub Actions

1. GitHub → **Actions** tab
2. Workflow: **Deploy to Dokploy**
3. Klikněte **Run workflow**
4. Zadejte důvod deploymentu (volitelné)
5. **Run workflow**

### Rollback na předchozí verzi

```bash
# Najděte SHA commitu, na který chcete vrátit
git log --oneline -n 10

# Stáhněte specifickou verzi image
docker pull ghcr.io/reinerttomas/laravel-ticket-api:sha-abc123

# V Dokploy nastavte environment proměnnou
IMAGE_TAG=sha-abc123

# Redeploy v Dokploy
```

---

## 🔧 Údržba a správa

### Migrace databáze

Migrace se spouští **automaticky** při startu kontejneru (díky `AUTORUN_ENABLED=true`).

**Manuální spuštění:**
```bash
docker exec $CONTAINER_ID php artisan migrate --force
```

### Cache clear

```bash
docker exec $CONTAINER_ID php artisan cache:clear
docker exec $CONTAINER_ID php artisan config:clear
docker exec $CONTAINER_ID php artisan route:clear
docker exec $CONTAINER_ID php artisan view:clear
```

### Backup databáze

```bash
# Vytvoření backupu
CONTAINER_ID=$(docker ps --filter "name=laravel-ticket-api" --format "{{.ID}}")
BACKUP_NAME="database-backup-$(date +%Y%m%d-%H%M%S).sqlite"

docker exec $CONTAINER_ID cp \
  /var/www/html/.infrastructure/volume_data/sqlite/database.sqlite \
  /var/www/html/storage/app/backups/$BACKUP_NAME

# Stažení backupu na lokální počítač
docker cp $CONTAINER_ID:/var/www/html/storage/app/backups/$BACKUP_NAME ./
```

### Automatický backup (cron)

Vytvořte backup script na VPS:

```bash
#!/bin/bash
# /opt/scripts/backup-laravel.sh

CONTAINER=$(docker ps --filter "name=laravel-ticket-api" --format "{{.ID}}")
BACKUP_DIR="/backups/laravel-ticket-api"
DATE=$(date +%Y%m%d-%H%M%S)

mkdir -p $BACKUP_DIR

docker exec $CONTAINER sqlite3 \
  /var/www/html/.infrastructure/volume_data/sqlite/database.sqlite \
  ".backup /tmp/backup-$DATE.db"

docker cp $CONTAINER:/tmp/backup-$DATE.db $BACKUP_DIR/

# Smazat starší než 30 dní
find $BACKUP_DIR -name "backup-*.db" -mtime +30 -delete
```

Přidejte do crontab:
```bash
crontab -e

# Každý den ve 2:00 ráno
0 2 * * * /opt/scripts/backup-laravel.sh
```

### Čištění starých Docker images

```bash
# Vyčistit nepoužívané images starší než 30 dní
docker image prune -a --filter "until=720h"

# Vyčistit nepoužívané volumes (POZOR!)
docker volume prune
```

---

## 🐛 Troubleshooting

### ❌ Image se nestahuje

**Problém:** `Error response from daemon: pull access denied`

**Řešení:**
```bash
# Zkontrolujte přihlášení
docker login ghcr.io

# Manuálně stáhněte image
docker pull ghcr.io/reinerttomas/laravel-ticket-api:latest

# Zkontrolujte oprávnění tokenu
# Token musí mít scope: read:packages
```

### ❌ Traefik nefunguje / 502 Bad Gateway

**Problém:** Aplikace není dostupná přes doménu

**Řešení:**
```bash
# 1. Zkontrolujte Traefik logy
docker logs traefik | grep laravel

# 2. Zkontrolujte síť
docker network inspect web-public
# Měl by tam být kontejner laravel-ticket-api

# 3. Zkontrolujte Traefik labels na kontejneru
docker inspect $CONTAINER_ID | grep traefik

# 4. Zkontrolujte port
docker exec $CONTAINER_ID netstat -tuln | grep 8080

# 5. Test lokálního připojení
docker exec traefik wget -O- http://laravel-ticket-api:8080/up
```

### ❌ SSL certifikát se nevytváří

**Problém:** Let's Encrypt certifikát se negeneruje

**Řešení:**
```bash
# 1. Zkontrolujte DNS
dig laravel.reinerttomas.com +short
# Musí vrátit IP vašeho VPS

# 2. Zkontrolujte porty 80 a 443
sudo netstat -tlnp | grep -E ':80|:443'

# 3. Zkontrolujte Traefik acme.json
docker exec traefik cat /letsencrypt/acme.json

# 4. Zkontrolujte Traefik logy
docker logs traefik | grep acme

# 5. Restart Traefik
docker restart traefik
```

### ❌ Aplikace vrací 500 Error

**Problém:** Interní chyba serveru

**Řešení:**
```bash
# 1. Zkontrolujte logy
docker logs $CONTAINER_ID

# 2. Zkontrolujte APP_KEY
docker exec $CONTAINER_ID env | grep APP_KEY
# Nesmí být prázdný

# 3. Zkontrolujte oprávnění
docker exec $CONTAINER_ID ls -la storage/

# 4. Clear cache
docker exec $CONTAINER_ID php artisan config:clear
docker exec $CONTAINER_ID php artisan cache:clear

# 5. Dočasně zapněte debug mode (pouze pro diagnostiku!)
# V Dokploy nastavte APP_DEBUG=true
# NEZAPOMEŇTE vrátit zpět na false!
```

### ❌ Databáze se nepersistuje

**Problém:** Data mizí po restartu

**Řešení:**
```bash
# 1. Zkontrolujte volumes
docker volume ls | grep laravel

# 2. Zkontrolujte mount pointy
docker inspect $CONTAINER_ID | grep -A 20 Mounts

# 3. Zkontrolujte cestu k databázi
docker exec $CONTAINER_ID ls -la /var/www/html/.infrastructure/volume_data/sqlite/

# 4. Test zápisu
docker exec $CONTAINER_ID touch /var/www/html/.infrastructure/volume_data/sqlite/test.txt
docker restart $CONTAINER_ID
docker exec $CONTAINER_ID ls /var/www/html/.infrastructure/volume_data/sqlite/test.txt
```

### ❌ Webhook nefunguje

**Problém:** Automatický deploy se nespouští

**Řešení:**
```bash
# 1. Zkontrolujte GitHub Actions logy
# GitHub → Actions → Deploy to Dokploy

# 2. Test webhooku manuálně
curl -X POST "YOUR_DOKPLOY_WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d '{"branch": "main", "message": "Test deploy"}'

# 3. Zkontrolujte GitHub Secret
# Settings → Secrets → DOKPLOY_WEBHOOK_URL

# 4. Zkontrolujte síťovou dostupnost
ping your-dokploy-domain.com
```

---

## 📊 Monitoring

### Health check endpoint

```bash
# Přidejte do UptimeRobot, Pingdom, nebo podobných služeb
curl https://laravel.reinerttomas.com/up
```

### Sledování logů v reálném čase

```bash
# Aplikační logy
docker logs -f --tail 100 $CONTAINER_ID

# Laravel logy
docker exec $CONTAINER_ID tail -f storage/logs/laravel.log

# Traefik access logy
docker logs traefik | grep laravel-ticket-api
```

### Metriky kontejneru

```bash
# CPU a RAM usage
docker stats $CONTAINER_ID

# Disk usage volumes
docker system df -v
```

---

## 🔒 Bezpečnostní checklist

- ✅ `APP_DEBUG=false` v produkci
- ✅ Silný `APP_KEY` vygenerovaný artisan
- ✅ `APP_ENV=production`
- ✅ Žádný `.env` soubor v Docker image
- ✅ Secrets uložené v Dokploy (encrypted)
- ✅ SSL certifikáty automaticky obnovované
- ✅ Žádné credentials v Git repository
- ✅ GitHub token pouze s `read:packages` oprávněním
- ✅ Pravidelné backupy databáze
- ✅ Firewall pravidla (pouze porty 80, 443, 22)

---

## 📚 Reference a užitečné příkazy

### Docker Compose příkazy

```bash
# Start
docker compose -f docker-compose.prod.yml up -d

# Stop
docker compose -f docker-compose.prod.yml down

# Restart
docker compose -f docker-compose.prod.yml restart

# Pull nový image
docker compose -f docker-compose.prod.yml pull

# Logy
docker compose -f docker-compose.prod.yml logs -f
```

### Laravel Artisan příkazy

```bash
# Migrace
docker exec $CONTAINER_ID php artisan migrate --force

# Cache
docker exec $CONTAINER_ID php artisan cache:clear
docker exec $CONTAINER_ID php artisan config:cache

# Tinker
docker exec -it $CONTAINER_ID php artisan tinker

# Seznam routes
docker exec $CONTAINER_ID php artisan route:list
```

### Environment proměnné

| Proměnná | Povinná | Výchozí | Popis |
|----------|---------|---------|-------|
| `GITHUB_REPOSITORY` | Ano | - | Název GitHub repository |
| `IMAGE_TAG` | Ne | `latest` | Tag Docker image |
| `APP_DOMAIN` | Ano | - | Doména aplikace |
| `APP_NAME` | Ano | - | Název aplikace |
| `APP_ENV` | Ano | `production` | Prostředí |
| `APP_KEY` | Ano | - | Šifrovací klíč |
| `APP_DEBUG` | Ano | `false` | Debug mode |
| `APP_URL` | Ano | - | URL aplikace s protokolem |
| `DB_CONNECTION` | Ano | `sqlite` | Databázový driver |
| `DB_DATABASE` | Ano | - | Cesta k SQLite databázi |

---

## 🔗 Užitečné odkazy

- **GitHub Repository:** https://github.com/reinerttomas/laravel-ticket-api
- **GitHub Actions:** https://github.com/reinerttomas/laravel-ticket-api/actions
- **GitHub Container Registry:** https://github.com/reinerttomas/laravel-ticket-api/pkgs/container/laravel-ticket-api
- **Dokploy Docs:** https://docs.dokploy.com
- **Traefik Docs:** https://doc.traefik.io/traefik/
- **Laravel Deployment:** https://laravel.com/docs/deployment

---

**Vytvořeno:** 2026-02-04
**Verze dokumentace:** 2.0
**Autor:** Tomáš Reinert
