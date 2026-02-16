#!/bin/bash
set -e

cd /var/app/current

# --- EFS / SQLite setup ---
EFS_ID=$(/opt/elasticbeanstalk/bin/get-config environment -k EFS_ID)

if [ -n "$EFS_ID" ]; then
    # EFS is configured — it MUST be mounted or we fail
    if ! mountpoint -q /mnt/efs; then
        echo "ERROR: EFS_ID is set but /mnt/efs is not mounted. Attempting mount..."
        mkdir -p /mnt/efs
        mount -t efs -o tls "$EFS_ID":/ /mnt/efs
    fi

    if [ ! -f /mnt/efs/database.sqlite ]; then
        touch /mnt/efs/database.sqlite
    fi

    chown webapp:webapp /mnt/efs/database.sqlite
    chmod 664 /mnt/efs/database.sqlite
    ln -sf /mnt/efs/database.sqlite database/database.sqlite

    # Persist Laravel logs on EFS
    mkdir -p /mnt/efs/storage/logs
    chown -R webapp:webapp /mnt/efs/storage
    chmod -R 775 /mnt/efs/storage
    rm -rf storage/logs
    ln -sf /mnt/efs/storage/logs storage/logs

    echo "SQLite database and logs symlinked from EFS."
else
    echo "WARNING: EFS_ID not set. Using local SQLite (data will not persist)."
    if [ ! -f database/database.sqlite ]; then
        touch database/database.sqlite
    fi
fi

# --- Load EB environment variables for artisan commands ---
# EB env vars may not be in the shell during deployment hooks.
# Use get-config to export them so config:cache bakes in correct values.
for key in $(/opt/elasticbeanstalk/bin/get-config --output YAML environment | cut -d: -f1); do
    export "$key=$(/opt/elasticbeanstalk/bin/get-config environment -k "$key")"
done

# --- Laravel post-deploy tasks ---
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Fix permissions ---
chown -R webapp:webapp storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database

# --- Restart PHP-FPM to clear OPcache ---
systemctl restart php-fpm
