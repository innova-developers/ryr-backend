<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <div style="text-align: center; padding: 20px 0; border-bottom: 2px solid #dc2626;">
        <h2 style="margin: 0; color: #dc2626;">{{ $emisorName }}</h2>
    </div>

    <div style="padding: 24px 0;">
        <p>Estimado/a <strong>{{ $customerName }}</strong>,</p>
        <p>Adjuntamos su comprobante <strong>{{ $invoiceLabel }} Nº {{ $invoiceNumber }}</strong>.</p>
        <p>Encontrará el documento en formato PDF adjunto a este correo.</p>
    </div>

    <div style="border-top: 1px solid #ddd; padding-top: 16px; font-size: 12px; color: #999; text-align: center;">
        <p>{{ $emisorName }} — Sistema RYR Comisiones</p>
    </div>
</body>
</html>
