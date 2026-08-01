$ErrorActionPreference = "Stop"

Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
}

Write-Host "==> Limpiando artefactos locales"
Remove-Item "public/hot" -Force -ErrorAction SilentlyContinue
Remove-Item "storage/logs/*.log" -Force -ErrorAction SilentlyContinue

Write-Host "==> Instalando y compilando el frontend"
npm ci
npm run build

Write-Host "==> Eliminando contenedores y el volumen MySQL anterior"
docker compose down -v --remove-orphans

Write-Host "==> Reconstruyendo las imágenes PHP"
docker compose build --no-cache app queue

Write-Host "==> Levantando base de datos, Redis y aplicación"
docker compose up -d mysql redis app web

Write-Host "==> Generando APP_KEY"
docker compose exec app php artisan key:generate --force

Write-Host "==> Creando y poblando la base de datos"
docker compose exec app php artisan migrate:fresh --seed --force

Write-Host "==> Limpiando cachés de Laravel"
docker compose exec app php artisan optimize:clear

Write-Host "==> Arrancando el worker después de las migraciones"
docker compose up -d queue

Write-Host "==> Verificando Nginx y catálogo"
docker compose exec web nginx -t
docker compose exec app php artisan catalog:doctor

Write-Host ""
Write-Host "Entorno listo: http://localhost:8000"
Write-Host "Admin: admin@retroshop.test / password"
