# Configuración de Google Maps API

## Pasos para configurar la API de Google Maps

### 1. Obtener API Key de Google Maps

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la **Geocoding API**:
   - Ve a "APIs & Services" > "Library"
   - Busca "Geocoding API"
   - Haz clic en "Enable"

4. Crea credenciales:
   - Ve a "APIs & Services" > "Credentials"
   - Haz clic en "Create Credentials" > "API Key"
   - Copia la API Key generada

### 2. Configurar en Laravel

1. Agrega la API Key al archivo `.env`:
```env
GOOGLE_MAPS_API_KEY=tu_api_key_aqui
```

2. Reinicia los contenedores de Docker:
```bash
docker-compose restart app
```

### 3. Probar la configuración

```bash
# Probar con una dirección específica
docker-compose exec app php artisan test:google-maps "Av. Corrientes 1234" "Buenos Aires"

# Probar con solo dirección
docker-compose exec app php artisan test:google-maps "Av. 9 de Julio 1000"
```

### 4. Funcionalidades implementadas

- **Geocodificación automática**: Las direcciones se convierten automáticamente a coordenadas
- **Cache**: Las coordenadas se cachean por 24 horas para mejorar el rendimiento
- **Manejo de errores**: Logs detallados si la API falla
- **Búsqueda en Argentina**: Configurado específicamente para direcciones argentinas

### 5. Campos agregados al JSON

#### Endpoint `/api/cadete/deliveries`:
```json
{
  "pickup_latitude": -34.6037,
  "pickup_longitude": -58.3816,
  "delivery_latitude": -34.6118,
  "delivery_longitude": -58.3960
}
```

#### Endpoint `/api/cadete/shipments`:
```json
{
  "origin": {
    "latitude": -34.6037,
    "longitude": -58.3816
  },
  "destination": {
    "latitude": -34.6118,
    "longitude": -58.3960
  }
}
```

### 6. Límites y consideraciones

- **Límites de API**: Google Maps tiene límites de requests por día
- **Cache**: Las coordenadas se cachean para reducir llamadas a la API
- **Fallback**: Si la API falla, los campos de coordenadas serán `null`
- **Costo**: La Geocoding API tiene costo por request (ver precios de Google)

### 7. Monitoreo

Los logs de geocodificación se guardan en `storage/logs/laravel.log` con los siguientes niveles:
- `warning`: API key no configurada o geocodificación fallida
- `error`: Errores de conexión o excepciones
