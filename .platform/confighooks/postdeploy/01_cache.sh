#!/bin/bash
set -e

cd /var/app/current

php artisan config:cache
php artisan route:cache
php artisan view:cache
