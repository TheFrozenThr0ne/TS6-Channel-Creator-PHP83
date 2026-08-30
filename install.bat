@echo off
php -r "if (version_compare(PHP_VERSION, '8.3.0', '<')) { echo 'PHP 8.3+ erforderlich.'; exit(1); }"
composer install --no-dev --optimize-autoloader
if errorlevel 1 exit /b 1
echo Installation abgeschlossen.
