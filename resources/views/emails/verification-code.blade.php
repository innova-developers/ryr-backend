<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código de Verificación</title>
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
            padding: 30px;
            border: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            color: #007bff;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            letter-spacing: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RYR - Código de Verificación</h1>
    </div>
    
    <div class="content">
        <p>Hola,</p>
        
        <p>Has solicitado un código de verificación para acceder a tu cuenta.</p>
        
        <div class="code">{{ $code }}</div>
        
        <p><strong>Este código expira en 10 minutos.</strong></p>
        
        <p>Si no solicitaste este código, puedes ignorar este mensaje.</p>
        
        <p>Saludos,<br>Equipo RYR</p>
    </div>
    
    <div class="footer">
        <p>Este es un mensaje automático, por favor no respondas a este email.</p>
    </div>
</body>
</html> 