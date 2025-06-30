# RYR Backend

Sistema de gestión de transportes y logística con arquitectura hexagonal.

## Características

- **Autenticación**: Sistema de login/logout con Sanctum
- **Gestión de Usuarios**: CRUD completo con roles de administrador
- **Sucursales**: Gestión de sucursales de la empresa
- **Clientes**: Gestión de clientes con búsqueda
- **Destinos**: Gestión de destinos y tarifas
- **Comisiones**: Sistema completo de comisiones con estados
- **Comisiones Extraordinarias**: Gestión de comisiones especiales
- **Transportes**: Gestión de transportes
- **Gastos**: Sistema de gastos con categorías
- **Categorías de Gastos**: ABM de categorías para clasificar gastos
- **Ubicaciones**: Gestión de ubicaciones por origen

## Arquitectura

El proyecto utiliza arquitectura hexagonal (Clean Architecture) con los siguientes contextos:

- **Auth**: Autenticación y autorización
- **Users**: Gestión de usuarios
- **Branchs**: Gestión de sucursales
- **Customers**: Gestión de clientes
- **Destinations**: Gestión de destinos
- **Commissions**: Gestión de comisiones
- **ExtraordinaryCommissions**: Comisiones extraordinarias
- **Transports**: Gestión de transportes
- **Expenses**: Gestión de gastos
- **ExpenseCategories**: Categorías de gastos
- **Locations**: Gestión de ubicaciones

## Instalación

1. Clonar el repositorio
2. Instalar dependencias: `composer install`
3. Copiar `.env.example` a `.env` y configurar
4. Generar clave: `php artisan key:generate`
5. Ejecutar migraciones: `php artisan migrate`
6. Ejecutar seeders: `php artisan db:seed`
7. Iniciar servidor: `php artisan serve`

## Endpoints

### Autenticación

- `POST /api/login` - Iniciar sesión
- `POST /api/logout` - Cerrar sesión (requiere auth)

### Usuarios (requiere admin)

- `GET /api/users` - Listar usuarios
- `POST /api/users` - Crear usuario
- `PUT /api/users/{id}` - Actualizar usuario
- `DELETE /api/users/{id}` - Eliminar usuario

### Sucursales (requiere admin)

- `GET /api/branches` - Listar sucursales
- `POST /api/branches` - Crear sucursal
- `PUT /api/branches/{id}` - Actualizar sucursal
- `DELETE /api/branches/{id}` - Eliminar sucursal

### Clientes

- `GET /api/customers` - Listar clientes
- `POST /api/customers` - Crear cliente
- `PUT /api/customers/{id}` - Actualizar cliente
- `DELETE /api/customers/{id}` - Eliminar cliente
- `GET /api/customers/search` - Buscar clientes

### Destinos

- `GET /api/destinations` - Listar destinos
- `POST /api/destinations` - Crear destino
- `PUT /api/destinations/{id}` - Actualizar destino
- `DELETE /api/destinations/{id}` - Eliminar destino
- `GET /api/destinations/rates/{origin}/{destination}` - Obtener tarifas
- `GET /api/origins` - Listar orígenes
- `GET /api/destinations/origin/{origin}` - Destinos por origen

### Comisiones

- `GET /api/commissions` - Listar comisiones
- `POST /api/commissions` - Crear comisión
- `GET /api/commissions/{id}` - Ver comisión
- `PATCH /api/commissions/{id}/status` - Actualizar estado
- `DELETE /api/commissions/{id}` - Eliminar comisión
- `GET /api/commissions/statuses` - Estados disponibles

### Comisiones Extraordinarias

- `GET /api/extraordinary-commissions` - Listar comisiones extraordinarias
- `POST /api/extraordinary-commissions` - Crear comisión extraordinaria
- `PUT /api/extraordinary-commissions/{id}` - Actualizar comisión extraordinaria
- `DELETE /api/extraordinary-commissions/{id}` - Eliminar comisión extraordinaria
- `GET /api/extraordinary-commissions/{origin}/{destination}` - Obtener por origen y destino

### Transportes

- `GET /api/transports` - Listar transportes
- `POST /api/transports` - Crear transporte
- `PUT /api/transports/{id}` - Actualizar transporte
- `DELETE /api/transports/{id}` - Eliminar transporte

### Categorías de Gastos

- `GET /api/expense-categories` - Listar categorías
- `POST /api/expense-categories` - Crear categoría
- `GET /api/expense-categories/{id}` - Ver categoría
- `PUT /api/expense-categories/{id}` - Actualizar categoría
- `DELETE /api/expense-categories/{id}` - Eliminar categoría

**Parámetros de consulta:**
- `?active=true` - Solo categorías activas

### Gastos

#### Gastos Generales
- `GET /api/expenses` - Listar todos los gastos
- `POST /api/expenses` - Crear gasto
- `GET /api/expenses/{id}` - Ver gasto
- `PUT /api/expenses/{id}` - Actualizar gasto
- `DELETE /api/expenses/{id}` - Eliminar gasto

**Parámetros de consulta:**
- `?transport_id={id}` - Filtrar por transporte

#### Gastos de Transportes (compatibilidad)
- `GET /api/transports/{transportId}/expenses` - Gastos de un transporte
- `POST /api/transports/{transportId}/expenses` - Crear gasto para transporte
- `PUT /api/transports/{transportId}/expenses/{expenseId}` - Actualizar gasto de transporte
- `DELETE /api/transports/{transportId}/expenses/{expenseId}` - Eliminar gasto de transporte

### Ubicaciones

- `GET /api/locations` - Listar ubicaciones
- `POST /api/locations` - Crear ubicación
- `GET /api/locations/origin/{origin}` - Ubicaciones por origen
- `PUT /api/locations/{id}` - Actualizar ubicación
- `DELETE /api/locations/{id}` - Eliminar ubicación

## Estructura de Datos

### Gasto

```json
{
  "id": 1,
  "transport_id": 1,
  "expense_category_id": 1,
  "date": "2024-03-25",
  "detail": "Combustible",
  "amount": "150.50",
  "created_at": "2024-03-25T10:00:00.000000Z",
  "updated_at": "2024-03-25T10:00:00.000000Z",
  "transport": {
    "id": 1,
    "name": "Transporte 1"
  },
  "category": {
    "id": 1,
    "name": "Combustible",
    "description": "Gastos de combustible"
  }
}
```

### Categoría de Gasto

```json
{
  "id": 1,
  "name": "Combustible",
  "description": "Gastos de combustible y lubricantes",
  "is_active": true,
  "created_at": "2024-03-25T10:00:00.000000Z",
  "updated_at": "2024-03-25T10:00:00.000000Z"
}
```

## Validaciones

### Crear/Actualizar Gasto

- `date`: Requerido, fecha válida
- `detail`: Requerido, máximo 255 caracteres
- `amount`: Requerido, numérico, mínimo 0
- `transport_id`: Opcional, debe existir en tabla transports
- `expense_category_id`: Opcional, debe existir en tabla expense_categories

### Crear/Actualizar Categoría

- `name`: Requerido, único, máximo 255 caracteres
- `description`: Opcional, máximo 1000 caracteres
- `is_active`: Requerido para actualizar, booleano

## Categorías Predefinidas

El sistema incluye las siguientes categorías por defecto:

- **Transportes**: Gastos relacionados con transportes y logística
- **Combustible**: Gastos de combustible y lubricantes
- **Mantenimiento**: Gastos de mantenimiento de vehículos y equipos
- **Peajes**: Gastos de peajes y viáticos
- **Seguros**: Gastos de seguros y coberturas
- **Oficina**: Gastos de oficina y administración
- **Marketing**: Gastos de marketing y publicidad
- **Otros**: Otros gastos varios

## Compatibilidad

El sistema mantiene compatibilidad con el sistema anterior de gastos de transportes:

- Las rutas `/api/transports/{id}/expenses` siguen funcionando
- Los gastos existentes mantienen su estructura
- Se pueden crear gastos con o sin transporte asociado
- Se pueden crear gastos con o sin categoría asociada

## Testing

Ejecutar tests:

```bash
# Todos los tests
php artisan test

# Tests específicos
php artisan test --filter=ExpenseCategoryTest
php artisan test --filter=ExpensesTest
```

## Migraciones

Para aplicar las nuevas migraciones:

```bash
php artisan migrate
```

Para ejecutar los seeders:

```bash
php artisan db:seed
```

## Desarrollo

### Estructura de Carpetas

```
app/
├── Contexts/
│   ├── Auth/
│   ├── Users/
│   ├── Branchs/
│   ├── Customers/
│   ├── Destinations/
│   ├── Commissions/
│   ├── ExtraordinaryCommissions/
│   ├── Transports/
│   ├── Expenses/
│   ├── ExpenseCategories/
│   └── Locations/
├── Shared/
│   ├── Models/
│   ├── Enums/
│   └── Middleware/
└── Providers/
```

### Patrones Utilizados

- **Arquitectura Hexagonal**: Separación clara entre dominio, aplicación e infraestructura
- **Repository Pattern**: Abstracción del acceso a datos
- **Use Case Pattern**: Lógica de negocio encapsulada
- **DTO Pattern**: Transferencia de datos entre capas
- **Factory Pattern**: Creación de objetos complejos

## Contribución

1. Fork el proyecto
2. Crear rama feature (`git checkout -b feature/AmazingFeature`)
3. Commit cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT.
