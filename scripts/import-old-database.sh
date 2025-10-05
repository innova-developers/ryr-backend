#!/bin/bash

# Script para importar la base de datos del sistema viejo
# Uso: ./scripts/import-old-database.sh [archivo_dump.sql]

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para mostrar mensajes
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar si se proporcionó el archivo de dump
if [ $# -eq 0 ]; then
    log_error "Debes proporcionar el archivo de dump de la base de datos vieja"
    echo "Uso: $0 <archivo_dump.sql>"
    echo "Ejemplo: $0 ryr_old_dump.sql"
    exit 1
fi

DUMP_FILE=$1

# Verificar que el archivo existe
if [ ! -f "$DUMP_FILE" ]; then
    log_error "El archivo $DUMP_FILE no existe"
    exit 1
fi

log_info "Iniciando importación de la base de datos vieja..."

# Leer variables de entorno
DB_OLD_HOST=${DB_OLD_HOST:-127.0.0.1}
DB_OLD_PORT=${DB_OLD_PORT:-3306}
DB_OLD_DATABASE=${DB_OLD_DATABASE:-ryr_old}
DB_OLD_USERNAME=${DB_OLD_USERNAME:-root}
DB_OLD_PASSWORD=${DB_OLD_PASSWORD:-}

log_info "Configuración:"
log_info "  Host: $DB_OLD_HOST"
log_info "  Puerto: $DB_OLD_PORT"
log_info "  Base de datos: $DB_OLD_DATABASE"
log_info "  Usuario: $DB_OLD_USERNAME"
log_info "  Archivo: $DUMP_FILE"

# Confirmar antes de proceder
read -p "¿Continuar con la importación? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_info "Importación cancelada"
    exit 0
fi

# Crear la base de datos si no existe
log_info "Creando base de datos $DB_OLD_DATABASE..."
mysql -h "$DB_OLD_HOST" -P "$DB_OLD_PORT" -u "$DB_OLD_USERNAME" -p"$DB_OLD_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_OLD_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar el dump
log_info "Importando datos desde $DUMP_FILE..."
mysql -h "$DB_OLD_HOST" -P "$DB_OLD_PORT" -u "$DB_OLD_USERNAME" -p"$DB_OLD_PASSWORD" "$DB_OLD_DATABASE" < "$DUMP_FILE"

log_info "✅ Importación completada exitosamente!"

# Mostrar estadísticas básicas
log_info "Verificando datos importados..."
mysql -h "$DB_OLD_HOST" -P "$DB_OLD_PORT" -u "$DB_OLD_USERNAME" -p"$DB_OLD_PASSWORD" "$DB_OLD_DATABASE" -e "
SELECT 
    'Tablas importadas:' as info,
    COUNT(*) as total_tables
FROM information_schema.tables 
WHERE table_schema = '$DB_OLD_DATABASE';

SELECT 
    table_name as tabla,
    table_rows as registros
FROM information_schema.tables 
WHERE table_schema = '$DB_OLD_DATABASE' 
AND table_rows > 0
ORDER BY table_rows DESC;
"

log_info "Ahora puedes ejecutar la migración con:"
log_info "  php artisan migrate:old-database --dry-run  # Modo de prueba"
log_info "  php artisan migrate:old-database            # Migración real"
