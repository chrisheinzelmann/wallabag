#!/usr/bin/env bash
# Create a wallabag backup (files + database dump) and keep only the newest N backups.
#
# Required environment variables:
#   APP_DIR=/path/to/wallabag
#   BACKUP_ROOT=/path/to/backups
# Optional environment variable:
#   KEEP_BACKUPS=2
#
# Example usage:
#   set -a; . ./backup.env; set +a; ./backup.sh

set -euo pipefail

APP_DIR="${APP_DIR:?APP_DIR is not set}"
BACKUP_ROOT="${BACKUP_ROOT:?BACKUP_ROOT is not set}"
KEEP_BACKUPS="${KEEP_BACKUPS:-2}"

if ! [[ "$KEEP_BACKUPS" =~ ^[0-9]+$ ]] || [ "$KEEP_BACKUPS" -lt 1 ]; then
    echo "KEEP_BACKUPS must be a positive integer."
    exit 1
fi

PARAMS_FILE="$APP_DIR/app/config/parameters.yml"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$BACKUP_ROOT/$TIMESTAMP"

if [ ! -d "$APP_DIR" ]; then
    echo "App directory not found: $APP_DIR"
    exit 1
fi

if [ ! -f "$PARAMS_FILE" ]; then
    echo "Missing parameters file: $PARAMS_FILE"
    exit 1
fi

mkdir -p "$BACKUP_DIR"

echo "[1/3] Creating filesystem backup..."
tar \
    --exclude='.git' \
    --exclude='var/cache/*' \
    --exclude='var/logs/*' \
    -czf "$BACKUP_DIR/files.tar.gz" \
    -C "$APP_DIR" \
    .

get_param() {
    local key="$1"

    php -r '
$file = $argv[1];
$key = $argv[2];
foreach (file($file) as $line) {
    if (preg_match("/^\\s*" . preg_quote($key, "/") . ":\\s*(.+?)\\s*$/", $line, $m)) {
        $value = trim($m[1]);
        $value = trim($value, "\"" . chr(39));
        if (strtolower($value) === "null") {
            echo "";
        } else {
            echo $value;
        }
        exit;
    }
}
' "$PARAMS_FILE" "$key"
}

echo "[2/3] Creating database dump..."
DB_DRIVER="$(get_param database_driver)"

case "$DB_DRIVER" in
    pdo_mysql)
        DB_HOST="$(get_param database_host)"
        DB_PORT="$(get_param database_port)"
        DB_SOCKET="$(get_param database_socket)"
        DB_NAME="$(get_param database_name)"
        DB_USER="$(get_param database_user)"
        DB_PASSWORD="$(get_param database_password)"

        DUMP_FILE="$BACKUP_DIR/db.sql"
        DUMP_CMD=(
            mysqldump
            --single-transaction
            --quick
            --default-character-set=utf8mb4
            --user="$DB_USER"
        )

        if [ -n "$DB_SOCKET" ]; then
            DUMP_CMD+=(--socket="$DB_SOCKET")
        else
            DUMP_CMD+=(--host="$DB_HOST")
            if [ -n "$DB_PORT" ]; then
                DUMP_CMD+=(--port="$DB_PORT")
            fi
        fi

        if [ -n "$DB_PASSWORD" ]; then
            DUMP_CMD+=(--password="$DB_PASSWORD")
        fi

        DUMP_CMD+=("$DB_NAME")

        "${DUMP_CMD[@]}" > "$DUMP_FILE"
        ;;
    sqlite|pdo_sqlite)
        DB_PATH="$(get_param database_path)"
        if [ -z "$DB_PATH" ]; then
            echo "database_path is empty in $PARAMS_FILE"
            exit 1
        fi

        if [ ! -f "$DB_PATH" ]; then
            echo "SQLite database not found: $DB_PATH"
            exit 1
        fi

        cp "$DB_PATH" "$BACKUP_DIR/db.sqlite"
        ;;
    *)
        echo "Unsupported database driver: $DB_DRIVER"
        exit 1
        ;;
esac

echo "[3/3] Applying retention (keep newest $KEEP_BACKUPS)..."

backup_dirs="$(ls -1dt "$BACKUP_ROOT"/* 2>/dev/null || true)"
if [ -n "$backup_dirs" ]; then
    index=0
    while IFS= read -r backup_dir; do
        [ -z "$backup_dir" ] && continue
        index=$((index + 1))

        if [ "$index" -gt "$KEEP_BACKUPS" ] && [ -d "$backup_dir" ]; then
            rm -rf -- "$backup_dir"
        fi
    done <<EOF
$backup_dirs
EOF
fi

echo "Backup complete: $BACKUP_DIR"
