# Implementación de Notificaciones en Flutter

## Prompt para la IA de Flutter

```
Necesito implementar el sistema de notificaciones funcional que consuma los siguientes endpoints de la API. El sistema debe mostrar notificaciones en tiempo real, permitir marcarlas como leídas, y mostrar un contador de notificaciones no leídas.

Endpoints disponibles:
1. GET /api/cadete/notifications - Obtener notificaciones
2. GET /api/cadete/notifications/unread-count - Contador de no leídas
3. PATCH /api/cadete/notifications/{id}/mark-as-read - Marcar como leída
4. PATCH /api/cadete/notifications/mark-all-as-read - Marcar todas como leídas

Requisitos:
- Implementar polling cada 30 segundos para actualizar notificaciones
- Mostrar badge con contador de no leídas
- Lista de notificaciones con pull-to-refresh
- Marcar como leída al tocar una notificación
- La autenticación se maneja con tokens Bearer en el header Authorization.

IMPORTANTE: El parámetro unread_only acepta cualquier formato (boolean, string, int):
- true, "true", "TRUE", 1, "1" = true
- false, "false", "FALSE", 0, "0" = false
- null o no enviado = false (por defecto)
```

## Estructura de Datos

### Modelo de Notificación
```dart
class Notification {
  final int id;
  final int userId;
  final int commissionId;
  final String type;
  final String title;
  final String message;
  final Map<String, dynamic>? data;
  final bool isRead;
  final DateTime? readAt;
  final DateTime createdAt;
  final DateTime updatedAt;
  final Commission? commission;

  Notification({
    required this.id,
    required this.userId,
    required this.commissionId,
    required this.type,
    required this.title,
    required this.message,
    this.data,
    required this.isRead,
    this.readAt,
    required this.createdAt,
    required this.updatedAt,
    this.commission,
  });

  factory Notification.fromJson(Map<String, dynamic> json) {
    return Notification(
      id: json['id'],
      userId: json['user_id'],
      commissionId: json['commission_id'],
      type: json['type'],
      title: json['title'],
      message: json['message'],
      data: json['data'],
      isRead: json['is_read'],
      readAt: json['read_at'] != null ? DateTime.parse(json['read_at']) : null,
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: DateTime.parse(json['updated_at']),
      commission: json['commission'] != null ? Commission.fromJson(json['commission']) : null,
    );
  }
}

class Commission {
  final int id;
  final String? clientName;
  final String? origin;
  final String? destination;
  final double? total;

  Commission({
    required this.id,
    this.clientName,
    this.origin,
    this.destination,
    this.total,
  });

  factory Commission.fromJson(Map<String, dynamic> json) {
    return Commission(
      id: json['id'],
      clientName: json['client']?['full_name'] ?? json['client']?['name'],
      origin: json['origin_location']?['name'],
      destination: json['destination_location']?['name'],
      total: json['total']?.toDouble(),
    );
  }
}
```

## Ejemplos de Respuestas de la API

### 1. GET /api/cadete/notifications

**Request:**
```http
GET /api/cadete/notifications?limit=20&offset=0&unread_only=false
Authorization: Bearer {token}
```

**Parámetros aceptados:**
- `limit` (int, 1-100, default: 50)
- `offset` (int, default: 0)
- `unread_only` (cualquier formato: boolean, string, int, null)

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "commission_id": 123,
      "type": "commission_assigned",
      "title": "Nueva comisión asignada",
      "message": "Se te ha asignado una nueva comisión #123",
      "data": {
        "commission_id": 123,
        "status": "CADETE_ASIGNADO",
        "client_name": "Juan Pérez",
        "total": 150.00,
        "origin": "Centro",
        "destination": "Norte",
        "timestamp": "2025-09-26T16:30:00.000Z"
      },
      "is_read": false,
      "read_at": null,
      "created_at": "2025-09-26T16:30:00.000Z",
      "updated_at": "2025-09-26T16:30:00.000Z",
      "commission": {
        "id": 123,
        "client": {
          "name": "Juan",
          "last_name": "Pérez",
          "full_name": "Juan Pérez"
        },
        "origin_location": {
          "name": "Centro"
        },
        "destination_location": {
          "name": "Norte"
        },
        "total": 150.00
      }
    }
  ],
  "count": 1
}
```

### 2. GET /api/cadete/notifications/unread-count

**Request:**
```http
GET /api/cadete/notifications/unread-count
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "unread_count": 3
}
```

### 3. PATCH /api/cadete/notifications/{id}/mark-as-read

**Request:**
```http
PATCH /api/cadete/notifications/1/mark-as-read
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Notificación marcada como leída"
}
```

### 4. PATCH /api/cadete/notifications/mark-all-as-read

**Request:**
```http
PATCH /api/cadete/notifications/mark-all-as-read
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Se marcaron 5 notificaciones como leídas",
  "count": 5
}
```

## Tipos de Notificaciones

### commission_assigned
- **Título:** "Nueva comisión asignada"
- **Mensaje:** "Se te ha asignado una nueva comisión #{commission_id}"
- **Datos adicionales:** Información completa de la comisión

### commission_status_change
- **Título:** Varía según el estado (ej: "En camino al origen", "Encomienda retirada")
- **Mensaje:** "Comisión #{commission_id}: {status_label}"
- **Datos adicionales:** Estado anterior, nuevo estado, detalles

## Estados de Comisión Relevantes para Notificaciones

- `CADETE_ASIGNADO` - Nueva comisión asignada
- `CADETE_EN_CAMINO_ORIGEN` - En camino al origen
- `EN_PUNTO_RETIRO` - En punto de retiro
- `ENCOMIENDA_RETIRADA` - Encomienda retirada
- `EN_CAMINO_PLANTA` - En camino a planta/sucursal
- `EN_TRANSITO_DESTINO` - En tránsito a destino
- `EN_PROCESO_ENTREGA` - En proceso de entrega
- `ENTREGADO` - Comisión entregada
- `RETIRADO_SUCURSAL` - Retirado en sucursal
- `INTENTO_ENTREGA_FALLIDO` - Intento de entrega fallido
- `REPROGRAMANDO_ENTREGA` - Reprogramando entrega
- `DISPONIBLE_RETIRO` - Disponible para retiro
- `EN_DEVOLUCION` - En devolución
- `DEVUELTO_REMITENTE` - Devuelto al remitente

## Consideraciones de Implementación

1. **Polling:** Implementar actualización automática cada 30 segundos
2. **Cache:** Guardar notificaciones localmente para mostrar offline
3. **Badge:** Mostrar contador de no leídas en el ícono de la app
4. **Pull-to-refresh:** Permitir actualización manual
5. **Navegación:** Al tocar una notificación, navegar a la comisión correspondiente
6. **Estados de carga:** Mostrar indicadores durante las peticiones
7. **Manejo de errores:** Mostrar mensajes de error amigables
8. **Paginación:** Implementar carga infinita o paginación
9. **Filtros:** Permitir filtrar por tipo de notificación
10. **Ordenamiento:** Mostrar las más recientes primero

## Ejemplo de Servicio en Flutter

```dart
class NotificationService {
  static const String baseUrl = 'https://tu-api.com/api/cadete/notifications';
  
  Future<List<Notification>> getNotifications({
    int limit = 50,
    int offset = 0,
    bool unreadOnly = false,
  }) async {
    final response = await http.get(
      Uri.parse('$baseUrl?limit=$limit&offset=$offset&unread_only=$unreadOnly'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success']) {
        return (data['data'] as List)
            .map((json) => Notification.fromJson(json))
            .toList();
      }
    }
    throw Exception('Error al obtener notificaciones');
  }
  
  Future<int> getUnreadCount() async {
    final response = await http.get(
      Uri.parse('$baseUrl/unread-count'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success']) {
        return data['unread_count'];
      }
    }
    throw Exception('Error al obtener contador');
  }
  
  Future<bool> markAsRead(int notificationId) async {
    final response = await http.patch(
      Uri.parse('$baseUrl/$notificationId/mark-as-read'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return data['success'];
    }
    return false;
  }
  
  Future<int> markAllAsRead() async {
    final response = await http.patch(
      Uri.parse('$baseUrl/mark-all-as-read'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success']) {
        return data['count'];
      }
    }
    return 0;
  }
}
```

## Notas Importantes

- **El parámetro `unread_only` es muy flexible** y acepta cualquier formato que Flutter envíe
- **Las notificaciones se crean automáticamente** cuando cambia el estado de una comisión
- **El sistema está completamente probado** y funcionando en Docker
- **Los endpoints están protegidos** con autenticación Bearer
- **La respuesta siempre incluye el campo `success`** para verificar el estado de la operación