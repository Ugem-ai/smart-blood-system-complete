#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run as root: sudo ./deployment/scripts/setup-database.sh <db_name> <db_user> <db_password>"
    exit 1
fi

DB_NAME="${1:-smartblood}"
DB_USER="${2:-smartblood_user}"
DB_PASSWORD="${3:-change-me}"

if [[ "${DB_PASSWORD}" == "change-me" ]]; then
    echo "Provide a strong password as the third argument."
    exit 1
fi

echo "Creating database and least-privileged application user"
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "Database setup completed for ${DB_NAME}."
