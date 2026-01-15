#!/usr/bin/env sh
set -e

APP_DIR="/var/www/html/app"

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "Bootstrapping Laravel skeleton..."
  mkdir -p "$APP_DIR"
  composer create-project laravel/laravel "$APP_DIR" --no-interaction
fi

if [ -f "/var/www/html/.env.example" ] && [ ! -f "$APP_DIR/.env" ]; then
  cp /var/www/html/.env.example "$APP_DIR/.env"
fi

if [ -f "$APP_DIR/artisan" ] && [ -f "$APP_DIR/.env" ]; then
  if ! grep -q "^APP_KEY=base64:" "$APP_DIR/.env"; then
    php "$APP_DIR/artisan" key:generate --force
  fi
fi

exec php-fpm
