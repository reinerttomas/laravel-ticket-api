# Deployment Guide - Dokploy

This guide covers deploying the Laravel Ticket API to a VPS using Dokploy with Docker Compose.

## Overview

The application uses:
- **Docker** for containerization
- **Dokploy** for deployment orchestration
- **GitHub Container Registry** (ghcr.io) for Docker images
- **SQLite** for the database (file-based, with persistent volume)
- **Environment variables** injected at runtime (not baked into the image)

## Architecture

```
GitHub Actions (CI/CD)
    ↓
Builds Docker Image (without .env)
    ↓
Pushes to ghcr.io
    ↓
Dokploy pulls image
    ↓
Injects ENV variables (from Dokploy GUI)
    ↓
Runs container with persistent SQLite volume
```

## Prerequisites

1. VPS with Dokploy installed
2. GitHub repository connected to Dokploy
3. GitHub Container Registry access configured

## Step-by-Step Deployment

### 1. Create New Service in Dokploy

1. Log in to your Dokploy dashboard
2. Click **"Create Service"** or **"New Project"**
3. Select **"Docker Compose"** as the service type
4. Name your service (e.g., `laravel-ticket-api`)

### 2. Connect GitHub Repository

1. Select **"GitHub"** as the source
2. Choose your repository: `reinerttomas/laravel-ticket-api`
3. Set branch: **`main`**
4. Set Docker Compose file path: **`docker-compose.prod.yml`**

### 3. Configure Environment Variables

In the Dokploy GUI, add the following environment variables:

#### Required Variables

```bash
# Application
APP_NAME=LaravelTicketAPI
APP_ENV=production
APP_KEY=base64:GENERATED_KEY_HERE  # See generation instructions below
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/.infrastructure/volume_data/sqlite/database.sqlite

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# Cache & Session
CACHE_STORE=file
SESSION_DRIVER=file
```

#### Optional Variables (if using queue workers)

```bash
QUEUE_CONNECTION=database  # or redis if you add Redis
```

#### Generating APP_KEY

To generate a secure `APP_KEY`:

**Option 1: Using Local Laravel Installation**
```bash
php artisan key:generate --show
```

**Option 2: Using Docker**
```bash
docker run --rm reinerttomas/laravel-ticket-api php artisan key:generate --show
```

Copy the output (e.g., `base64:xyz...`) and paste it into the `APP_KEY` environment variable in Dokploy.

### 4. Configure Volume Persistence

The `docker-compose.prod.yml` already configures the SQLite volume:

```yaml
volumes:
  - sqlite_data:/var/www/html/.infrastructure/volume_data/sqlite
```

**Important**: This ensures your database persists across container restarts and redeployments.

### 5. Deploy the Application

1. Click **"Deploy"** in Dokploy
2. Dokploy will:
   - Pull the latest image from `ghcr.io/reinerttomas/laravel-ticket-api:latest`
   - Start the container with your environment variables
   - Mount the persistent SQLite volume
   - Run migrations automatically (via `AUTORUN_ENABLED=true`)

### 6. Verify Deployment

#### Check Container Status

```bash
docker ps | grep laravel-ticket-api
```

#### Check Logs

```bash
docker logs <container_id>
```

Look for:
- ✅ Migrations run successfully
- ✅ Application optimizations completed
- ✅ No errors

#### Test Health Check

```bash
curl https://your-domain.com/up
```

Expected response: HTTP 200 with "OK" or health status.

#### Verify Environment Variables

```bash
docker exec -it <container_id> bash
env | grep APP_
php artisan config:show app
```

#### Check Database

```bash
docker exec -it <container_id> sqlite3 /var/www/html/.infrastructure/volume_data/sqlite/database.sqlite
sqlite> .tables
sqlite> .quit
```

You should see all Laravel tables (migrations, users, etc.).

## Database Migrations

Migrations run automatically on container startup thanks to the `AUTORUN_ENABLED=true` feature in the Docker image.

If you need to run migrations manually:

```bash
docker exec -it <container_id> php artisan migrate --force
```

The `--force` flag is required in production environments.

## Updating the Application

### Automatic Updates (Recommended)

1. Push changes to the `main` branch
2. GitHub Actions automatically builds and pushes a new image
3. In Dokploy, click **"Redeploy"**
4. Dokploy pulls the new image and restarts the container
5. Migrations run automatically

### Manual Image Pull

```bash
docker pull ghcr.io/reinerttomas/laravel-ticket-api:latest
```

Then redeploy in Dokploy.

## Rollback Procedure

If a deployment fails:

1. In Dokploy, navigate to **"Deployments"**
2. Select a previous successful deployment
3. Click **"Rollback"**
4. Environment variables and database volumes are preserved

## Troubleshooting

### Container Won't Start

**Check logs:**
```bash
docker logs <container_id>
```

Common issues:
- Missing `APP_KEY` → Generate and set it
- Invalid `DB_DATABASE` path → Verify volume mount
- Permission issues → Check volume permissions

### Database Not Persisting

**Verify volume:**
```bash
docker volume ls | grep sqlite
docker volume inspect <volume_name>
```

**Check mount:**
```bash
docker inspect <container_id> | grep -A 10 Mounts
```

### Migrations Not Running

**Manually run migrations:**
```bash
docker exec -it <container_id> php artisan migrate --force
```

**Check AUTORUN is enabled:**
```bash
docker exec -it <container_id> env | grep AUTORUN
```

Should show: `AUTORUN_ENABLED=true`

### Application Returns 500 Error

**Check APP_KEY:**
```bash
docker exec -it <container_id> php artisan config:show app
```

**Clear cache:**
```bash
docker exec -it <container_id> php artisan config:clear
docker exec -it <container_id> php artisan cache:clear
```

## Security Checklist

- ✅ `APP_DEBUG=false` in production
- ✅ Strong `APP_KEY` generated
- ✅ `APP_ENV=production`
- ✅ No `.env` file in Docker image
- ✅ Secrets stored in Dokploy (encrypted)
- ✅ SQLite database in persistent volume
- ✅ No credentials in git repository

## Performance Optimization

The Docker image automatically runs these optimizations on startup:
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

No manual intervention required.

## Monitoring

### Health Check Endpoint

The application includes a built-in health check:

```bash
curl https://your-domain.com/up
```

Configure monitoring tools (UptimeRobot, Pingdom, etc.) to poll this endpoint.

### Logs

**View real-time logs:**
```bash
docker logs -f <container_id>
```

**Laravel logs inside container:**
```bash
docker exec -it <container_id> tail -f storage/logs/laravel.log
```

## Backup Strategy

### Database Backup

```bash
# Create backup
docker exec <container_id> sqlite3 /var/www/html/.infrastructure/volume_data/sqlite/database.sqlite ".backup /tmp/backup.db"

# Copy to host
docker cp <container_id>:/tmp/backup.db ./backup-$(date +%Y%m%d).db
```

### Automated Backups

Add a cron job on your VPS:

```bash
0 2 * * * /path/to/backup-script.sh
```

Example backup script:
```bash
#!/bin/bash
CONTAINER=$(docker ps --filter "name=laravel-ticket-api" --format "{{.ID}}")
BACKUP_DIR="/backups/laravel-ticket-api"
DATE=$(date +%Y%m%d-%H%M%S)

mkdir -p $BACKUP_DIR
docker exec $CONTAINER sqlite3 /var/www/html/.infrastructure/volume_data/sqlite/database.sqlite ".backup /tmp/backup.db"
docker cp $CONTAINER:/tmp/backup.db $BACKUP_DIR/database-$DATE.db

# Keep only last 7 days
find $BACKUP_DIR -name "database-*.db" -mtime +7 -delete
```

## Environment Variables Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_NAME` | Yes | - | Application name |
| `APP_ENV` | Yes | `production` | Environment (production/staging) |
| `APP_KEY` | Yes | - | Encryption key (generate with artisan) |
| `APP_DEBUG` | Yes | `false` | Debug mode (MUST be false in prod) |
| `APP_URL` | Yes | - | Full application URL with protocol |
| `DB_CONNECTION` | Yes | `sqlite` | Database driver |
| `DB_DATABASE` | Yes | - | Full path to SQLite database file |
| `LOG_CHANNEL` | No | `stack` | Logging channel |
| `LOG_LEVEL` | No | `error` | Minimum log level |
| `CACHE_STORE` | No | `file` | Cache driver |
| `SESSION_DRIVER` | No | `file` | Session driver |

## Support

For issues or questions:
- Check application logs: `docker logs <container_id>`
- Review Laravel logs: `storage/logs/laravel.log`
- Verify environment: `php artisan config:show`

## Additional Resources

- [Dokploy Documentation](https://docs.dokploy.com)
- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
