<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu opinión nos importa</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#ffffff;border-radius:12px;padding:32px;">
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <h1 style="margin:0;font-size:22px;color:#111827;">🚚 R&amp;R Comisiones</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:16px;color:#374151;line-height:1.6;">
                            <p style="margin:0 0 12px;">Hola {{ $customerName }},</p>
                            <p style="margin:0 0 12px;">
                                Tu envío <strong>#{{ $commissionId }}</strong> fue entregado.
                                ¿Cómo fue tu experiencia?
                            </p>
                            <p style="margin:0 0 24px;">
                                Son 10 segundos y nos ayuda muchísimo a mejorar el servicio.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <a href="{{ $feedbackUrl }}"
                               style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-size:16px;font-weight:bold;">
                                Dejar mi opinión
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;line-height:1.6;">
                            <p style="margin:0 0 8px;">Si el botón no funciona, copiá y pegá este enlace:</p>
                            <p style="margin:0;word-break:break-all;">
                                <a href="{{ $feedbackUrl }}" style="color:#2563eb;">{{ $feedbackUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:28px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">
                            ¡Gracias por confiar en R&amp;R Comisiones!
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
