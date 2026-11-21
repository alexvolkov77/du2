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

LOGO_SOURCE="$APP_DIR/public/images/du2-logo.webp"
LOGO_PUBLIC="$WEBROOT/images/du2-logo.webp"
ICON_SOURCE="$APP_DIR/public/favicon.ico"
ICON_PUBLIC="$WEBROOT/favicon.ico"
APPLE_ICON_SOURCE="$APP_DIR/public/apple-touch-icon.png"
APPLE_ICON_PUBLIC="$WEBROOT/apple-touch-icon.png"
if [[ ! -s "$ICON_SOURCE" ]]; then
    echo "Отсутствует favicon.ico в Git checkout: $ICON_SOURCE" >&2
    exit 1
fi
for asset in "$APPLE_ICON_SOURCE"; do
    if [[ ! -s "$asset" ]]; then
        echo "Отсутствует иконка в Git checkout: $asset" >&2
        exit 1
    fi
done
if [[ ! -s "$LOGO_SOURCE" ]]; then
    echo "Отсутствует логотип в Git checkout: $LOGO_SOURCE" >&2
    exit 1
fi

if [[ "$WEBROOT" != "$(cd "$APP_DIR/public" && pwd -P)" ]]; then
    mkdir -p "$WEBROOT/build"
    cp -a "$APP_DIR/public/build/." "$WEBROOT/build/"

    # Hosting uses a separate public directory: publish brand assets too.
    mkdir -p "$WEBROOT/images"
    cp -a "$APP_DIR/public/images/." "$WEBROOT/images/"
    cp -a "$ICON_SOURCE" "$ICON_PUBLIC"
    cp -a "$APPLE_ICON_SOURCE" "$APPLE_ICON_PUBLIC"
fi

if [[ ! -s "$LOGO_PUBLIC" ]] || ! cmp -s "$LOGO_SOURCE" "$LOGO_PUBLIC"; then
    echo "Не удалось опубликовать логотип: $LOGO_PUBLIC" >&2
    exit 1
fi
if [[ ! -s "$ICON_PUBLIC" ]] || ! cmp -s "$ICON_SOURCE" "$ICON_PUBLIC"; then
    echo "Не удалось опубликовать favicon.ico: $ICON_PUBLIC" >&2
    exit 1
fi
if [[ ! -s "$APPLE_ICON_PUBLIC" ]] || ! cmp -s "$APPLE_ICON_SOURCE" "$APPLE_ICON_PUBLIC"; then
    echo "Не удалось опубликовать apple-touch-icon.png: $APPLE_ICON_PUBLIC" >&2
    exit 1
fi
echo "Опубликовано: $WEBROOT/build и $LOGO_PUBLIC", $ICON_PUBLIC

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

# A missing brand image should fail deployment, just like missing CSS/JS.
LOGO_URL="https://$DOMAIN/images/du2-logo.webp"
logo_status="$(curl -kLsS -o /dev/null -w '%{http_code}' --max-time 20 -H 'Cache-Control: no-cache' "$LOGO_URL")" || true
echo "HTTP $logo_status: $LOGO_URL"
if [[ "$logo_status" != 200 ]]; then
    echo "Логотип скопирован в $LOGO_PUBLIC, но URL возвращает HTTP $logo_status." >&2
    echo "Проверь фактический document root, правила nginx/Apache и кэш на стороне хостинга." >&2
    exit 1
fi

# Validate actual bytes served by the web server, not just HTTP 200.
# This catches stale Laravel icons from another document root or an intermediary cache.
verify_icon_response() {
    local path="$1"
    local expected="$2"
    local url="https://$DOMAIN/$path?v=20261122c"
    local downloaded
    downloaded="$(mktemp)"
    if ! curl -kLfsS --max-time 20 -H 'Cache-Control: no-cache' -o "$downloaded" "$url"; then
        rm -f "$downloaded"
        echo "Не удалось скачать иконку с сайта: $url" >&2
        return 1
    fi
    if ! cmp -s "$expected" "$downloaded"; then
        rm -f "$downloaded"
        echo "Иконка на сайте НЕ совпадает с репозиторием: $url" >&2
        echo "Проверь кеш хостинга/CDN и фактический document root: $WEBROOT" >&2
        return 1
    fi
    rm -f "$downloaded"
    echo "Фирменная иконка подтверждена: $url"
}
verify_icon_response "favicon.ico" "$ICON_SOURCE"
verify_icon_response "apple-touch-icon.png" "$APPLE_ICON_SOURCE"

echo "[7/8] Миграции"
php artisan migrate --force

echo "[8/8] Кэш Laravel"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

echo "[9/9] Настройка Laravel scheduler (раз в минуту)"
if ! command -v crontab >/dev/null 2>&1; then
    echo "Не найден crontab: необходимо настроить запуск php artisan schedule:run каждую минуту." >&2
    exit 1
fi
CRON_TAG="# du2-habits-scheduler"
existing_crontab="$(crontab -l 2>/dev/null || true)"
safe_app_dir="$(printf '%q' "$APP_DIR")"
php_bin="$(command -v php)"
mkdir -p "$APP_DIR/storage/logs"
cron_line="* * * * * cd $safe_app_dir && $php_bin artisan schedule:run >/dev/null 2>> $safe_app_dir/storage/logs/du2-scheduler-errors.log $CRON_TAG"

# Replace an older DU2 cron entry, but keep unrelated cron jobs unchanged.
if grep -Fq "$CRON_TAG" <<< "$existing_crontab"; then
    existing_crontab="$(printf '%s\n' "$existing_crontab" | grep -vF "$CRON_TAG" || true)"
fi
printf '%s\n%s\n' "$existing_crontab" "$cron_line" | crontab -
crontab -l | grep -F "$CRON_TAG"

echo "Проверка регистрации задачи Laravel:"
php artisan schedule:list | grep -F 'habits:send-reminders' || {
    echo "Laravel scheduler не содержит habits:send-reminders." >&2
    exit 1
}
echo "Диагностика последнего запуска:"
php artisan habits:reminders-status

echo "Релиз установлен: $(git rev-parse --short HEAD) · https://$DOMAIN"
