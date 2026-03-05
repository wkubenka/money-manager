# Migration Plan: Elastic Beanstalk to Oracle Cloud Free Tier

## Overview

Migrate the Money Manager Laravel application from AWS Elastic Beanstalk (us-west-1) to an Oracle Cloud Infrastructure (OCI) Always Free Tier VM instance. DNS will remain on AWS Route 53 and be updated to point to the new Oracle server.

---

## Current State (Elastic Beanstalk)

| Component | Current Setup |
|---|---|
| Platform | PHP 8.5 on Amazon Linux 2023 (single instance) |
| Region | us-west-1 |
| Database | SQLite persisted on EFS (`/mnt/efs/database.sqlite`) |
| Web Server | Nginx (EB-managed) |
| SSL | AWS ACM certificate via EB HTTPS listener |
| DNS | Route 53 → EB environment CNAME |
| Logs | Laravel logs on EFS, tailed by EB |
| Deploys | `npm run build && eb deploy` |
| Env Vars | `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `DB_CONNECTION`, `EFS_ID`, `ADMIN_EMAILS` |

---

## Target State (Oracle Free Tier)

| Component | Target Setup |
|---|---|
| Compute | OCI Always Free ARM-based VM (Ampere A1 — up to 4 OCPUs, 24 GB RAM) |
| OS | Ubuntu 24.04 LTS (or Oracle Linux 9) |
| PHP | 8.2+ via Ondrej PPA (or Remi on Oracle Linux) |
| Database | SQLite on local disk (no EFS needed — VM is persistent) |
| Web Server | Nginx + PHP-FPM |
| SSL | Let's Encrypt (Certbot) with auto-renewal |
| DNS | Route 53 A record → Oracle VM public IP |
| Deploys | Git pull + deploy script (or GitHub Actions via SSH) |

---

## Migration Steps

### Phase 1: Provision Oracle Infrastructure

1. **Create OCI account** (if not already done) and confirm Always Free eligibility
2. **Create a Compute instance**
   - Shape: VM.Standard.A1.Flex (ARM — Always Free eligible)
   - OCPUs: 1–4, Memory: 6–24 GB (allocate as needed)
   - Boot volume: 50 GB (free tier allows up to 200 GB total)
   - Image: Ubuntu 24.04 LTS (Canonical)
   - Region: choose closest available (e.g., us-phoenix-1 or us-ashburn-1)
3. **Configure networking**
   - VCN with public subnet (created by default)
   - Add ingress rules to Security List:
     - TCP 22 (SSH — restrict to your IP)
     - TCP 80 (HTTP)
     - TCP 443 (HTTPS)
4. **Assign a Reserved Public IP** (prevents IP change on reboot)
5. **SSH in and verify access**

### Phase 2: Server Setup

1. **System updates**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

2. **Install PHP 8.2+ and extensions**
   ```bash
   sudo add-apt-repository ppa:ondrej/php -y
   sudo apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml \
     php8.2-curl php8.2-sqlite3 php8.2-zip php8.2-bcmath php8.2-intl \
     php8.2-readline php8.2-opcache
   ```

3. **Install Nginx**
   ```bash
   sudo apt install -y nginx
   ```

4. **Install Composer**
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

5. **Install Node.js (for Vite asset builds)**
   ```bash
   curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
   sudo apt install -y nodejs
   ```

6. **Install Certbot**
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   ```

### Phase 3: Application Setup

1. **Create application directory and user**
   ```bash
   sudo mkdir -p /var/www/money-manager
   sudo chown www-data:www-data /var/www/money-manager
   ```

2. **Clone repository**
   ```bash
   cd /var/www/money-manager
   sudo -u www-data git clone <repo-url> .
   ```

3. **Install dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with production values:
   #   APP_ENV=production
   #   APP_DEBUG=false
   #   APP_URL=https://yourdomain.com
   #   DB_CONNECTION=sqlite
   #   ADMIN_EMAILS=...
   php artisan key:generate   # or copy existing APP_KEY from EB
   ```

5. **Set up SQLite database**
   ```bash
   touch database/database.sqlite
   chmod 664 database/database.sqlite
   php artisan migrate --force
   ```

6. **Set permissions**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache database
   sudo chmod -R 775 storage bootstrap/cache
   ```

7. **Cache configuration**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```

### Phase 4: Nginx Configuration

Create `/etc/nginx/sites-available/money-manager`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/money-manager/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    # Static asset caching (mirrors current EB config)
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location /sw.js {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
    }

    location /manifest.json {
        expires 1d;
        access_log off;
    }

    location ~* \.(ico|png|svg)$ {
        expires 30d;
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/money-manager /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### Phase 5: SSL with Let's Encrypt

```bash
sudo certbot --nginx -d yourdomain.com
```

Certbot will automatically:
- Obtain the certificate
- Modify the Nginx config to listen on 443
- Add HTTP → HTTPS redirect
- Set up auto-renewal via systemd timer

Verify auto-renewal:

```bash
sudo certbot renew --dry-run
```

### Phase 6: DNS Cutover (Route 53)

1. **Before cutover:**
   - Verify the app is running on the Oracle VM by accessing it via its public IP
   - Lower the existing DNS TTL to 60 seconds (do this 24–48 hours in advance)

2. **Update Route 53 record:**
   - Change the existing record for `yourdomain.com` from a CNAME (pointing to EB) to an **A record** pointing to the Oracle VM's reserved public IP
   - If using `www` subdomain, update that record as well

3. **Verify propagation:**
   ```bash
   dig yourdomain.com
   nslookup yourdomain.com
   ```

4. **After propagation is confirmed:**
   - Test the site end-to-end (login, data integrity, HTTPS)
   - Restore DNS TTL to a normal value (e.g., 300–3600 seconds)

### Phase 7: Data Migration

1. **Copy the production SQLite database from EFS:**
   ```bash
   # On EB instance (via eb ssh)
   scp /mnt/efs/database.sqlite user@oracle-vm:/tmp/

   # Or download via eb ssh + scp
   eb ssh -c "cat /mnt/efs/database.sqlite" > database.sqlite
   scp database.sqlite user@oracle-vm:/var/www/money-manager/database/
   ```

2. **Run any pending migrations on the copied database:**
   ```bash
   php artisan migrate --force
   ```

3. **Verify data integrity:**
   - Log in and check accounts, plans, expenses, net worth entries

> **Important:** Time the data copy close to the DNS cutover to minimize data loss. Consider putting the EB app into maintenance mode during the transition window.

### Phase 8: Deploy Script

Create a deploy script at `/var/www/money-manager/deploy.sh`:

```bash
#!/bin/bash
set -e

cd /var/www/money-manager

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart PHP-FPM to clear OPcache
sudo systemctl restart php8.2-fpm
```

Optionally, set up GitHub Actions to SSH into the server and run this script on push to `main`.

### Phase 9: Decommission Elastic Beanstalk

Only after confirming the Oracle setup is stable (suggest waiting 1–2 weeks):

1. Terminate the EB environment: `eb terminate`
2. Delete the EFS file system (after final backup)
3. Delete the ACM certificate (no longer needed)
4. Remove EB-related config from the repo (`.ebextensions/`, `.platform/`, `.elasticbeanstalk/`, `.ebignore`)
5. Keep Route 53 hosted zone (still in use)

---

## Codebase Changes Required

| File/Area | Change |
|---|---|
| `.env` (production) | Remove `EFS_ID`, update `APP_URL` if domain stays the same it's a no-op |
| Deployment docs | Update `CLAUDE.md` deployment section to reflect new process |
| `.ebextensions/` | Remove after decommission |
| `.platform/` | Remove after decommission |
| `.elasticbeanstalk/` | Remove after decommission |
| `.ebignore` | Remove after decommission |

No application code changes are needed — the app uses SQLite and standard Laravel conventions, which work identically on Oracle.

---

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Data loss during migration | Put EB in maintenance mode, copy DB, then cut DNS |
| Oracle Free Tier instance reclaimed | OCI reclaims idle instances after 90 days — set up a lightweight cron or health check to keep it active |
| ARM compatibility issues | PHP and all dependencies support ARM natively; test with `composer install` on the VM |
| No managed SSL | Let's Encrypt auto-renews; monitor expiry with a cron alert |
| No managed backups | Set up a cron job to back up SQLite to object storage (OCI offers 10 GB free) |
| Downtime during cutover | Lower TTL in advance; keep EB running until DNS fully propagates |

---

## Post-Migration Checklist

- [ ] App accessible via HTTPS at production domain
- [ ] All data present and correct (plans, expenses, accounts, net worth)
- [ ] Login and authentication working
- [ ] CSV import working
- [ ] SSL certificate valid and auto-renewing
- [ ] SQLite backup cron configured
- [ ] Deploy script tested
- [ ] DNS TTL restored to normal value
- [ ] Elastic Beanstalk terminated (after stability period)
- [ ] EB-related files removed from repo

---

## Cost Comparison

| | Elastic Beanstalk | Oracle Free Tier |
|---|---|---|
| Compute | ~$8–15/mo (t3.micro) | $0 (Always Free A1) |
| EFS | ~$0.30/mo | N/A (local disk) |
| Route 53 | ~$0.50/mo (hosted zone) | ~$0.50/mo (kept) |
| ACM Certificate | Free | N/A (Let's Encrypt, free) |
| **Total** | **~$9–16/mo** | **~$0.50/mo** |
