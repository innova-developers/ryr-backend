<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Envío Actualizado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            font-size: 14px;
            color: #6c757d;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background-color: #28a745;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        .tracking-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .commission-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .commission-details h3 {
            margin-top: 0;
            color: #495057;
        }
        .commission-details p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚚 RYR Comisiones</h1>
        <p>Actualización de Estado de Envío</p>
    </div>

    <div class="content">
        <h2>Hola {{ $customer->name }} {{ $customer->last_name }},</h2>
        
        <p>{{ $statusMessage }}</p>

        <div class="status-badge">
            Estado: {{ $newStatus }}
        </div>

        @if($details)
            <p><strong>Detalles adicionales:</strong> {{ $details }}</p>
        @endif

        <div class="commission-details">
            <h3>📦 Detalles del Envío #{{ $commission->id }}</h3>
            <p><strong>Origen:</strong> {{ $commission->originLocation->name ?? 'N/A' }}</p>
            <p><strong>Dirección Origen:</strong> {{ $commission->originLocation->address ?? 'N/A' }}</p>
            <p><strong>Ciudad Origen:</strong> {{ $commission->originLocation->origin ?? 'N/A' }}</p>
            <p><strong>Teléfono Origen:</strong> {{ $commission->originLocation->phone ?? 'N/A' }}</p>
            <p><strong>Horario Origen:</strong> {{ $commission->originLocation->schedule ?? 'N/A' }}</p>
            <br>
            <p><strong>Destino:</strong> {{ $commission->destinationLocation->name ?? 'N/A' }}</p>
            <p><strong>Dirección Destino:</strong> {{ $commission->destinationLocation->address ?? 'N/A' }}</p>
            <p><strong>Ciudad Destino:</strong> {{ $commission->destinationLocation->origin ?? 'N/A' }}</p>
            <p><strong>Teléfono Destino:</strong> {{ $commission->destinationLocation->phone ?? 'N/A' }}</p>
            <p><strong>Horario Destino:</strong> {{ $commission->destinationLocation->schedule ?? 'N/A' }}</p>
            <br>
            <p><strong>Fecha:</strong> {{ $commission->date->format('d/m/Y') }}</p>
            <p><strong>Total:</strong> ${{ number_format($commission->total, 2) }}</p>
        </div>

        <p>Puedes hacer seguimiento de tu envío y gestionar tus comisiones desde nuestra plataforma web:</p>
        
        <a href="{{ $trackingUrl }}" class="tracking-button">
            🔍 Hacer Seguimiento
        </a>

        <p>También puedes acceder a tu panel de cliente para ver todos tus envíos y gestiones.</p>
    </div>

    <div class="footer">
        <p>Este es un mensaje automático de RYR Comisiones.</p>
        <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
        <p>© {{ date('Y') }} RYR Comisiones. Todos los derechos reservados.</p>
    </div>
</body>
</html> 