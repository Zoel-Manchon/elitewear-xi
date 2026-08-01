$ErrorActionPreference = "Stop"

Set-Location (Split-Path -Parent $PSScriptRoot)

Write-Host "==> Limpiando cachés"
docker compose exec app php artisan optimize:clear

Write-Host "==> Composer"
docker compose exec app composer validate --strict --no-check-publish
docker compose exec app composer audit --locked --no-interaction

Write-Host "==> Larastan"
docker compose exec app vendor/bin/phpstan clear-result-cache
docker compose exec app vendor/bin/phpstan analyse --no-progress --memory-limit=1G

Write-Host "==> Pint"
docker compose exec app vendor/bin/pint --test

Write-Host "==> Tests aislados del entorno local"
docker compose exec `
    -e APP_ENV=testing `
    -e APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY= `
    app php artisan test --colors=always

Write-Host "==> Frontend"
npm ci
npm run build
npm audit --audit-level=high

Write-Host "==> Nginx"
docker compose exec web nginx -t

Write-Host ""
Write-Host "Todos los controles han finalizado correctamente."
