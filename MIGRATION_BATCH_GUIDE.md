# Guía de Migración por Lotes

Esta guía explica cómo usar el comando de migración por lotes para procesar grandes volúmenes de datos sin sobrecargar el sistema.

## Comando Principal

### Migración Completa con Lotes
```bash
php artisan migrate:complete-system [opciones]
```

**Opciones:**
- `--commission-from=1` - ID de comisión inicial (default: 1)
- `--commission-to=0` - ID de comisión final (0 = todas, default: 0)
- `--batch-size=100` - Tamaño del lote para comisiones (default: 100)
- `--locations-batch=10` - Tamaño del lote para ubicaciones (default: 10)
- `--skip-coordinates` - Omitir cálculo de coordenadas (migrar sin lat/lng)
- `--coordinates-only` - Solo calcular coordenadas para ubicaciones existentes
- `--dry-run` - Modo de prueba
- `--force` - Forzar sin confirmación
- `--skip-clear` - Omitir limpieza de DB

## Estrategias de Migración Recomendadas

### Estrategia 1: Migración Rápida (Sin Coordenadas)
```bash
# Migrar todo el sistema sin coordenadas (más rápido)
php artisan migrate:complete-system --skip-coordinates --locations-batch=50 --batch-size=100
```

### Estrategia 2: Migración con Coordenadas por Lotes
```bash
# Paso 1: Migrar estructura básica sin coordenadas
php artisan migrate:complete-system --skip-coordinates --locations-batch=50

# Paso 2: Calcular coordenadas por la noche (proceso lento)
php artisan migrate:complete-system --coordinates-only --locations-batch=5
```

### Estrategia 3: Migración de Comisiones por Rangos
```bash
# Migrar comisiones en lotes de 1000
php artisan migrate:complete-system --commission-from=1 --commission-to=1000 --batch-size=50 --skip-coordinates
php artisan migrate:complete-system --commission-from=1001 --commission-to=2000 --batch-size=50 --skip-coordinates
php artisan migrate:complete-system --commission-from=2001 --commission-to=3000 --batch-size=50 --skip-coordinates
# ... continuar hasta completar todas las comisiones
```

### Estrategia 4: Migración Híbrida (Recomendada)
```bash
# Paso 1: Migrar todo sin coordenadas (rápido)
php artisan migrate:complete-system --skip-coordinates --locations-batch=50 --batch-size=100

# Paso 2: Calcular coordenadas en horario nocturno
php artisan migrate:complete-system --coordinates-only --locations-batch=5
```

## Configuración de Producción

### Variables de Entorno Recomendadas
```env
# Google Maps API (para coordenadas)
GOOGLE_MAPS_API_KEY=tu_api_key_aqui

# Base de datos vieja
DB_OLD_HOST=localhost
DB_OLD_PORT=3306
DB_OLD_DATABASE=ryr_old
DB_OLD_USERNAME=usuario
DB_OLD_PASSWORD=password
DB_OLD_CHARSET=utf8mb4
DB_OLD_COLLATION=utf8mb4_unicode_ci
```

### Configuración de Lotes Recomendada

**Para Servidor con Poco Recursos:**
- `--batch-size=25` (comisiones)
- `--locations-batch=10` (ubicaciones)

**Para Servidor con Recursos Moderados:**
- `--batch-size=50` (comisiones)
- `--locations-batch=20` (ubicaciones)

**Para Servidor con Muchos Recursos:**
- `--batch-size=100` (comisiones)
- `--locations-batch=50` (ubicaciones)

## Monitoreo y Logs

### Verificar Progreso
```bash
# Ver comisiones migradas
php artisan tinker
>>> DB::table('commissions')->count()

# Ver ubicaciones con coordenadas
>>> DB::table('locations')->whereNotNull('latitude')->count()

# Ver ubicaciones sin coordenadas
>>> DB::table('locations')->whereNull('latitude')->count()
```

### Logs de Laravel
```bash
tail -f storage/logs/laravel.log
```

## Solución de Problemas

### Error: "Connection refused"
- Verificar configuración de `DB_OLD_*` en `.env`
- Verificar que la base de datos vieja esté accesible

### Error: "Google Maps API quota exceeded"
- Reducir `--batch-size` para ubicaciones
- Aumentar pausas entre requests
- Verificar límites de API en Google Cloud Console

### Error: "Memory limit exceeded"
- Reducir `--batch-size`
- Aumentar `memory_limit` en `php.ini`
- Usar `--dry-run` para probar primero

### Error: "Transaction timeout"
- Reducir `--batch-size`
- Aumentar `innodb_lock_wait_timeout` en MySQL
- Procesar en horarios de menor carga

## Ejemplos de Uso en Producción

### Migración Nocturna (Recomendada)
```bash
# Ejecutar durante la madrugada (2-6 AM)
nohup php artisan migrate:location-coordinates --from=1 --to=0 --batch-size=15 > migration.log 2>&1 &
```

### Migración por Horarios
```bash
# Mañana: Comisiones
php artisan migrate:commissions-batch --from=1 --to=2000 --batch-size=75

# Tarde: Más comisiones
php artisan migrate:commissions-batch --from=2001 --to=4000 --batch-size=75

# Noche: Coordenadas (proceso lento)
php artisan migrate:location-coordinates --from=1 --to=500 --batch-size=10
```

### Verificación Post-Migración
```bash
# Verificar integridad de datos
php artisan migrate:complete-system --dry-run

# Verificar conteos
php artisan tinker
>>> echo "Comisiones: " . DB::table('commissions')->count();
>>> echo "Ubicaciones: " . DB::table('locations')->count();
>>> echo "Con coordenadas: " . DB::table('locations')->whereNotNull('latitude')->count();
```

## Notas Importantes

1. **Siempre usar `--dry-run` primero** para verificar la configuración
2. **Las coordenadas requieren Google Maps API** - proceso lento y con límites
3. **Procesar en horarios de baja demanda** para evitar interrupciones
4. **Monitorear logs** para detectar errores temprano
5. **Hacer backups** antes de migraciones masivas
6. **Usar `nohup` y `&`** para procesos largos en producción
