<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta de Satisfacción</title>
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
            background-color: #f97316;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #f97316;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #ea580c;
        }
        .info-box {
            background-color: white;
            padding: 15px;
            border-left: 4px solid #f97316;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Gracias por confiar en nosotros!</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $customer->name }}</strong>,</p>
        
        <p>Nos complace informarte que tu envío <strong>#{{ $commission->id }}</strong> ha sido entregado exitosamente.</p>
        
        <div class="info-box">
            <p><strong>Detalles del envío:</strong></p>
            <p>ID de Comisión: #{{ $commission->id }}</p>
            <p>Fecha: {{ $commission->date->format('d/m/Y') }}</p>
        </div>
        
        <p>Tu opinión es muy importante para nosotros. Nos encantaría conocer tu experiencia con nuestro servicio.</p>
        
        <p>Por favor, tómate un momento para completar nuestra breve encuesta de satisfacción:</p>
        
        <div style="text-align: center;">
            <a href="{{ $surveyUrl }}" class="button">Completar Encuesta</a>
        </div>
        
        <p style="font-size: 12px; color: #666; margin-top: 30px;">
            O copia y pega este enlace en tu navegador:<br>
            <a href="{{ $surveyUrl }}" style="color: #f97316;">{{ $surveyUrl }}</a>
        </p>
        
        <p style="margin-top: 30px;">
            Gracias por tu tiempo y por elegir nuestros servicios.
        </p>
        
        <p>
            Saludos cordiales,<br>
            <strong>Equipo RYR Comisiones</strong>
        </p>
    </div>
</body>
</html>
