#!/bin/bash

# Script para ejecutar migración en segundo plano
# Uso: ./migrate_background.sh

echo "🚀 Iniciando migración en segundo plano..."

# Crear directorio de logs si no existe
mkdir -p logs

# Función para verificar si el proceso está corriendo
check_process() {
    if pgrep -f "migrate:complete-system" > /dev/null; then
        echo "✅ Proceso de migración está corriendo (PID: $(pgrep -f "migrate:complete-system"))"
        return 0
    else
        echo "❌ Proceso de migración no está corriendo"
        return 1
    fi
}

# Función para mostrar logs
show_logs() {
    echo "📋 Últimas 20 líneas del log:"
    tail -20 logs/migration.log
}

# Función para detener migración
stop_migration() {
    echo "🛑 Deteniendo migración..."
    pkill -f "migrate:complete-system"
    echo "✅ Migración detenida"
}

# Función para verificar progreso
check_progress() {
    echo "📊 Verificando progreso..."
    
    # Contar comisiones migradas
    COMMISSIONS_COUNT=$(php artisan tinker --execute="echo DB::table('commissions')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    echo "   💼 Comisiones migradas: ${COMMISSIONS_COUNT:-0}"
    
    # Contar ubicaciones migradas
    LOCATIONS_COUNT=$(php artisan tinker --execute="echo DB::table('locations')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    echo "   📍 Ubicaciones migradas: ${LOCATIONS_COUNT:-0}"
    
    # Contar clientes migrados
    CUSTOMERS_COUNT=$(php artisan tinker --execute="echo DB::table('customers')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    echo "   👥 Clientes migrados: ${CUSTOMERS_COUNT:-0}"
}

# Función para continuar migración
resume_migration() {
    echo "🔄 Continuando migración desde el último ID procesado..."
    nohup php artisan migrate:complete-system --resume --batch-size=25 --locations-batch=5 --skip-coordinates > logs/migration_resume.log 2>&1 &
    echo "✅ Migración reanudada en segundo plano"
    echo "📋 Log: logs/migration_resume.log"
}

# Función para migración por rangos
migrate_by_ranges() {
    echo "📦 Iniciando migración por rangos..."
    
    # Rango 1: 1-5000
    echo "   📦 Migrando comisiones 1-5000..."
    nohup php artisan migrate:complete-system --commission-from=1 --commission-to=5000 --batch-size=25 --skip-coordinates > logs/migration_1-5000.log 2>&1 &
    
    echo "✅ Migración por rangos iniciada"
    echo "📋 Logs: logs/migration_1-5000.log"
    echo "💡 Usa 'check_progress' para verificar el progreso"
}

# Función para migración completa
migrate_full() {
    echo "🚀 Iniciando migración completa..."
    nohup php artisan migrate:complete-system --batch-size=25 --locations-batch=5 --skip-coordinates > logs/migration_full.log 2>&1 &
    echo "✅ Migración completa iniciada en segundo plano"
    echo "📋 Log: logs/migration_full.log"
}

# Función para calcular coordenadas
migrate_coordinates() {
    echo "📍 Iniciando cálculo de coordenadas..."
    nohup php artisan migrate:complete-system --coordinates-only --locations-batch=3 > logs/migration_coordinates.log 2>&1 &
    echo "✅ Cálculo de coordenadas iniciado en segundo plano"
    echo "📋 Log: logs/migration_coordinates.log"
}

# Menú principal
case "$1" in
    "start")
        migrate_full
        ;;
    "resume")
        resume_migration
        ;;
    "ranges")
        migrate_by_ranges
        ;;
    "coordinates")
        migrate_coordinates
        ;;
    "status")
        check_process
        show_logs
        check_progress
        ;;
    "stop")
        stop_migration
        ;;
    "logs")
        show_logs
        ;;
    "progress")
        check_progress
        ;;
    *)
        echo "🔧 Script de Migración en Segundo Plano"
        echo ""
        echo "Uso: $0 [comando]"
        echo ""
        echo "Comandos disponibles:"
        echo "  start       - Iniciar migración completa"
        echo "  resume      - Continuar migración desde el último ID"
        echo "  ranges      - Migrar por rangos (1-5000, 5001-10000, etc.)"
        echo "  coordinates - Solo calcular coordenadas"
        echo "  status      - Verificar estado y progreso"
        echo "  stop        - Detener migración"
        echo "  logs        - Mostrar logs"
        echo "  progress    - Verificar progreso"
        echo ""
        echo "Ejemplos:"
        echo "  $0 start"
        echo "  $0 status"
        echo "  $0 stop"
        ;;
esac
