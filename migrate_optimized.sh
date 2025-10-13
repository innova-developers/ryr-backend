#!/bin/bash

# Script optimizado para migración sin coordenadas
# Uso: ./migrate_optimized.sh

echo "🚀 Iniciando migración optimizada sin coordenadas..."

# Crear directorio de logs
mkdir -p logs

# Función para verificar memoria
check_memory() {
    echo "💾 Memoria disponible:"
    free -h
    echo ""
}

# Función para verificar progreso
check_progress() {
    echo "📊 Verificando progreso actual..."
    
    COMMISSIONS=$(php artisan tinker --execute="echo DB::table('commissions')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    LOCATIONS=$(php artisan tinker --execute="echo DB::table('locations')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    CUSTOMERS=$(php artisan tinker --execute="echo DB::table('customers')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    
    echo "   💼 Comisiones: ${COMMISSIONS:-0}"
    echo "   📍 Ubicaciones: ${LOCATIONS:-0}"
    echo "   👥 Clientes: ${CUSTOMERS:-0}"
    echo ""
}

# Función para migración por pasos
migrate_step_by_step() {
    echo "📦 Iniciando migración por pasos..."
    
    # Paso 1: Solo estructura básica (sin comisiones)
    echo "   📋 Paso 1: Migrando estructura básica..."
    nohup php artisan migrate:complete-system --skip-coordinates --commission-from=1 --commission-to=0 --batch-size=50 --locations-batch=100 > logs/step1_basic.log 2>&1 &
    
    echo "   ✅ Paso 1 iniciado en segundo plano"
    echo "   📋 Log: logs/step1_basic.log"
    echo "   💡 Espera a que termine antes de continuar con el paso 2"
}

# Función para migración de comisiones por rangos
migrate_commissions_by_ranges() {
    echo "💼 Iniciando migración de comisiones por rangos..."
    
    # Verificar si ya hay comisiones migradas
    COMMISSIONS=$(php artisan tinker --execute="echo DB::table('commissions')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    
    if [ "${COMMISSIONS:-0}" -gt 0 ]; then
        echo "   ⚠️  Ya hay ${COMMISSIONS} comisiones migradas"
        echo "   🔄 Usando modo resume para continuar..."
        nohup php artisan migrate:complete-system --resume --skip-coordinates --batch-size=50 > logs/commissions_resume.log 2>&1 &
    else
        echo "   📦 Migrando comisiones en rangos de 5000..."
        
        # Rango 1: 1-5000
        nohup php artisan migrate:complete-system --commission-from=1 --commission-to=5000 --skip-coordinates --batch-size=50 > logs/commissions_1-5000.log 2>&1 &
        
        echo "   ✅ Rango 1-5000 iniciado"
        echo "   📋 Log: logs/commissions_1-5000.log"
    fi
}

# Función para migración completa optimizada
migrate_full_optimized() {
    echo "🚀 Iniciando migración completa optimizada..."
    
    # Verificar memoria antes de empezar
    check_memory
    
    # Usar parámetros optimizados
    nohup php artisan migrate:complete-system \
        --skip-coordinates \
        --batch-size=50 \
        --locations-batch=100 \
        > logs/migration_optimized.log 2>&1 &
    
    echo "   ✅ Migración optimizada iniciada"
    echo "   📋 Log: logs/migration_optimized.log"
    echo "   📊 Parámetros: batch-size=50, locations-batch=100"
}

# Función para monitorear
monitor() {
    echo "👀 Monitoreando migración..."
    
    while true; do
        clear
        echo "🔄 Estado de la migración - $(date)"
        echo "=================================="
        
        # Verificar si está corriendo
        if pgrep -f "migrate:complete-system" > /dev/null; then
            echo "✅ Proceso corriendo (PID: $(pgrep -f "migrate:complete-system"))"
        else
            echo "❌ Proceso no está corriendo"
        fi
        
        echo ""
        check_progress
        
        # Mostrar últimas líneas del log
        echo "📋 Últimas líneas del log:"
        tail -5 logs/migration_optimized.log 2>/dev/null || echo "   No hay log disponible"
        
        echo ""
        echo "Presiona Ctrl+C para salir del monitoreo"
        sleep 10
    done
}

# Menú principal
case "$1" in
    "full")
        migrate_full_optimized
        ;;
    "step")
        migrate_step_by_step
        ;;
    "commissions")
        migrate_commissions_by_ranges
        ;;
    "monitor")
        monitor
        ;;
    "status")
        check_progress
        ;;
    *)
        echo "🔧 Script de Migración Optimizada"
        echo ""
        echo "Uso: $0 [comando]"
        echo ""
        echo "Comandos disponibles:"
        echo "  full        - Migración completa optimizada"
        echo "  step        - Migración por pasos"
        echo "  commissions - Solo migrar comisiones por rangos"
        echo "  monitor     - Monitorear en tiempo real"
        echo "  status      - Verificar progreso"
        echo ""
        echo "Recomendado para tu caso:"
        echo "  $0 full"
        echo ""
        ;;
esac
