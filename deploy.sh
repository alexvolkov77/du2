#!/usr/bin/env bash
set -Eeuo pipefail

# One-command production deployment: bash ~/du2-app/deploy.sh
APP_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd -P)"
cd "$APP_DIR"
DOMAIN="lk.du2.ru"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Остановлено: есть незакоммиченные изменения в отслеживаемых файлах." >&2
    git status --short
    exit 1
fi

echo "[1/8] Обновление кода"
git pull --ff-only origin main

echo "[2/8] PHP-зависимости"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[3/8] JS-зависимости"
npm ci --no-audit --no-fund

echo "[4/8] Сборка Vue"
npm run build
test -f "$APP_DIR/public/build/manifest.json" || { echo "Не найден public/build/manifest.json" >&2; exit 1; }

echo "[5/8] Публикация CSS и JS в document root"
# During the first installation, install.sh copied public/ into a separate hosting
# document root and generated its index.php pointing back to APP_DIR.
# Never select APP_DIR/public first: its files can be invisible to the web server.
WEBROOT="${WEBROOT:-}"
if [[ -z "$WEBROOT" ]]; then
    for candidate in \
        "$HOME/data/www/$DOMAIN" \
        "$HOME/www/$DOMAIN" \
        "$HOME/domains/$DOMAIN/public_html" \
        "$HOME/$DOMAIN/public_html" \
        "$HOME/$DOMAIN" \
        "$HOME/public_html"; do
        if [[ -f "$candidate/index.php" ]] && grep -Fq "$APP_DIR" "$candidate/index.php"; then
            WEBROOT="$candidate"
            break
        fi
    done
fi

if [[ -z "$WEBROOT" || ! -f "$WEBROOT/index.php" ]]; then
    echo "Не найден webroot сайта $DOMAIN с index.php, ссылающимся на $APP_DIR." >&2
    echo "Проверь каталог домена и выполни: WEBROOT=/путь/к/корню/сайта bash ~/du2-app/deploy.sh" >&2
    echo "Возможные каталоги:" >&2
    for guess in "$HOME/www/$DOMAIN" "$HOME/data/www/$DOMAIN" "$HOME/domains/$DOMAIN/public_html"; do
        if [[ -d "$guess" ]]; then echo "  $guess" >&2; fi
    done
    exit 1
fi
WEBROOT="$(cd "$WEBROOT" && pwd -P)"
if [[ "$WEBROOT" == "$APP_DIR" ]]; then
    echo "WEBROOT не должен совпадать с корнем приложения." >&2
    exit 1
fi

if [[ "$WEBROOT" != "$(cd "$APP_DIR/public" && pwd -P)" ]]; then
    mkdir -p "$WEBROOT/build"
    cp -a "$APP_DIR/public/build/." "$WEBROOT/build/"
fi
echo "Опубликовано: $WEBROOT/build"

echo "[6/8] Проверка доступности CSS и JS через сайт"
mapfile -t assets < <(php -r '
$m = json_decode(file_get_contents($argv[1]), true);
foreach (["resources/js/app.ts", "resources/css/app.css"] as $key) {
    if (!empty($m[$key]["file"])) echo $m[$key]["file"]."\n";
}
' "$APP_DIR/public/build/manifest.json")
if [[ "${#assets[@]}" -eq 0 ]]; then
    echo "Нет входных JS/CSS файлов в Vite manifest" >&2
    exit 1
fi
for asset in "${assets[@]}"; do
    url="https://$DOMAIN/build/$asset"
    status="$(curl -kLsS -o /dev/null -w '%{http_code}' --max-time 20 "$url")" || true
    echo "HTTP $status: $url"
    if [[ "$status" != 200 ]]; then
        echo "Ошибка публикации: проверь, что document root домена — $WEBROOT" >&2
        exit 1
    fi
done

echo "[7/8] Миграции"
php artisan migrate --force

echo "[8/8] Кэш Laravel"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

echo "Релиз установлен: $(git rev-parse --short HEAD) · https://$DOMAIN"
