#!/usr/bin/env bash
set -Eeuo pipefail

REPO="${REPO:-/home/elivokwjs/repositories/example-app}"
WEB="${WEB:-/home/elivokwjs/public_html}"
PHP="${PHP:-/usr/local/bin/php}"
COMPOSER="${COMPOSER:-/opt/cpanel/composer/bin/composer}"
BACKUPS="${BACKUPS:-$HOME/backups/elio}"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"

cd "$REPO"
mkdir -p storage/logs
exec > >(tee -a storage/logs/deploy.log) 2>&1

STARTED=$(date +%s)
DOWN=0

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "ERROR: $*"
    exit 1
}

artisan() {
    "$PHP" artisan "$@" --no-interaction
}

finish() {
    local status=$?

    if [ "$DOWN" = 1 ]; then
        artisan up || true
    fi

    if [ "$status" = 0 ]; then
        log "Deploy finished in $(( $(date +%s) - STARTED ))s"
    else
        log "Deploy FAILED (exit $status). Database backups: $BACKUPS"
    fi
}
trap finish EXIT

env_value() {
    grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -E "s/^\"(.*)\"$/\1/; s/^'(.*)'$/\1/" || true
}

install_php_dependencies() {
    log "Installing PHP dependencies"
    "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress
}

find_npm() {
    if [ -n "${NPM:-}" ]; then
        echo "$NPM"
        return
    fi

    if command -v npm >/dev/null 2>&1; then
        command -v npm
        return
    fi

    local node_bin
    node_bin=$(ls -d /opt/cpanel/ea-nodejs*/bin 2>/dev/null | sort -V | tail -n1 || true)

    if [ -n "$node_bin" ] && [ -x "$node_bin/npm" ]; then
        echo "$node_bin/npm"
    fi
}

build_assets() {
    if git ls-files --error-unmatch public/build/manifest.json >/dev/null 2>&1; then
        log "Using the frontend build committed with $(git log -1 --format='%h' -- public/build)"
        return
    fi

    local npm
    npm=$(find_npm)

    if [ -n "$npm" ]; then
        log "Building frontend with $npm"
        export PATH="$(dirname "$npm"):$PATH"

        if [ -f package-lock.json ]; then
            "$npm" ci --no-audit --no-fund
        else
            "$npm" install --no-audit --no-fund
        fi

        "$npm" run build
        [ -f public/build/manifest.json ] || fail "npm run build did not create public/build/manifest.json"
    elif [ -f "$WEB/build/manifest.json" ]; then
        log "WARNING: Node.js not found on the server, keeping the build already in $WEB/build."
        log "WARNING: If CSS or JS changed, run 'npm run build' locally and upload public/build to $WEB/build."
    else
        fail "Node.js not found and $WEB/build is missing. Upload public/build manually or install Node.js in cPanel."
    fi
}

backup_database() {
    local connection database
    connection=$(env_value DB_CONNECTION)
    database=$(env_value DB_DATABASE)

    if [ "$connection" != "mysql" ] && [ "$connection" != "mariadb" ]; then
        log "WARNING: DB_CONNECTION is '$connection', skipping database backup"
        return
    fi

    command -v mysqldump >/dev/null 2>&1 || fail "mysqldump not found, refusing to migrate without a backup"

    mkdir -p "$BACKUPS"
    chmod 700 "$BACKUPS"

    local credentials file
    credentials=$(mktemp)
    chmod 600 "$credentials"
    {
        echo "[client]"
        echo "host=$(env_value DB_HOST)"
        echo "port=$(env_value DB_PORT)"
        echo "user=$(env_value DB_USERNAME)"
        printf 'password="%s"\n' "$(env_value DB_PASSWORD | sed 's/\\/\\\\/g; s/"/\\"/g')"
    } > "$credentials"

    file="$BACKUPS/$database-$(date +%Y%m%d-%H%M%S).sql.gz"
    log "Backing up database $database to $file"

    if ! mysqldump --defaults-extra-file="$credentials" --single-transaction --quick --routines --triggers --no-tablespaces "$database" | gzip > "$file.part"; then
        rm -f "$credentials" "$file.part"
        fail "Database backup failed, nothing was migrated"
    fi

    rm -f "$credentials"
    mv "$file.part" "$file"
    log "Backup size: $(du -h "$file" | cut -f1)"

    ls -1t "$BACKUPS"/*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -f
}

release() {
    log "Entering maintenance mode"
    artisan down --retry=15
    DOWN=1

    log "Running migrations"
    artisan migrate --force

    log "Refreshing caches"
    artisan config:clear
    artisan route:clear
    artisan cache:clear
    artisan config:cache
    artisan view:cache

    log "Publishing public files to $WEB"
    find public -mindepth 1 -maxdepth 1 ! -name storage -exec cp -R {} "$WEB/" \;

    artisan queue:restart

    artisan up
    DOWN=0
}

log "Deploying $(git log -1 --format='%h %s' 2>/dev/null || echo 'unknown commit')"
[ -f .env ] || fail ".env is missing in $REPO"

install_php_dependencies
build_assets
backup_database
release
