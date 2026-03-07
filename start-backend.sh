#!/bin/bash

echo "Iniciando Backend Control Volumetrico API..."

# Verificar si PHP está instalado
if ! command -v php &> /dev/null; then
    echo "Error: PHP no está instalado. Instala PHP 8.1 o superior."
    exit 1
fi

# Verificar si Composer está instalado
if ! command -v composer &> /dev/null; then
    echo "Error: Composer no está instalado."
    exit 1
fi

# Instalar dependencias si no existen
if [ ! -d "vendor" ]; then
    echo "Instalando dependencias de Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generar key si no existe
if [ ! -f ".env" ]; then
    echo "Copiando archivo .env..."
    cp .env.example .env
    php artisan key:generate
fi

# Ejecutar migraciones
echo "Ejecutando migraciones..."
php artisan migrate --force

# Limpiar cache
echo "Limpiando cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Iniciar servidor
echo "Iniciando servidor en http://localhost:8000"
php artisan serve --host=0.0.0.0 --port=8000