#!/usr/bin/env bash
set -Eeuo pipefail

# One-command deployment: bash ~/du2-app/deploy.sh
APP_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
cd "$APP_DIR"
DOMAIN="lk.du2.ru"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Остановлено: есть незакоммиченные изменения в отслеживаемых файлах." >&2
    git status --short
    exit 1
fi

echo "[1/7] Обновление кода"
git pull --ff-only origin main

echo "[2/7] PHP-зависимости"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[3/7] JS-зависимости"
npm ci --no-audit --no-fund

echo "[4/7] Сборка Vue"
npm run build
test -f "$APP_DIR/public/build/manifest.json" || { echo "Не найден public/build/manifest.json" >&2; exit 1; }

echo "[5/7] Публикация собранных CSS и JS"
# The first install copied public/ into a separate hosting document root.
# Rebuilding public/build alone does not make new asset hashes available there.
WEBROOT="${WEBROOT:-}"
if [[ -z "$WEBROOT" ]]; then
    for candidate in \
        "$APP_DIR/public" \
        "$HOME/data/www/$DOMAIN" \
        "$HOME/www/$DOMAIN" \
        "$HOME/domains/$DOMAIN/public_html" \
        "$HOME/$DOMAIN/public_html" \
        "$HOME/$DOMAIN"; do
        if [[ -f "$candidate/index.php" ]] && \
           { [[ "$(readlink -f "$candidate")" == "$(readlink -f "$APP_DIR/public")" ]] || grep -Fq "$APP_DIR" "$candidate/index.php"; }; then
            WEBROOT="$candidate"
            break
        fi
    done
fi
if [[ -z "$WEBROOT" || ! -f "$WEBROOT/index.php" ]]; then
    echo "Не удалось определить document root сайта. Укажи WEBROOT=/полный/путь bash deploy.sh" >&2
    exit 1
fi
if [[ "$(readlink -f "$WEBROOT")" != "$(readlink -f "$APP_DIR/public")" ]]; then
    mkdir -p "$WEBROOT/build"
    cp -a "$APP_DIR/public/build/." "$WEBROOT/build/"
fi
echo "Assets: $WEBROOT/build"

echo "[6/7] Миграции"
php artisan migrate --force

echo "[7/7] Кэш Laravel"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

echo "Релиз установлен: $(git rev-parse --short HEAD) · https://$DOMAIN"
