# Migrate from Elastic Beanstalk to Oracle Cloud Always Free

## Context

Two personal Laravel projects each run on their own Elastic Beanstalk environment with an ALB and EFS (~$78/month total). Moving both onto a single Oracle Cloud Always Free ARM instance brings this to $0/month.

**Oracle Cloud Always Free specs:** Up to 4 ARM OCPUs, 24 GB RAM, 200 GB block storage — permanently free.

---

## What changes in this repo

### 1. Simplify `app/Providers/AppServiceProvider.php`
Remove the CloudFront/ALB proxy workarounds (lines 44-56). Nginx terminates TLS directly, so no proxy mismatch. Keep `URL::forceScheme('https')`.

### 2. Narrow trusted proxies in `bootstrap/app.php`
Change `trustProxies(at: '*')` to remove it or set to `'127.0.0.1'` — no ALB/CloudFront in front.

### 3. Delete EB-specific files
- `.ebextensions/` (4 files)
- `.platform/` (5 files across hooks, confighooks, nginx)
- `.elasticbeanstalk/`
- `.ebignore`

### 4. Add `deploy.sh`
Replaces `.platform/hooks/postdeploy/01_laravel.sh`. Builds assets on the server:
```bash
#!/bin/bash
set -e
APP_DIR="/var/www/money-manager"
cd "$APP_DIR"
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
sudo systemctl restart php-fpm
```

### 5. Update CLAUDE.md deployment section
Replace EB deployment docs with new workflow.

---

## Infrastructure guide (manual server setup)

### Oracle Cloud instance
- **Shape:** VM.Standard.A1.Flex (ARM) — allocate 2 OCPUs, 12 GB RAM (leaves room for the second project's instance or a single shared instance)
- **OS:** Oracle Linux 8 or Ubuntu 22.04 (both available in OCI free tier)
- **Region:** Pick one with ARM availability (us-ashburn-1, us-phoenix-1 tend to have capacity)
- **Reserve a public IP** for DNS stability

### Storage layout
Use the instance's boot volume (47-200 GB included free). No separate volume needed at this scale, but keep data in a dedicated directory:

```
/data/money-manager/database.sqlite
/data/money-manager/storage/logs/
/data/second-project/database.sqlite
/data/second-project/storage/logs/
```
Symlink each app's `database/database.sqlite` and `storage/logs/` into `/data/`.

### Software stack
- PHP 8.2+ with FPM, Node.js 20, Composer
- **Nginx** — two vhosts, one per domain, `root /var/www/{project}/public`
- **Certbot** with nginx plugin for free auto-renewing HTTPS

### Security
- OCI security list / network security group: port 22 (your IP), 80, 443
- SSH key-only auth
- Automatic OS security updates
- `iptables` rules on the instance as defense-in-depth (OCI's default `iptables` blocks everything except SSH — you'll need to open 80/443 at both the OCI security list AND the OS firewall)

---

## SQLite backup strategy

| Layer | Frequency | Method | Retention |
|---|---|---|---|
| Object Storage backup | Daily (cron) | `sqlite3 .backup` then upload to OCI Object Storage (free 10 GB) or AWS S3 | 1 year |
| Boot volume backup | Weekly | OCI boot volume backup (5 free backups included) | 4 weeks |

Daily cron script:
```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
for project in money-manager second-project; do
    DB="/data/$project/database.sqlite"
    [ -f "$DB" ] && sqlite3 "$DB" ".backup /tmp/${project}-${TIMESTAMP}.sqlite"
    # Upload to OCI Object Storage (free tier) or S3
    oci os object put --bucket-name backups --file "/tmp/${project}-${TIMESTAMP}.sqlite" \
        --name "${project}/${TIMESTAMP}.sqlite"
    rm -f "/tmp/${project}-${TIMESTAMP}.sqlite"
done
```

Key: use `sqlite3 .backup` (not `cp`) for a consistent copy during writes.

---

## Migration sequence

1. **Create OCI account** and provision ARM instance
2. **Open firewall** — OCI security list + OS `iptables` for ports 80, 443
3. **Install stack** — PHP 8.2, nginx, Composer, Node.js, Certbot
4. **Deploy apps** — clone repos, install deps, configure `.env`, set up `/data/` symlinks
5. **Test** — hit the public IP directly (local `/etc/hosts` override)
6. **Lower DNS TTL** to 60s, wait for old TTL to expire
7. **Copy databases** — put EB apps in maintenance mode, `scp` SQLite files from EFS to `/data/`
8. **Cut DNS** — point A records to the OCI instance's reserved IP
9. **Run Certbot** — obtain SSL certificates
10. **Verify** — test everything, restore DNS TTL
11. **Set up backups** — daily cron + weekly volume backups
12. **Decommission EB** — terminate environments, delete EFS, ALB, ACM cert

**Expected downtime:** 10-30 minutes.

---

## Cost comparison

| | Current (2x EB) | Oracle Cloud Free |
|---|---|---|
| Compute | ~$30 | $0 |
| Load balancer | ~$44 | $0 |
| Storage | ~$3 | $0 |
| Backups | $0 | $0 |
| **Total** | **~$78/month** | **$0/month** |

---

## Verification
- Both apps load over HTTPS with valid certificates
- Login, CRUD, CSV import all work
- Database contains expected data after migration
- Backup cron runs and files appear in Object Storage
- `sudo certbot renew --dry-run` succeeds
- Run test suites: `php artisan test --compact`
