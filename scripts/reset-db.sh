#!/bin/bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"

cd "$repo_root"

echo "WARNING: This will destroy all data and restore the initial seeded state."
read -r -p "Are you sure? (y/N) " confirm
if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Aborted."
    exit 0
fi

echo "Dropping and recreating database..."
docker compose exec mysql mysql -u root -proot_secret -e "DROP DATABASE IF EXISTS jutform; CREATE DATABASE jutform;"
echo "Running schema..."
docker compose exec -T mysql mysql -u root -proot_secret jutform < docker/mysql/init/00-schema.sql
echo "Seeding data..."
docker compose run --rm seeder
echo "Applying migrations..."
"$script_dir/migrate.sh"
echo "Done. Database reset complete."
