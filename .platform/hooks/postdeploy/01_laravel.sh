#!/bin/bash
set -e

cd /var/app/current

# --- EFS / SQLite setup ---
if mountpoint -q /mnt/efs; then
    # Create SQLite database on EFS if it doesn't exist
    if [ ! -f /mnt/efs/database.sqlite ]; then
        touch /mnt/efs/database.sqlite
    fi

    # Symlink so Laravel finds it at the default path
    ln -sf /mnt/efs/database.sqlite database/database.sqlite
else
    echo "WARNING: EFS not mounted. SQLite data will not persist across deploys."
    # Ensure a local SQLite file exists as fallback
    if [ ! -f database/database.sqlite ]; then
        touch database/database.sqlite
    fi
fi

# --- Laravel post-deploy tasks ---
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Fix permissions ---
chown -R webapp:webapp storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
