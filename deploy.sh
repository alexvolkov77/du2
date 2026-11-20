#!/usr/bin/env bash
set -Eeuo pipefail

# One-command update for lk.du2.ru. Run from any directory:
# bash ~/du2-app/deploy.sh
cd "$(dirname "$(readlink -f "$0")")"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
  echo "Остановлено: в проекте есть незакоммиченные изменения."
  git status --short
  exit 1
fi

echo "[1/6] Обновление кода"
git pull --ff-only origin main

echo "[2/6] PHP зависимости"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[3/6] JS зависимости"
npm ci --no-audit --no-fund

echo "[4/6] Сборка Vue"
npm run build

echo "[5/6] Миграции"
php artisan migrate --force

echo "[6/6] Очистка и подготовка кэша"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

echo "Релиз установлен: $(git rev-parse --short HEAD)"
