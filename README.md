# 🚚 RYR Comisiones - API Backend

Backend API para el sistema de gestión de comisiones y envíos de RYR Comisiones, desarrollado con Laravel 11 y arquitectura hexagonal.

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Tecnologías](#-tecnologías)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Autenticación](#-autenticación)
- [Endpoints de la API](#-endpoints-de-la-api)
  - [Autenticación](#autenticación-1)
  - [Clientes](#clientes)
  - [Comisiones](#comisiones)
  - [Usuarios](#usuarios)
  - [Sucursales](#sucursales)
  - [Destinos](#destinos)
  - [Ubicaciones](#ubicaciones)
  - [Transportes](#transportes)
  - [Gastos](#gastos)
  - [Ingresos](#ingresos)
  - [Cuenta Corriente](#cuenta-corriente)
  - [Dashboard de Clientes](#dashboard-de-clientes)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Testing](#-testing)
- [Variables de Entorno](#-variables-de-entorno)

## ✨ Características

- 🔐 **Autenticación múltiple**: Email/password y verificación por código
- 📱 **Notificaciones**: WhatsApp y email automáticas
- 🏢 **Gestión de sucursales**: Multi-sucursal con roles
- 📊 **Dashboard de clientes**: Seguimiento de envíos y saldos
- 💰 **Cuenta corriente**: Gestión de pagos y saldos
- 📈 **Reportes**: Comisiones, gastos e ingresos
- 🚛 **Tracking público**: Seguimiento sin autenticación
- 🔄 **Webhooks**: Notificaciones automáticas de estado

## 🛠 Tecnologías

- **Laravel 11** - Framework PHP
- **Laravel Sanctum** - Autenticación API
- **MySQL/SQLite** - Base de datos
- **Docker** - Containerización
- **PHPUnit** - Testing
- **Green API** - WhatsApp Business API
- **Laravel Mail** - Envío de emails

## 🚀 Instalación

### Prerrequisitos

- Docker y Docker Compose
- PHP 8.2+
- Composer

### Pasos de instalación

1. **Clonar el repositorio**
```bash
git clone <repository-url>
cd ryr-backend
```

2. **Configurar variables de entorno**
```bash
cp .env.example .env
# Editar .env con tus configuraciones
```

3. **Levantar con Docker**
```bash
docker-compose up -d
```

4. **Instalar dependencias**
```bash
docker-compose exec app composer install
```

5. **Generar clave de aplicación**
```bash
docker-compose exec app php artisan key:generate
```

6. **Ejecutar migraciones**
```bash
docker-compose exec app php artisan migrate
```

7. **Ejecutar seeders (opcional)**
```bash
docker-compose exec app php artisan db:seed
```

## ⚙️ Configuración

### Variables de entorno importantes

```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ryr_backend
DB_USERNAME=root
DB_PASSWORD=

# WhatsApp API (Green API)
WHATSAPP_API_URL=https://api.green-api.com
WHATSAPP_API_INSTANCE=your_instance_id
WHATSAPP_API_TOKEN=your_token

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password

# Configuración de la aplicación
DISABLE_WHATSAPP=false
```

## 🔐 Autenticación

La API utiliza **Laravel Sanctum** para la autenticación. Existen dos tipos de autenticación:

### 1. Autenticación por Email/Password (Administradores)

```bash
POST /api/login
Content-Type: application/json

{
    "email": "admin@ryr.com",
    "password": "password"
}
```

**Respuesta:**
```json
{
    "success": true,
    "token": "1|abc123...",
    "user": {
        "id": 1,
        "name": "Admin",
        "email": "admin@ryr.com",
        "role": "administrador"
    }
}
```

### 2. Autenticación por Código (Clientes)

**Paso 1: Validar identificador**
```bash
POST /api/validate-identifier
Content-Type: application/json

{
    "identifier": "cliente@email.com",
    "type": "email"
}
```

**Paso 2: Verificar código**
```bash
POST /api/verify-code
Content-Type: application/json

{
    "identifier": "cliente@email.com",
    "type": "email",
    "code": "123456"
}
```

### Uso del Token

Incluir el token en el header de las peticiones:
```bash
Authorization: Bearer 1|abc123...
```

## 📡 Endpoints de la API

### 🔐 Autenticación

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `POST` | `/api/login` | Login con email/password | No |
| `POST` | `/api/logout` | Logout | Sí |
| `POST` | `/api/validate-identifier` | Validar email/teléfono | No |
| `POST` | `/api/verify-code` | Verificar código | No |

### 👥 Clientes

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `POST` | `/api/customers/public` | Crear cliente público | No |
| `GET` | `/api/customers` | Listar clientes | Admin |
| `POST` | `/api/customers` | Crear cliente | Admin |
| `GET` | `/api/customers/{id}` | Obtener cliente | Admin |
| `PUT` | `/api/customers/{id}` | Actualizar cliente | Admin |
| `DELETE` | `/api/customers/{id}` | Eliminar cliente | Admin |
| `GET` | `/api/customers/search` | Buscar clientes | Admin |

**Ejemplo - Crear cliente público:**
```bash
POST /api/customers/public
Content-Type: application/json

{
    "name": "Juan",
    "last_name": "Pérez",
    "email": "juan@email.com",
    "phone": "2914716316",
    "address": "Calle 123",
    "city": "Buenos Aires",
    "dni": "12345678"
}
```

### 📦 Comisiones

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/commissions/{id}/tracking` | Tracking público | No |
| `POST` | `/api/commissions/public` | Crear comisión pública | No |
| `GET` | `/api/commissions` | Listar comisiones | Admin |
| `POST` | `/api/commissions` | Crear comisión | Admin |
| `GET` | `/api/commissions/{id}` | Obtener comisión | Admin |
| `PATCH` | `/api/commissions/{id}/status` | Actualizar estado | Admin |
| `DELETE` | `/api/commissions/{id}` | Eliminar comisión | Admin |
| `GET` | `/api/commissions/statuses` | Estados disponibles | Admin |

**Ejemplo - Tracking público:**
```bash
GET /api/commissions/395699000002/tracking
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "tracking_id": 395699000002,
        "tracking_number": "RYR395699000002",
        "status": "PENDIENTE",
        "origin": {
            "name": "Sucursal Centro",
            "address": "Av. San Martín 123",
            "city": "Buenos Aires",
            "phone": "011-1234-5678",
            "schedule": "9:00 a 18:00"
        },
        "destination": {
            "name": "Sucursal Norte",
            "address": "Av. Libertador 456",
            "city": "Córdoba",
            "phone": "0351-9876-5432",
            "schedule": "8:00 a 17:00"
        },
        "date": "2025-07-31 00:00:00",
        "items_count": 2,
        "message": "Para más información, inicia sesión en tu cuenta de cliente."
    }
}
```

### 👤 Usuarios

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/users` | Listar usuarios | Admin |
| `POST` | `/api/users` | Crear usuario | Admin |
| `PUT` | `/api/users/{id}` | Actualizar usuario | Admin |
| `DELETE` | `/api/users/{id}` | Eliminar usuario | Admin |
| `GET` | `/api/users/{userId}/salary` | Calcular salario | Admin |

**Ejemplo - Crear usuario:**
```bash
POST /api/users
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Nuevo Usuario",
    "email": "usuario@ryr.com",
    "password": "password123",
    "role": "empleado",
    "branch_id": 1,
    "salary_type": "fixed_salary",
    "base_salary": 50000
}
```

### 🏢 Sucursales

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/branches` | Listar sucursales | No |
| `POST` | `/api/branches` | Crear sucursal | No |
| `GET` | `/api/branches/{id}` | Obtener sucursal | No |
| `PUT` | `/api/branches/{id}` | Actualizar sucursal | No |
| `DELETE` | `/api/branches/{id}` | Eliminar sucursal | No |

### 🗺️ Destinos

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/destinations` | Listar destinos | No |
| `POST` | `/api/destinations` | Crear destino | No |
| `GET` | `/api/destinations/{id}` | Obtener destino | No |
| `PUT` | `/api/destinations/{id}` | Actualizar destino | No |
| `DELETE` | `/api/destinations/{id}` | Eliminar destino | No |
| `GET` | `/api/destinations/rates/{origin}/{destination}` | Obtener tarifas | No |
| `GET` | `/api/origins` | Listar orígenes | No |
| `GET` | `/api/destinations/origin/{origin}` | Destinos por origen | No |

### 📍 Ubicaciones

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/locations` | Listar ubicaciones | No |
| `POST` | `/api/locations` | Crear ubicación | No |
| `PUT` | `/api/locations/{id}` | Actualizar ubicación | No |
| `DELETE` | `/api/locations/{id}` | Eliminar ubicación | No |
| `GET` | `/api/locations/origin/{origin}` | Ubicaciones por origen | No |

### 🚛 Transportes

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/transports` | Listar transportes | No |
| `POST` | `/api/transports` | Crear transporte | No |
| `PUT` | `/api/transports/{id}` | Actualizar transporte | No |
| `DELETE` | `/api/transports/{id}` | Eliminar transporte | No |
| `GET` | `/api/transports/{transportId}/expenses` | Gastos del transporte | No |
| `POST` | `/api/transports/{transportId}/expenses` | Crear gasto de transporte | No |
| `PUT` | `/api/transports/{transportId}/expenses/{expenseId}` | Actualizar gasto | No |
| `DELETE` | `/api/transports/{transportId}/expenses/{expenseId}` | Eliminar gasto | No |

### 💰 Gastos

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/expenses` | Listar gastos | No |
| `POST` | `/api/expenses` | Crear gasto | No |
| `GET` | `/api/expenses/{id}` | Obtener gasto | No |
| `PUT` | `/api/expenses/{id}` | Actualizar gasto | No |
| `DELETE` | `/api/expenses/{id}` | Eliminar gasto | No |
| `GET` | `/api/users/{userId}/expenses` | Gastos por usuario | No |

### 📈 Ingresos

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/incomes` | Listar ingresos | No |
| `POST` | `/api/incomes` | Crear ingreso | No |
| `GET` | `/api/incomes/{id}` | Obtener ingreso | No |
| `PUT` | `/api/incomes/{id}` | Actualizar ingreso | No |
| `DELETE` | `/api/incomes/{id}` | Eliminar ingreso | No |
| `GET` | `/api/users/{userId}/incomes` | Ingresos por usuario | No |

### 💳 Cuenta Corriente

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `POST` | `/api/current-accounts` | Crear transacción | No |
| `GET` | `/api/current-accounts/{id}` | Obtener transacción | No |
| `PUT` | `/api/current-accounts/{id}` | Actualizar transacción | No |
| `DELETE` | `/api/current-accounts/{id}` | Eliminar transacción | No |
| `GET` | `/api/customers/{customerId}/current-account/transactions` | Transacciones del cliente | Admin |
| `GET` | `/api/customers/{customerId}/current-account/balance` | Saldo del cliente | Admin |

### 🏠 Dashboard de Clientes

| Método | Endpoint | Descripción | Autenticación |
|--------|----------|-------------|---------------|
| `GET` | `/api/client/profile` | Obtener perfil | Cliente |
| `PUT` | `/api/client/profile` | Actualizar perfil | Cliente |
| `GET` | `/api/client/shipments` | Mis envíos | Cliente |
| `GET` | `/api/client/account-balance` | Mi saldo | Cliente |
| `GET` | `/api/client/current-account/transactions` | Mis transacciones | Cliente |
| `GET` | `/api/client/current-account/balance` | Mi saldo detallado | Cliente |

**Ejemplo - Obtener perfil del cliente:**
```bash
GET /api/client/profile
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
    "success": true,
    "customer": {
        "id": 1,
        "name": "Juan",
        "last_name": "Pérez",
        "email": "juan@email.com",
        "phone": "2914716316",
        "address": "Calle 123",
        "city": "Buenos Aires",
        "dni": "12345678"
    },
    "user": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@email.com",
        "role": "cliente"
    }
}
```

## 🏗️ Estructura del Proyecto

```
ryr-backend/
├── app/
│   ├── Contexts/                    # Arquitectura hexagonal
│   │   ├── Auth/                    # Autenticación
│   │   ├── Branchs/                 # Sucursales
│   │   ├── Commissions/             # Comisiones
│   │   ├── Customers/               # Clientes
│   │   ├── CurrentAccount/          # Cuenta corriente
│   │   ├── Destinations/            # Destinos
│   │   ├── Expenses/                # Gastos
│   │   ├── Incomes/                 # Ingresos
│   │   ├── Locations/               # Ubicaciones
│   │   ├── Transports/              # Transportes
│   │   └── Users/                   # Usuarios
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                # Controladores de auth
│   │   │   ├── Client/              # Dashboard cliente
│   │   │   └── Public/              # Endpoints públicos
│   │   └── Middleware/              # Middlewares
│   ├── Mail/                        # Clases de email
│   ├── Services/                    # Servicios
│   └── Shared/                      # Modelos y enums compartidos
├── config/                          # Configuraciones
├── database/
│   ├── factories/                   # Factories para testing
│   ├── migrations/                  # Migraciones
│   └── seeders/                     # Seeders
├── resources/
│   └── views/
│       └── emails/                  # Templates de email
├── routes/
│   └── api.php                      # Rutas de la API
└── tests/                           # Tests
```

## 🧪 Testing

### Ejecutar tests

```bash
# Todos los tests
docker-compose exec app php artisan test

# Tests específicos
docker-compose exec app php artisan test --filter=CommissionTest

# Tests con coverage
docker-compose exec app php artisan test --coverage
```

### Tipos de tests

- **Feature Tests**: Prueban endpoints completos
- **Unit Tests**: Prueban casos de uso individuales
- **Integration Tests**: Prueban integración entre componentes

## 🔧 Comandos útiles

```bash
# Limpiar cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear

# Ver rutas
docker-compose exec app php artisan route:list

# Crear migración
docker-compose exec app php artisan make:migration create_table_name

# Crear seeder
docker-compose exec app php artisan make:seeder TableNameSeeder

# Ejecutar seeder específico
docker-compose exec app php artisan db:seed --class=TableNameSeeder
```

## 📞 Soporte

Para soporte técnico o consultas sobre la API, contactar a:
- **Email**: soporte@ryr.com
- **WhatsApp**: +54 9 11 1234-5678

## 📄 Licencia

Este proyecto es propiedad de RYR Comisiones. Todos los derechos reservados.

---

**Desarrollado con ❤️ por el equipo de Innova Developers**
