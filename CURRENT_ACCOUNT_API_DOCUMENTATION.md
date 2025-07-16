# API de Cuenta Corriente - Documentación para Frontend

## Descripción General

El módulo de cuenta corriente permite gestionar las transacciones financieras de los clientes, incluyendo ingresos (créditos) y egresos (débitos), con cálculo automático de saldos.

## Endpoints Disponibles

### 1. Crear Transacción de Cuenta Corriente

**POST** `/api/current-accounts`

Crea una nueva transacción en la cuenta corriente de un cliente.

#### Parámetros del Body:
```json
{
  "customer_id": 1,
  "type": "credit", // "credit" | "debit"
  "amount": 1000.50,
  "description": "Pago de factura",
  "reference": "FAC-001", // opcional
  "transaction_date": "2024-01-15",
  "payment_method": "cash", // "cash" | "transfer" | "check" | "card" | "other" | null
  "observations": "Pago en efectivo" // opcional
}
```

#### Respuesta Exitosa (201):
```json
{
  "id": 1,
  "customer_id": 1,
  "type": "credit",
  "amount": "1000.50",
  "description": "Pago de factura",
  "reference": "FAC-001",
  "transaction_date": "2024-01-15",
  "balance": "1000.50",
  "payment_method": "cash",
  "observations": "Pago en efectivo",
  "user_id": 1,
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

#### Respuesta de Error (422):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "customer_id": ["El cliente es requerido"],
    "amount": ["El monto debe ser mayor a 0"]
  }
}
```

---

### 2. Obtener Transacción Específica

**GET** `/api/current-accounts/{id}`

Obtiene los detalles de una transacción específica.

#### Respuesta Exitosa (200):
```json
{
  "id": 1,
  "customer_id": 1,
  "type": "credit",
  "amount": "1000.50",
  "description": "Pago de factura",
  "reference": "FAC-001",
  "transaction_date": "2024-01-15",
  "balance": "1000.50",
  "payment_method": "cash",
  "observations": "Pago en efectivo",
  "user_id": 1,
  "customer": {
    "id": 1,
    "name": "Juan",
    "last_name": "Pérez"
  },
  "user": {
    "id": 1,
    "name": "Admin"
  },
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

#### Respuesta de Error (404):
```json
{
  "message": "Transacción no encontrada"
}
```

---

### 3. Actualizar Transacción

**PUT** `/api/current-accounts/{id}`

Actualiza una transacción existente.

#### Parámetros del Body:
```json
{
  "amount": 1500.00,
  "description": "Pago actualizado",
  "observations": "Observación actualizada"
}
```

**Nota:** Todos los campos son opcionales. Solo se actualizarán los campos proporcionados.

#### Respuesta Exitosa (200):
```json
{
  "id": 1,
  "customer_id": 1,
  "type": "credit",
  "amount": "1500.00",
  "description": "Pago actualizado",
  "reference": "FAC-001",
  "transaction_date": "2024-01-15",
  "balance": "1500.00",
  "payment_method": "cash",
  "observations": "Observación actualizada",
  "user_id": 1,
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

---

### 4. Eliminar Transacción

**DELETE** `/api/current-accounts/{id}`

Elimina una transacción (soft delete).

#### Respuesta Exitosa (200):
```json
{
  "message": "Transacción eliminada exitosamente"
}
```

#### Respuesta de Error (404):
```json
{
  "message": "Transacción no encontrada"
}
```

---

### 5. Obtener Transacciones de un Cliente

**GET** `/api/customers/{customerId}/current-account/transactions`

Obtiene todas las transacciones de un cliente específico con paginación y filtros.

#### Parámetros de Query:
- `type`: Filtrar por tipo ("credit" | "debit")
- `start_date`: Fecha de inicio (YYYY-MM-DD)
- `end_date`: Fecha de fin (YYYY-MM-DD)
- `payment_method`: Método de pago ("cash" | "transfer" | "check" | "card" | "other")
- `search`: Búsqueda en descripción, referencia u observaciones
- `per_page`: Elementos por página (default: 15)
- `page`: Número de página (default: 1)

#### Ejemplo de Request:
```
GET /api/customers/1/current-account/transactions?type=credit&start_date=2024-01-01&end_date=2024-01-31&per_page=10&page=1
```

#### Respuesta Exitosa (200):
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "customer_id": 1,
      "type": "credit",
      "amount": "1000.50",
      "description": "Pago de factura",
      "reference": "FAC-001",
      "transaction_date": "2024-01-15",
      "balance": "1000.50",
      "payment_method": "cash",
      "observations": "Pago en efectivo",
      "user_id": 1,
      "customer": {
        "id": 1,
        "name": "Juan",
        "last_name": "Pérez"
      },
      "user": {
        "id": 1,
        "name": "Admin"
      },
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "first_page_url": "http://localhost/api/customers/1/current-account/transactions?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://localhost/api/customers/1/current-account/transactions?page=1",
  "next_page_url": null,
  "path": "http://localhost/api/customers/1/current-account/transactions",
  "per_page": 15,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

---

### 6. Obtener Saldo de un Cliente

**GET** `/api/customers/{customerId}/current-account/balance`

Obtiene el saldo actual de un cliente.

#### Respuesta Exitosa (200):
```json
{
  "customer_id": 1,
  "balance": 1500.50,
  "formatted_balance": "$1.500,50"
}
```

---

### 7. Lista de Clientes con Saldo (Actualizado)

**GET** `/api/customers`

Obtiene la lista de todos los clientes incluyendo su saldo actual.

#### Respuesta Exitosa (200):
```json
[
  {
    "id": 1,
    "dni": 12345678,
    "name": "Juan",
    "email": "juan@example.com",
    "last_name": "Pérez",
    "address": "Calle 123",
    "city": "Buenos Aires",
    "phone": "123456789",
    "is_premium": false,
    "user": {
      "id": 1,
      "name": "Admin"
    },
    "branch": {
      "id": 1,
      "name": "Sucursal Centro"
    },
    "balance": 1500.50,
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
]
```

---

### 8. Búsqueda de Clientes con Saldo (Actualizado)

**GET** `/api/customers/search?q={query}`

Busca clientes por nombre, apellido, email o DNI, incluyendo su saldo actual.

#### Respuesta Exitosa (200):
```json
[
  {
    "id": 1,
    "dni": 12345678,
    "name": "Juan",
    "email": "juan@example.com",
    "last_name": "Pérez",
    "address": "Calle 123",
    "city": "Buenos Aires",
    "phone": "123456789",
    "is_premium": false,
    "user": {
      "id": 1,
      "name": "Admin"
    },
    "branch": {
      "id": 1,
      "name": "Sucursal Centro"
    },
    "balance": 1500.50,
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
]
```

---

## Tipos de Transacciones

### Credit (Ingreso)
- Representa un ingreso de dinero
- Aumenta el saldo del cliente
- Ejemplos: pagos, abonos, devoluciones

### Debit (Egreso)
- Representa un egreso de dinero
- Disminuye el saldo del cliente
- Ejemplos: compras, gastos, retiros

## Métodos de Pago

- `cash`: Efectivo
- `transfer`: Transferencia bancaria
- `check`: Cheque
- `card`: Tarjeta de crédito/débito
- `other`: Otro método

## Cálculo de Saldos

El sistema calcula automáticamente los saldos de cada cliente basándose en todas sus transacciones, ordenadas por fecha y ID. El saldo se actualiza automáticamente cuando:

1. Se crea una nueva transacción
2. Se actualiza el monto o tipo de una transacción existente
3. Se elimina una transacción

## Códigos de Estado HTTP

- `200`: Operación exitosa
- `201`: Recurso creado exitosamente
- `404`: Recurso no encontrado
- `422`: Datos de entrada inválidos
- `500`: Error interno del servidor

## Ejemplos de Uso

### Crear un Pago (Crédito)
```javascript
const response = await fetch('/api/current-accounts', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    customer_id: 1,
    type: 'credit',
    amount: 1000.50,
    description: 'Pago de factura #123',
    reference: 'FAC-123',
    transaction_date: '2024-01-15',
    payment_method: 'cash',
    observations: 'Pago en efectivo'
  })
});
```

### Obtener Transacciones con Filtros
```javascript
const response = await fetch('/api/customers/1/current-account/transactions?type=credit&start_date=2024-01-01&end_date=2024-01-31&per_page=10');
```

### Obtener Saldo de Cliente
```javascript
const response = await fetch('/api/customers/1/current-account/balance');
const { balance, formatted_balance } = await response.json();
console.log(`Saldo: ${formatted_balance}`); // "$1.500,50"
```

### Listar Clientes con Saldo
```javascript
const response = await fetch('/api/customers');
const customers = await response.json();
customers.forEach(customer => {
  console.log(`${customer.name} ${customer.last_name}: $${customer.balance}`);
});
```

## Notas Importantes

1. **Autenticación**: Todos los endpoints requieren autenticación válida
2. **Validación**: Los datos se validan automáticamente en el servidor
3. **Paginación**: Las listas de transacciones incluyen paginación automática
4. **Soft Delete**: Las transacciones eliminadas se marcan como eliminadas pero no se borran físicamente
5. **Cálculo Automático**: Los saldos se calculan automáticamente y se mantienen consistentes
6. **Relaciones**: Las transacciones incluyen información del cliente y usuario que las creó 