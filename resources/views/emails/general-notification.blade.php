<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
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
        .message-box {
            background-color: white;
            padding: 20px;
            border-left: 4px solid #f97316;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
    </div>
    <div class="content">
        @if($cadeteName)
            <p>Hola <strong>{{ $cadeteName }}</strong>,</p>
        @else
            <p>Hola,</p>
        @endif
        
        <div class="message-box">
            <p style="white-space: pre-wrap;">{{ $message }}</p>
        </div>
        
        <p style="margin-top: 30px;">
            Saludos cordiales,<br>
            <strong>Equipo RYR Comisiones</strong>
        </p>
    </div>
</body>
</html>
