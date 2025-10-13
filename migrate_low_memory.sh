#!/bin/bash

# Script para migración con límite de memoria bajo
# Uso: ./migrate_low_memory.sh

echo "🚀 Iniciando migración optimizada para memoria limitada..."

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

# Función para migración ultra-optimizada
migrate_ultra_optimized() {
    echo "🚀 Iniciando migración ultra-optimizada..."
    
    # Verificar memoria antes de empezar
    check_memory
    
    # Usar parámetros ultra-optimizados para memoria limitada
    nohup php artisan migrate:complete-system \
        --skip-coordinates \
        --batch-size=5 \
        --locations-batch=10 \
        > logs/migration_ultra_optimized.log 2>&1 &
    
    echo "   ✅ Migración ultra-optimizada iniciada"
    echo "   📋 Log: logs/migration_ultra_optimized.log"
    echo "   📊 Parámetros: batch-size=5, locations-batch=10"
    echo "   💡 Estos parámetros usan muy poca memoria"
}

# Función para migración por pasos ultra-pequeños
migrate_step_by_step_ultra() {
    echo "📦 Iniciando migración por pasos ultra-pequeños..."
    
    # Paso 1: Solo estructura básica
    echo "   📋 Paso 1: Migrando estructura básica..."
    nohup php artisan migrate:complete-system \
        --skip-coordinates \
        --commission-from=1 \
        --commission-to=0 \
        --batch-size=5 \
        --locations-batch=10 \
        > logs/step1_ultra.log 2>&1 &
    
    echo "   ✅ Paso 1 iniciado"
    echo "   📋 Log: logs/step1_ultra.log"
    echo "   💡 Espera a que termine antes de continuar"
}

# Función para migración de comisiones ultra-pequeña
migrate_commissions_ultra() {
    echo "💼 Iniciando migración de comisiones ultra-pequeña..."
    
    # Verificar si ya hay comisiones migradas
    COMMISSIONS=$(php artisan tinker --execute="echo DB::table('commissions')->count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    
    if [ "${COMMISSIONS:-0}" -gt 0 ]; then
        echo "   ⚠️  Ya hay ${COMMISSIONS} comisiones migradas"
        echo "   🔄 Usando modo resume para continuar..."
        nohup php artisan migrate:complete-system \
            --resume \
            --skip-coordinates \
            --batch-size=5 \
            > logs/commissions_resume_ultra.log 2>&1 &
    else
        echo "   📦 Migrando comisiones en rangos ultra-pequeños..."
        
        # Rango 1: 1-1000 (ultra-pequeño)
        nohup php artisan migrate:complete-system \
            --commission-from=1 \
            --commission-to=1000 \
            --skip-coordinates \
            --batch-size=5 \
            > logs/commissions_1-1000.log 2>&1 &
        
        echo "   ✅ Rango 1-1000 iniciado"
        echo "   📋 Log: logs/commissions_1-1000.log"
    fi
}

# Función para monitorear con información de memoria
monitor_with_memory() {
    echo "👀 Monitoreando migración con información de memoria..."
    
    while true; do
        clear
        echo "🔄 Estado de la migración - $(date)"
        echo "=================================="
        
        # Verificar memoria
        echo "💾 Memoria:"
        free -h | head -2
        
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
        tail -5 logs/migration_ultra_optimized.log 2>/dev/null || echo "   No hay log disponible"
        
        echo ""
        echo "Presiona Ctrl+C para salir del monitoreo"
        sleep 15
    done
}

# Función para aumentar límite de memoria temporalmente
increase_memory_limit() {
    echo "🔧 Intentando aumentar límite de memoria..."
    
    # Crear archivo php.ini temporal
    cat > php_memory.ini << EOF
memory_limit = 512M
max_execution_time = 0
max_input_time = -1
EOF
    
    echo "   📝 Archivo php_memory.ini creado"
    echo "   💡 Usa: php -c php_memory.ini artisan migrate:complete-system --skip-coordinates --batch-size=10 --locations-batch=20"
}

# Menú principal
case "$1" in
    "ultra")
        migrate_ultra_optimized
        ;;
    "step")
        migrate_step_by_step_ultra
        ;;
    "commissions")
        migrate_commissions_ultra
        ;;
    "monitor")
        monitor_with_memory
        ;;
    "memory")
        increase_memory_limit
        ;;
    "status")
        check_progress
        ;;
    *)
        echo "🔧 Script de Migración para Memoria Limitada"
        echo ""
        echo "Uso: $0 [comando]"
        echo ""
        echo "Comandos disponibles:"
        echo "  ultra       - Migración ultra-optimizada (batch-size=5)"
        echo "  step        - Migración por pasos ultra-pequeños"
        echo "  commissions - Solo migrar comisiones ultra-pequeña"
        echo "  monitor     - Monitorear con información de memoria"
        echo "  memory      - Crear archivo para aumentar límite de memoria"
        echo "  status      - Verificar progreso"
        echo ""
        echo "Recomendado para memoria limitada (256MB):"
        echo "  $0 ultra"
        echo ""
        echo "Si necesitas más memoria:"
        echo "  $0 memory"
        echo "  php -c php_memory.ini artisan migrate:complete-system --skip-coordinates --batch-size=10 --locations-batch=20"
        ;;
esac
