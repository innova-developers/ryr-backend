
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
### GET `/api/brenches`
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

### PUT `/api/brranches/{id}`
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
- `id` (integer, requerido): ID del usuario a eliminar.

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
