
<p align="center">
<img src="public/logo.jpeg" width="60%" style="padding:50px;border-radius:15px;" alt="Innova Logo" /></p>


<p style="font-size:3em;" align="center">
  🚚 RyR Comisiones <br>
  <img src="https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel" />
  <img src="https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker" />
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL" />
</p>

<p align="center">
 
</p>


## 🌟 Características

- 🐳 Dockerizado con PHP, MySQL y phpMyAdmin  
- 🔐 Autenticación con Sanctum  
- 🏗️ Arquitectura hexagonal  
- 🔍 PHPStan para análisis estático  
- ✨ PHP-CS-Fixer para formateo de código  
- ⚙️ GitHub Actions para CI  
- 🔄 Pre-push hooks para verificación de código  
- 🧪 Tests automatizados incluidos  

## 📋 Requisitos

- Docker 🐳  
- Docker Compose 🐙  
- PHP 8.2 🐘  
- Composer 📦  

## 🛠️ Instalación

### 1. Clonar el Proyecto

- Clona el repositorio:

git clone https://github.com/innova-developers/ryr-back.git
cd ryr-back

### 2. ⚙️ Configuración Inicial

- Copia el archivo `.env.example` a `.env`:
cp .env.example .env

- Inicia los contenedores de Docker:
docker-compose up -d --build

- Instala las dependencias de Composer:
composer install

- Genera la clave de aplicación:
php artisan key:generate

- Ejecuta las migraciones:
php artisan migrate

- Ejecuta los tests para verificar que todo funciona:
php artisan test

### 3.🔍 Análisis de Código
#### PHPStan : composer analyse
#### PHP-CS-Fixer composer format

# 📶 EndPoints 
## 🔐 Autenticación

### POST `/api/login`
Inicia sesión y devuelve un token de acceso.

**Parámetros:**
- `email` (string, requerido)
- `password` (string, requerido)

**Respuesta exitosa:**
```json
{
  "success": true,
  "token": "TOKEN_GENERADO",
  "user": {
    "id": 1,
    "name": "Nombre",
    "email": "usuario@ejemplo.com",
    "role": "admin"
  }
}
```

### POST `/api/logout`
Cierra la sesión del usuario autenticado y revoca el token.

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Sesión cerrada correctamente"
}
```

## 👤 Usuarios

### POST `/api/users`
Crea un nuevo usuario con los datos proporcionados.

### Payload de ejemplo

```json
{
  "name": "Juan Pérez",
  "email": "nuevo@ejemplo.com",
  "password": "passwordseguro",
  "role": "administrador"
}
```
### Respuesta exitosa

```json
{
  "success": true,
  "message": "Usuario creado correctamente",
  "user": {
      "id": 1,
      "name": "Juan Pérez",
      "email": "nuevo@ejemplo.com",
      "role": "administrador"
    }
}
```

### Respuesta Erronea 

```json
{
  "success": false,
    "message": "Datos inválidos: The name field is required., The email field must be a valid email address., The password field is required., The selected role is invalid."
}
```

### Validaciones

- **name**: requerido, string, máximo 255 caracteres
- **email**: requerido, email válido, único, máximo 255 caracteres
- **password**: requerido, string, mínimo 8 caracteres
- **role**: requerido, string, uno de: `administrador`, `mostrador`,`cadete`,`cliente`

### GET `/api/users`
Devuelve la lista de usuarios registrados (requiere autenticación).

##### Respuesta exitosa
```json
[
  {
    "id": 1,
    "name": "Juan",
    "email": "juan@example.com",
    "role": "admin",
  },
  ...
]
```

### DELETE `/api/users/{id}`
Elimina un usuario por su ID (requiere autenticación y rol de administrador).

#### Parámetros de ruta:
- `id` (integer, requerido): ID del usuario a eliminar.

##### Respuesta exitosa
```json
[
    {
        "id": 1,
        "name": "Juan",
        "email": "juan@example.com",
        "role": "admin",
    },
    ...
]
```

### PUT `/api/users/{id}`
Actualiza los datos de un usuario existente (requiere autenticación y rol de administrador).
#### Payload de ejemplo

```json
{
    "name": "Juan Editado",
    "email": "editado@ejemplo.com",
    "password": "nuevacontraseña",
    "role": "mostrador"
} 
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID del usuario a eliminar.

##### Respuesta exitosa
```json
[
    {
        "id": 1,
        "name": "Juan",
        "email": "juan@example.com",
        "role": "admin"
    }
]
```


## 👤 Sucursales

### POST `/api/branches`
Crea una nueva sucursal con los datos proporcionados.

### Payload de ejemplo

```json
{
  "name": "Sucursal Moreno",
  "address": "Calle 1234",
  "schedule": "8 a 21hs",
  "phone": "2917493992"
}
```
### Respuesta exitosa

```json
{
  "success": true,
  "message": "sucursal creada correctamente",
}
```

### Respuesta Erronea

```json
{
  "success": false,
    "message": "Datos inválidos: The name field is required., The email field must be a valid email address., The password field is required., The selected role is invalid."
}
```
### GET `/api/branches`
Devuelve la lista de sucursales (requiere autenticación).

##### Respuesta exitosa
```json
[
  {
    "id": 1,
    "name": "Sucursal Moreno",
    "address": "Calle 1234",
    "schedule": "8 a 21hs",
      "phone": "2917493992",
  },
  ...
]
```

### DELETE `/api/branches/{id}`
Elimina una sucursal por su ID (requiere autenticación y rol de administrador).

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la sucursal a eliminar.

##### Respuesta exitosa
```json
[
    "success" => "true"
]
```

### PUT `/api/branches/{id}`
Actualiza los datos de una sucursal existente (requiere autenticación y rol de administrador).
#### Payload de ejemplo

```json
{
    "name": "Sucursal Moreno",
    "address": "Calle 1234",
    "schedule": "8 a 21hs",
    "phone": "2917493992"
} 
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la sucursal a actualizar.

##### Respuesta exitosa
```json
[
    {
        "id": 1,
        "name": "Sucursal Moreno",
        "address": "Calle 1234",
        "schedule": "8 a 21hs",
        "phone": "2917493992",
    }
]
```

## 👤 Clientes

### POST `/api/customers`
Crea un nuevo cliente con los datos proporcionados.

#### Payload de ejemplo
```json
{
  "dni": 12345678,
  "name": "Juan",
  "last_name": "Pérez",
  "mobile": "2917493992",
  "email": "cliente@ejemplo.com",
  "address": "Calle 123",
  "city": "Bahía Blanca",
  "phone": "2917493992",
  "maps_url": "https://maps.google.com",
  "business_hours": "9 a 18hs",
  "observations": "Cliente frecuente",
  "is_premium": false,
  "branch_id": 1
}
```

#### Respuesta exitosa
```json
{
  "id": 1,
  "dni": 12345678,
  "name": "Juan",
  "last_name": "Pérez",
  "mobile": "2917493992",
  "email": "cliente@ejemplo.com",
  "address": "Calle 123",
  "city": "Bahía Blanca",
  "phone": "2917493992",
  "maps_url": "https://maps.google.com",
  "business_hours": "9 a 18hs",
  "observations": "Cliente frecuente",
  "is_premium": false,
  "user_id": 1,
  "branch_id": 1,
  "created_at": "2024-03-21T12:00:00.000000Z",
  "updated_at": "2024-03-21T12:00:00.000000Z"
}
```

#### Validaciones
- **dni**: requerido, número entero, único
- **name**: requerido, string, máximo 255 caracteres
- **last_name**: requerido, string, máximo 255 caracteres
- **mobile**: requerido, string, máximo 255 caracteres
- **email**: requerido, email válido, único, máximo 255 caracteres
- **address**: requerido, string, máximo 255 caracteres
- **city**: requerido, string, máximo 255 caracteres
- **phone**: requerido, string, máximo 255 caracteres
- **maps_url**: opcional, string, máximo 255 caracteres
- **business_hours**: opcional, string, máximo 255 caracteres
- **observations**: opcional, string
- **is_premium**: opcional, booleano
- **branch_id**: requerido, número entero, debe existir en la tabla branches

### GET `/api/customers`
Devuelve la lista de clientes registrados (requiere autenticación).

#### Respuesta exitosa
```json
[
  {
    "id": 1,
    "dni": 12345678,
    "name": "Juan",
    "last_name": "Pérez",
    "mobile": "2917493992",
    "email": "cliente@ejemplo.com",
    "address": "Calle 123",
    "city": "Bahía Blanca",
    "phone": "2917493992",
    "maps_url": "https://maps.google.com",
    "business_hours": "9 a 18hs",
    "observations": "Cliente frecuente",
    "is_premium": false,
    "user_id": 1,
    "branch_id": 1,
    "created_at": "2024-03-21T12:00:00.000000Z",
    "updated_at": "2024-03-21T12:00:00.000000Z"
  }
]
```

### GET `/api/customers/{id}`
Obtiene los detalles de un cliente específico por su ID.

#### Parámetros de ruta:
- `id` (integer, requerido): ID del cliente a consultar.

#### Respuesta exitosa
```json
{
  "id": 1,
  "dni": 12345678,
  "name": "Juan",
  "last_name": "Pérez",
  "mobile": "2917493992",
  "email": "cliente@ejemplo.com",
  "address": "Calle 123",
  "city": "Bahía Blanca",
  "phone": "2917493992",
  "maps_url": "https://maps.google.com",
  "business_hours": "9 a 18hs",
  "observations": "Cliente frecuente",
  "is_premium": false,
  "user_id": 1,
  "branch_id": 1,
  "created_at": "2024-03-21T12:00:00.000000Z",
  "updated_at": "2024-03-21T12:00:00.000000Z"
}
```

#### Respuesta de error (404)
```json
{
  "message": "Customer not found"
}
```

### PUT `/api/customers/{id}`
Actualiza los datos de un cliente existente.

#### Parámetros de ruta:
- `id` (integer, requerido): ID del cliente a actualizar.

#### Payload de ejemplo
```json
{
  "dni": 12345678,
  "name": "Juan",
  "last_name": "Pérez",
  "mobile": "2917493992",
  "email": "cliente@ejemplo.com",
  "address": "Calle 123",
  "city": "Bahía Blanca",
  "phone": "2917493992",
  "maps_url": "https://maps.google.com",
  "business_hours": "9 a 18hs",
  "observations": "Cliente frecuente",
  "is_premium": false,
  "branch_id": 1
}
```

#### Respuesta exitosa
```json
{
  "id": 1,
  "dni": 12345678,
  "name": "Juan",
  "last_name": "Pérez",
  "mobile": "2917493992",
  "email": "cliente@ejemplo.com",
  "address": "Calle 123",
  "city": "Bahía Blanca",
  "phone": "2917493992",
  "maps_url": "https://maps.google.com",
  "business_hours": "9 a 18hs",
  "observations": "Cliente frecuente",
  "is_premium": false,
  "user_id": 1,
  "branch_id": 1,
  "created_at": "2024-03-21T12:00:00.000000Z",
  "updated_at": "2024-03-21T12:00:00.000000Z"
}
```

#### Respuesta de error (404)
```json
{
  "message": "Customer not found"
}
```

### DELETE `/api/customers/{id}`
Elimina un cliente por su ID.

#### Parámetros de ruta:
- `id` (integer, requerido): ID del cliente a eliminar.

#### Respuesta exitosa
```json
null
```
Status: 204 No Content

#### Respuesta de error (404)
```json
{
  "message": "Customer not found"
}
```

## 🚚 Destinos

El módulo de destinos permite gestionar las rutas y precios de transporte entre diferentes ciudades.

### Listar todos los destinos
```http
GET /api/destinations
```

#### Respuesta
```json
[
    {
        "id": 1,
        "origin": "Buenos Aires",
        "destination": "Córdoba",
        "fixed_price": 1500.00,
        "small_bulk_price": 1200.00,
        "large_bulk_price": 900.00,
        "created_at": "2024-03-21T10:00:00.000000Z",
        "updated_at": "2024-03-21T10:00:00.000000Z"
    }
]
```

### Crear un nuevo destino
```http
POST /api/destinations
```

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| origin | string | Sí | Ciudad de origen |
| destination | string | Sí | Ciudad de destino |
| fixed_price | float | Sí | Precio fijo del transporte |
| small_bulk_price | float | Sí | Precio por bulto chico (aproximadamente 20% menos que el fijo) |
| large_bulk_price | float | Sí | Precio por bulto grande (aproximadamente 40% menos que el fijo) |

#### Ejemplo de solicitud
```json
{
    "origin": "Buenos Aires",
    "destination": "Córdoba",
    "fixed_price": 1500.00,
    "small_bulk_price": 1200.00,
    "large_bulk_price": 900.00
}
```

#### Respuesta
```json
{
    "id": 1,
    "origin": "Buenos Aires",
    "destination": "Córdoba",
    "fixed_price": 1500.00,
    "small_bulk_price": 1200.00,
    "large_bulk_price": 900.00,
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T10:00:00.000000Z"
}
```

### Obtener un destino específico
```http
GET /api/destinations/{id}
```

#### Respuesta
```json
{
    "id": 1,
    "origin": "Buenos Aires",
    "destination": "Córdoba",
    "fixed_price": 1500.00,
    "small_bulk_price": 1200.00,
    "large_bulk_price": 900.00,
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T10:00:00.000000Z"
}
```

### Actualizar un destino
```http
PUT /api/destinations/{id}
```

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| origin | string | No | Ciudad de origen |
| destination | string | No | Ciudad de destino |
| fixed_price | float | No | Precio fijo del transporte |
| small_bulk_price | float | No | Precio por bulto chico |
| large_bulk_price | float | No | Precio por bulto grande |

#### Ejemplo de solicitud
```json
{
    "fixed_price": 1600.00,
    "small_bulk_price": 1280.00,
    "large_bulk_price": 960.00
}
```

#### Respuesta
```json
{
    "id": 1,
    "origin": "Buenos Aires",
    "destination": "Córdoba",
    "fixed_price": 1600.00,
    "small_bulk_price": 1280.00,
    "large_bulk_price": 960.00,
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T11:00:00.000000Z"
}
```

### Eliminar un destino
```http
DELETE /api/destinations/{id}
```

#### Respuesta
```json
{
    "message": "Destino eliminado correctamente"
}
```

### Códigos de error
| Código | Descripción |
|--------|-------------|
| 404 | Destino no encontrado |
| 422 | Error de validación en los datos enviados |

#### Ejemplo de error de validación
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "origin": ["El campo origen es obligatorio"],
        "destination": ["El campo destino es obligatorio"],
        "fixed_price": ["El campo precio fijo es obligatorio y debe ser un número"],
        "small_bulk_price": ["El campo precio por bulto chico es obligatorio y debe ser un número"],
        "large_bulk_price": ["El campo precio por bulto grande es obligatorio y debe ser un número"]
    }
}
```

## 🚚 Comisiones Extraordinarias

### Endpoints

- `GET /api/extraordinary-commissions` - Listar todas las comisiones extraordinarias
- `POST /api/extraordinary-commissions` - Crear una nueva comisión extraordinaria
- `GET /api/extraordinary-commissions/{id}` - Obtener una comisión extraordinaria específica
- `PUT /api/extraordinary-commissions/{id}` - Actualizar una comisión extraordinaria
- `DELETE /api/extraordinary-commissions/{id}` - Eliminar una comisión extraordinaria

### Estructura de Datos

```json
{
    "origin": "string",
    "destination": "string",
    "detail": "string",
    "price": "decimal",
    "observations": "string (opcional)"
}
```

### Validaciones

- `origin`: Requerido, string
- `destination`: Requerido, string
- `detail`: Requerido, string
- `price`: Requerido, numérico
- `observations`: Opcional, string

## 🚛 Transportes

El módulo de transportes permite gestionar los vehículos disponibles para el transporte de mercancías.

### Listar todos los transportes
```http
GET /api/transports
```

#### Respuesta
```json
[
    {
        "id": 1,
        "plate": "ABC123",
        "description": "Camión de carga",
        "phone": "1234567890",
        "insurance": "Seguro XYZ",
        "usage": "Carga general",
        "observation": "Observación de prueba",
        "created_at": "2024-03-21T10:00:00.000000Z",
        "updated_at": "2024-03-21T10:00:00.000000Z"
    }
]
```

### Crear un nuevo transporte
```http
POST /api/transports
```

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| plate | string | Sí | Placa del vehículo (máximo 10 caracteres) |
| description | string | Sí | Descripción del vehículo |
| phone | string | No | Teléfono de contacto |
| insurance | string | No | Información del seguro |
| usage | string | No | Uso del vehículo |
| observation | string | No | Observaciones adicionales |

#### Ejemplo de solicitud
```json
{
    "plate": "ABC123",
    "description": "Camión de carga",
    "phone": "1234567890",
    "insurance": "Seguro XYZ",
    "usage": "Carga general",
    "observation": "Observación de prueba"
}
```

#### Respuesta
```json
{
    "id": 1,
    "plate": "ABC123",
    "description": "Camión de carga",
    "phone": "1234567890",
    "insurance": "Seguro XYZ",
    "usage": "Carga general",
    "observation": "Observación de prueba",
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T10:00:00.000000Z"
}
```

### Actualizar un transporte
```http
PUT /api/transports/{id}
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID del transporte a actualizar.

#### Respuesta
```json
{
    "id": 1,
    "plate": "XYZ789",
    "description": "Nueva descripción",
    "phone": "9876543210",
    "insurance": "Nuevo seguro",
    "usage": "Nuevo uso",
    "observation": "Nueva observación",
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T11:00:00.000000Z"
}
```

### Eliminar un transporte
```http
DELETE /api/transports/{id}
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID del transporte a eliminar.

#### Respuesta
Status: 204 No Content

### Códigos de error
| Código | Descripción |
|--------|-------------|
| 400 | Error en los datos enviados o transporte no encontrado |
| 422 | Error de validación en los datos enviados |

## 💰 Gastos de Transporte

El módulo de gastos permite gestionar los gastos asociados a cada transporte.

### Listar gastos de un transporte
```http
GET /api/transports/{transportId}/expenses
```

#### Parámetros de ruta:
- `transportId` (integer, requerido): ID del transporte.

#### Respuesta
```json
[
    {
        "id": 1,
        "transport_id": 1,
        "date": "2024-03-25",
        "detail": "Combustible",
        "amount": "150.50",
        "created_at": "2024-03-25T10:00:00.000000Z",
        "updated_at": "2024-03-25T10:00:00.000000Z"
    }
]
```

### Crear un nuevo gasto
```http
POST /api/transports/{transportId}/expenses
```

#### Parámetros de ruta:
- `transportId` (integer, requerido): ID del transporte.

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| date | date | Sí | Fecha del gasto |
| detail | string | Sí | Detalle del gasto |
| amount | numeric | Sí | Monto del gasto (debe ser positivo) |

#### Ejemplo de solicitud
```json
{
    "date": "2024-03-25",
    "detail": "Combustible",
    "amount": 150.50
}
```

#### Respuesta
```json
{
    "id": 1,
    "transport_id": 1,
    "date": "2024-03-25",
    "detail": "Combustible",
    "amount": "150.50",
    "created_at": "2024-03-25T10:00:00.000000Z",
    "updated_at": "2024-03-25T10:00:00.000000Z"
}
```

### Actualizar un gasto
```http
PUT /api/transports/{transportId}/expenses/{expenseId}
```

#### Parámetros de ruta:
- `transportId` (integer, requerido): ID del transporte.
- `expenseId` (integer, requerido): ID del gasto.

#### Respuesta
```json
{
    "id": 1,
    "transport_id": 1,
    "date": "2024-03-26",
    "detail": "Mantenimiento",
    "amount": "200.00",
    "created_at": "2024-03-25T10:00:00.000000Z",
    "updated_at": "2024-03-26T10:00:00.000000Z"
}
```

### Eliminar un gasto
```http
DELETE /api/transports/{transportId}/expenses/{expenseId}
```

#### Parámetros de ruta:
- `transportId` (integer, requerido): ID del transporte.
- `expenseId` (integer, requerido): ID del gasto.

#### Respuesta
```json
{
    "message": "Gasto eliminado correctamente"
}
```

### Códigos de error
| Código | Descripción |
|--------|-------------|
| 404 | Gasto no encontrado |
| 422 | Error de validación en los datos enviados |

## 📍 Ubicaciones

El módulo de ubicaciones permite gestionar las ubicaciones de origen y destino para las comisiones.

### Listar todas las ubicaciones
```http
GET /api/locations
```

#### Respuesta
```json
[
    {
        "id": 1,
        "name": "Sucursal Centro",
        "address": "Av. Principal 123",
        "origin": "Buenos Aires",
        "phone": "1234567890",
        "map": "https://maps.google.com",
        "schedule": "9:00 - 18:00",
        "observation": "Ubicación principal",
        "created_at": "2024-03-21T10:00:00.000000Z",
        "updated_at": "2024-03-21T10:00:00.000000Z"
    }
]
```

### Crear una nueva ubicación
```http
POST /api/locations
```

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| name | string | Sí | Nombre de la ubicación |
| address | string | Sí | Dirección de la ubicación |
| origin | string | Sí | Ciudad de origen |
| phone | string | Sí | Teléfono de contacto |
| map | string | No | URL del mapa |
| schedule | string | Sí | Horario de atención |
| observation | string | No | Observaciones adicionales |

#### Ejemplo de solicitud
```json
{
    "name": "Sucursal Centro",
    "address": "Av. Principal 123",
    "origin": "Buenos Aires",
    "phone": "1234567890",
    "map": "https://maps.google.com",
    "schedule": "9:00 - 18:00",
    "observation": "Ubicación principal"
}
```

#### Respuesta
```json
{
    "id": 1,
    "name": "Sucursal Centro",
    "address": "Av. Principal 123",
    "origin": "Buenos Aires",
    "phone": "1234567890",
    "map": "https://maps.google.com",
    "schedule": "9:00 - 18:00",
    "observation": "Ubicación principal",
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T10:00:00.000000Z"
}
```

### Obtener ubicaciones por origen
```http
GET /api/locations/origin/{origin}
```

#### Parámetros de ruta:
- `origin` (string, requerido): Ciudad de origen.

#### Respuesta
```json
[
    {
        "id": 1,
        "name": "Sucursal Centro",
        "address": "Av. Principal 123",
        "origin": "Buenos Aires",
        "phone": "1234567890",
        "map": "https://maps.google.com",
        "schedule": "9:00 - 18:00",
        "observation": "Ubicación principal"
    }
]
```

### Actualizar una ubicación
```http
PUT /api/locations/{id}
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la ubicación a actualizar.

#### Respuesta
```json
{
    "id": 1,
    "name": "Sucursal Centro Actualizada",
    "address": "Av. Principal 456",
    "origin": "Buenos Aires",
    "phone": "0987654321",
    "map": "https://maps.google.com/updated",
    "schedule": "10:00 - 19:00",
    "observation": "Ubicación actualizada",
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T11:00:00.000000Z"
}
```

### Eliminar una ubicación
```http
DELETE /api/locations/{id}
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la ubicación a eliminar.

#### Respuesta
Status: 204 No Content

### Códigos de error
| Código | Descripción |
|--------|-------------|
| 404 | Ubicación no encontrada |
| 422 | Error de validación en los datos enviados |

## 📋 Comisiones

El módulo de comisiones permite gestionar las órdenes de transporte y sus estados.

### Crear una nueva comisión
```http
POST /api/commissions
```

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| client_id | integer | Sí | ID del cliente |
| date | date | Sí | Fecha de la comisión |
| origin | string | Sí | Ciudad de origen |
| destination | string | Sí | Ciudad de destino |
| status | string | Sí | Estado de la comisión |
| origin_location_id | integer | Sí | ID de la ubicación de origen |
| destination_location_id | integer | Sí | ID de la ubicación de destino |
| items | array | Sí | Array de items de la comisión |
| total | numeric | Sí | Total de la comisión |

#### Estructura de items
```json
{
    "type": "ordinaria|extraordinaria",
    "size": "small|large (solo para ordinaria)",
    "quantity": 1,
    "unit_price": 100.00,
    "subtotal": 100.00,
    "detail": "Detalle (solo para extraordinaria)"
}
```

#### Ejemplo de solicitud
```json
{
    "client_id": 1,
    "date": "2024-03-21",
    "origin": "Buenos Aires",
    "destination": "Córdoba",
    "status": "deposito",
    "origin_location_id": 1,
    "destination_location_id": 2,
    "items": [
        {
            "type": "ordinaria",
            "size": "small",
            "quantity": 2,
            "unit_price": 500,
            "subtotal": 1000
        },
        {
            "type": "extraordinaria",
            "quantity": 1,
            "unit_price": 1500,
            "subtotal": 1500,
            "detail": "Manejo especial"
        }
    ],
    "total": 2500
}
```

#### Respuesta
```json
{
    "id": 1,
    "client_id": 1,
    "destination_id": 1,
    "branch_id": 1,
    "date": "2024-03-21",
    "status": "deposito",
    "total": "2500.00",
    "user_id": 1,
    "origin_location_id": 1,
    "destination_location_id": 2,
    "created_at": "2024-03-21T10:00:00.000000Z",
    "updated_at": "2024-03-21T10:00:00.000000Z"
}
```

### Listar comisiones
```http
GET /api/commissions
```

#### Parámetros de consulta opcionales:
- `clientName`: Filtrar por nombre del cliente
- `destination_id`: Filtrar por ID de destino
- `branch_id`: Filtrar por ID de sucursal
- `user_id`: Filtrar por ID de usuario
- `dateFrom`: Filtrar desde fecha
- `dateTo`: Filtrar hasta fecha
- `status`: Filtrar por estado
- `page`: Número de página
- `perPage`: Elementos por página
- `sort_by`: Campo para ordenar
- `sort_direction`: Dirección del ordenamiento (asc/desc)

#### Respuesta
```json
{
    "data": [
        {
            "id": 1,
            "client_id": 1,
            "client": {
                "id": 1,
                "name": "Juan Pérez"
            },
            "destination": {
                "id": 1,
                "origin": "Buenos Aires",
                "destination": "Córdoba"
            },
            "branch_id": 1,
            "date": "2024-03-21",
            "status": "deposito",
            "user_id": 1,
            "total": "2500.00",
            "items": [
                {
                    "id": 1,
                    "type": "ordinaria",
                    "size": "small",
                    "quantity": 2,
                    "unit_price": "500.00",
                    "subtotal": "1000.00"
                }
            ]
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

### Obtener una comisión específica
```http
GET /api/commissions/{id}
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la comisión.

#### Respuesta
```json
{
    "data": {
        "id": 1,
        "client_id": 1,
        "client": {
            "id": 1,
            "name": "Juan Pérez"
        },
        "destination": {
            "id": 1,
            "origin": "Buenos Aires",
            "destination": "Córdoba"
        },
        "branch_id": 1,
        "branch": {
            "id": 1,
            "name": "Sucursal Centro"
        },
        "date": "2024-03-21",
        "status": "deposito",
        "user_id": 1,
        "user": {
            "id": 1,
            "name": "Usuario"
        },
        "total": "2500.00",
        "items": [
            {
                "id": 1,
                "type": "ordinaria",
                "size": "small",
                "quantity": 2,
                "unit_price": "500.00",
                "subtotal": "1000.00"
            }
        ],
        "logs": [
            {
                "previous_status": "",
                "new_status": "deposito",
                "details": "Comisión creada",
                "user": "Usuario"
            }
        ]
    }
}
```

### Obtener estados disponibles
```http
GET /api/commissions/statuses
```

#### Respuesta
```json
[
    {
        "value": "deposito",
        "label": "DEPOSITO"
    },
    {
        "value": "las_rosas",
        "label": "LAS_ROSAS"
    },
    {
        "value": "en_transito",
        "label": "EN_TRANSITO"
    },
    {
        "value": "entregado",
        "label": "ENTREGADO"
    }
]
```

### Actualizar estado de una comisión
```http
PATCH /api/commissions/{id}/status
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la comisión.

#### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| status | string | Sí | Nuevo estado de la comisión |
| details | string | No | Detalles del cambio de estado |

#### Ejemplo de solicitud
```json
{
    "status": "en_transito",
    "details": "En camino a destino"
}
```

#### Respuesta
```json
{
    "message": "Estado de la comisión actualizado correctamente",
    "commission": {
        "id": 1,
        "status": "en_transito",
        "branch": {
            "id": 1,
            "name": "Sucursal Centro"
        }
    }
}
```

### Eliminar una comisión
```http
DELETE /api/commissions/{id}
```

#### Parámetros de ruta:
- `id` (integer, requerido): ID de la comisión a eliminar.

#### Respuesta
```json
{
    "message": "Comisión eliminada correctamente"
}
```

### Códigos de error
| Código | Descripción |
|--------|-------------|
| 400 | Error en los datos enviados |
| 404 | Comisión no encontrada |
| 422 | Error de validación en los datos enviados |

## 🔧 Comandos Útiles

### Análisis de Código
```bash
# Ejecutar PHPStan para análisis estático
composer analyse

# Formatear código con PHP-CS-Fixer
composer format
```

### Base de Datos
```bash
# Ejecutar migraciones
php artisan migrate

# Revertir migraciones
php artisan migrate:rollback

# Ejecutar seeders
php artisan db:seed

# Marcar migraciones como ejecutadas (si hay problemas)
php artisan migrate:status
```

### Tests
```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter=UserTest

# Ejecutar tests con cobertura
php artisan test --coverage
```

## 🐳 Docker

### Comandos Docker
```bash
# Construir y levantar contenedores
docker-compose up -d --build

# Ver logs
docker-compose logs -f

# Ejecutar comandos dentro del contenedor
docker-compose exec app php artisan migrate

# Detener contenedores
docker-compose down
```

## 📝 Notas Importantes

- Todas las rutas de la API requieren autenticación excepto `/api/login`
- Las rutas de usuarios y sucursales requieren rol de administrador
- Los transportes y gastos no requieren autenticación específica
- Las ubicaciones no requieren autenticación
- Las comisiones requieren autenticación y están asociadas al usuario autenticado

## 🤝 Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.
