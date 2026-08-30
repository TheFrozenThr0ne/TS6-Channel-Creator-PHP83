#!/usr/bin/env sh
set -eu
command -v php >/dev/null 2>&1 || { echo "PHP fehlt."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Composer fehlt. Installiere Composer und starte dieses Skript erneut."; exit 1; }

php -r 'if (version_compare(PHP_VERSION, "8.3.0", "<")) { fwrite(STDERR, "PHP 8.3+ erforderlich.\n"); exit(1); }'
composer install --no-dev --optimize-autoloader
echo "Installation abgeschlossen."
