# Funcionalidad "A Cuenta" en Comisiones - Documentación

## Descripción

Se ha agregado un parámetro opcional `a_cuenta` a la creación de comisiones que permite registrar automáticamente el monto de la comisión como una deuda en la cuenta corriente del cliente.

## Funcionalidad

### Parámetro `a_cuenta`

- **Tipo**: `boolean`
- **Obligatorio**: No (opcional)
- **Valor por defecto**: `false`
- **Descripción**: Cuando es `true`, crea automáticamente una transacción de débito en la cuenta corriente del cliente por el monto total de la comisión.

### Comportamiento

1. **`a_cuenta = true`**: 
   - Se crea la comisión normalmente
   - Se crea automáticamente una transacción de débito en la cuenta corriente del cliente
   - El saldo del cliente se reduce por el monto de la comisión
   - La transacción incluye referencia a la comisión creada

2. **`a_cuenta = false` o no especificado**:
   - Se crea la comisión normalmente
   - No se afecta la cuenta corriente del cliente
   - El saldo del cliente permanece sin cambios

## Ejemplos de Uso

### Crear comisión a cuenta (con deuda)

```json
POST /api/commissions
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
    }
  ],
  "total": 1000,
  "a_cuenta": true
}
```

**Resultado:**
- Comisión creada con ID #1
- Transacción de cuenta corriente creada:
  - Tipo: `debit` (débito)
  - Monto: 1000
  - Descripción: "Comisión #1 - Buenos Aires a Córdoba"
  - Referencia: "COM-1"
  - Saldo del cliente: -1000

### Crear comisión sin afectar cuenta corriente

```json
POST /api/commissions
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
    }
  ],
  "total": 1000,
  "a_cuenta": false
}
```

**Resultado:**
- Comisión creada con ID #1
- No se crea transacción en cuenta corriente
- Saldo del cliente: sin cambios

### Crear comisión sin especificar a_cuenta (comportamiento por defecto)

```json
POST /api/commissions
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
    }
  ],
  "total": 1000
}
```

**Resultado:**
- Comisión creada con ID #1
- No se crea transacción en cuenta corriente (a_cuenta = false por defecto)
- Saldo del cliente: sin cambios

## Transacciones de Cuenta Corriente Creadas

Cuando `a_cuenta = true`, se crea automáticamente una transacción con los siguientes datos:

- **Tipo**: `debit` (débito/egreso)
- **Monto**: Igual al total de la comisión
- **Descripción**: "Comisión #[ID] - [Origen] a [Destino]"
- **Referencia**: "COM-[ID]"
- **Fecha**: Misma fecha de la comisión
- **Método de pago**: `null`
- **Observaciones**: "Comisión registrada a cuenta corriente"
- **Usuario**: Usuario que creó la comisión

## Validación

- El campo `a_cuenta` es opcional
- Si se proporciona, debe ser un valor booleano válido (`true` o `false`)
- Si no se proporciona, se asume `false`

## Casos de Uso

### Caso 1: Cliente paga al contado
- Crear comisión sin `a_cuenta` o con `a_cuenta: false`
- El cliente paga inmediatamente
- No se afecta su cuenta corriente

### Caso 2: Cliente paga a cuenta
- Crear comisión con `a_cuenta: true`
- Se registra automáticamente la deuda
- El cliente puede pagar posteriormente registrando un crédito en su cuenta corriente

### Caso 3: Múltiples comisiones a cuenta
- Cada comisión con `a_cuenta: true` suma a la deuda total
- El saldo negativo se acumula
- Se pueden ver todas las transacciones en el historial de cuenta corriente

## Compatibilidad

Esta funcionalidad es completamente compatible con el comportamiento existente:

- Las comisiones existentes no se ven afectadas
- El parámetro es opcional, por lo que no requiere cambios en el frontend existente
- Los tests existentes siguen funcionando sin modificaciones
- La funcionalidad se puede activar gradualmente según las necesidades

## Tests

Se han creado tests específicos que cubren:

- Crear comisión con `a_cuenta: true`
- Crear comisión con `a_cuenta: false`
- Crear comisión sin especificar `a_cuenta`
- Múltiples comisiones acumulando saldo
- Validación del campo `a_cuenta`

Todos los tests pasan exitosamente. 