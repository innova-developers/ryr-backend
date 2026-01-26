# Schedule Normalizer Service

Servicio para normalizar y parsear horarios comerciales desde strings con formatos libres.

## Configuración de IA (Opcional)

Para usar el parseo con IA como fallback, necesitas configurar una API key de Groq:

### 1. Obtener API Key de Groq (Gratis)

1. Registrarse en https://console.groq.com/
2. Ir a "API Keys" en el dashboard
3. Crear una nueva API key
4. Copiar la key

### 2. Configurar en Laravel

Agregar al archivo `.env`:

```env
GROQ_API_KEY=tu_api_key_aqui
```

### 3. Usar el servicio con IA

```php
use App\Services\ScheduleNormalizer\ScheduleNormalizerService;
use App\Services\ScheduleNormalizer\GroqAIClient;

$aiClient = new GroqAIClient();
$service = new ScheduleNormalizerService($aiClient);

$result = $service->normalize('Abre 10hs cierra tipo 8 de la noche');
```

## Uso Básico

```php
use App\Services\ScheduleNormalizer\ScheduleNormalizerService;

$service = new ScheduleNormalizerService();

// Parseo con regex (sin IA)
$result = $service->normalize('8 a 12 y 15 a 20');

// Resultado:
// [
//   'ranges' => [
//     ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
//     ['start' => '15:00', 'end' => '20:00', 'confidence' => 'high']
//   ],
//   'raw' => '8 a 12 y 15 a 20',
//   'normalized_at' => '2025-01-15T10:30:00.000000Z'
// ]
```

## Verificar si cierra pronto

```php
$ranges = [
    ['start' => '08:00', 'end' => '20:00', 'confidence' => 'high']
];

// Verificar si cierra en los próximos 15 minutos
$closesSoon = $service->closesSoon($ranges, 15);
```

## Formatos Soportados

### Parseo con Regex (confidence: high)

- `8 a 12`
- `8-12`
- `8a12`
- `08:30-12:00`
- `8 a 12 y 15 a 20`
- `8 a 12 / 15 a 20`
- `8:00-12:00, 15:00-20:00`

### Parseo con IA (confidence: medium/low)

- `Abre 10hs cierra tipo 8 de la noche`
- `8 de la mañana a 12 del mediodía`
- `Desde las 9 hasta las 18`
- `Mañana 8 a 12, tarde 15 a 20`
- `8am a 12pm y 3pm a 8pm`

## Tests

### Ejecutar todos los tests

```bash
php artisan test tests/Unit/Services/ScheduleNormalizerServiceTest.php
php artisan test tests/Unit/Services/ScheduleNormalizerComprehensiveTest.php
```

### Ejecutar tests con IA (requiere API key)

```bash
php artisan test tests/Unit/Services/ScheduleNormalizerWithAITest.php
```

## Notas Importantes

- **NO llamar a IA en cronjobs**: Los horarios deben pre-procesarse antes de ejecutar cronjobs
- El servicio primero intenta regex, solo usa IA si regex falla
- Los resultados son serializables a JSON para persistir en BD
- El método `closesSoon()` usa la hora actual del servidor


