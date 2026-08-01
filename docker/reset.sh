#!/usr/bin/env sh
# Reconstruye el entorno desde cero. Destruye la base de datos.
set -eu

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    cp .env.example .env
fi

echo "==> Limpiando artefactos locales"
rm -f public/hot storage/logs/*.log

echo "==> Instalando y compilando el frontend"
npm ci
npm run build

echo "==> Parando y borrando contenedores y volúmenes"
docker compose down -v --remove-orphans

echo "==> Construyendo las imágenes PHP sin caché"
docker compose build --no-cache app queue

echo "==> Levantando base de datos, Redis y aplicación"
docker compose up -d mysql redis app web

echo "==> Preparando la aplicación"
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan migrate:fresh --seed --force
docker compose exec -T app php artisan optimize:clear

echo "==> Arrancando la cola después de las migraciones"
docker compose up -d queue

echo "==> Verificando"
docker compose exec -T web nginx -t
docker compose exec -T app php artisan catalog:doctor

echo
echo "Listo: http://localhost:8000"
echo "Admin: admin@retroshop.test / password"
