<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 13px; color: #000; padding: 32px 40px; }

        .header-box { border: 2px solid #000; border-radius: 4px; overflow: hidden; margin-bottom: 20px; }
        .header-grid { display: table; width: 100%; }
        .header-cell { display: table-cell; vertical-align: top; padding: 16px 20px; }
        .header-cell-center { display: table-cell; vertical-align: middle; text-align: center; padding: 12px 28px; border-left: 2px solid #000; border-right: 2px solid #000; }
        .header-cell:first-child { border-right: none; }

        .emisor-name { font-weight: 700; font-size: 18px; margin-bottom: 8px; }
        .emisor-detail { font-size: 12px; color: #444; line-height: 1.7; }

        .letter-box { width: 52px; height: 52px; border: 3px solid #000; border-radius: 6px; display: inline-block; text-align: center; line-height: 52px; font-size: 32px; font-weight: 700; }
        .letter-code { font-size: 11px; color: #666; margin-top: 4px; }

        .comprobante-title { font-weight: 700; font-size: 16px; margin-bottom: 10px; text-transform: uppercase; }
        .comprobante-detail { font-size: 13px; line-height: 1.8; }

        .client-box { border: 1px solid #ccc; border-radius: 4px; padding: 14px 20px; margin-bottom: 20px; }
        .client-title { font-weight: 700; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .client-grid { display: table; width: 100%; font-size: 13px; line-height: 1.6; }
        .client-row { display: table-row; }
        .client-cell { display: table-cell; padding: 3px 0; width: 50%; }

        table.detail { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        table.detail th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #000; font-weight: 700; background: #f5f5f5; }
        table.detail th.right { text-align: right; }
        table.detail th.center { text-align: center; }
        table.detail td { padding: 10px 12px; border-bottom: 1px solid #ddd; }
        table.detail td.right { text-align: right; }
        table.detail td.center { text-align: center; }

        table.totals { margin-left: auto; border-collapse: collapse; font-size: 13px; min-width: 320px; }
        table.totals td { padding: 8px 16px; }
        table.totals td.right { text-align: right; }
        table.totals tr.total-row { border-top: 2px solid #000; }
        table.totals tr.total-row td { padding: 10px 16px; font-weight: 700; font-size: 16px; }

        .obs-box { font-size: 12px; color: #555; margin-bottom: 20px; padding: 10px 16px; background: #fafafa; border-radius: 4px; border: 1px solid #eee; }

        .footer { border-top: 2px solid #ddd; padding-top: 20px; margin-top: 20px; text-align: center; }
        .footer-cae { font-size: 12px; color: #555; display: inline-block; text-align: left; }
        .footer-brand { font-size: 10px; color: #999; margin-top: 12px; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header-box">
        <div class="header-grid">
            <div class="header-cell">
                <div class="emisor-name">{{ $emisor['razon_social'] }}</div>
                <div class="emisor-detail">
                    <div><strong>C.U.I.T.:</strong> {{ $emisor['cuit'] }}</div>
                    <div><strong>Domicilio:</strong> {{ $emisor['domicilio'] }}</div>
                    <div><strong>Cond. IVA:</strong> {{ $emisor['condicion_iva'] }}</div>
                </div>
            </div>
            <div class="header-cell-center">
                <div class="letter-box">{{ $letter }}</div>
                <div class="letter-code">Cód. {{ str_pad($invoice->tipo_comprobante, 2, '0', STR_PAD_LEFT) }}</div>
            </div>
            <div class="header-cell">
                <div class="comprobante-title">{{ $label }}</div>
                <div class="comprobante-detail">
                    <div><strong>Nº:</strong> {{ $formatted_number }}</div>
                    <div><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($invoice->fecha_emision)->format('d/m/Y') }}</div>
                    <div><strong>Punto de Venta:</strong> {{ str_pad($invoice->punto_venta, 5, '0', STR_PAD_LEFT) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- CLIENT --}}
    <div class="client-box">
        <div class="client-title">Datos del Cliente</div>
        <div class="client-grid">
            <div class="client-row">
                <div class="client-cell"><strong>Razón Social:</strong> {{ strtoupper($invoice->razon_social ?? '') }}</div>
                <div class="client-cell"><strong>Condición IVA:</strong> {{ $invoice->condicion_iva ?? 'Consumidor Final' }}</div>
            </div>
            <div class="client-row">
                <div class="client-cell"><strong>Domicilio:</strong> {{ $invoice->domicilio_cliente ?? '—' }}</div>
                <div class="client-cell"><strong>{{ $doc_label }}:</strong> {{ $invoice->doc_numero ?? '—' }}</div>
            </div>
        </div>
    </div>

    {{-- DETAIL TABLE --}}
    <table class="detail">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="center" style="width:70px">Cant.</th>
                <th class="right" style="width:120px">Precio Unit.</th>
                <th class="right" style="width:80px">% IVA</th>
                <th class="right" style="width:120px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $description }}</td>
                <td class="center">1</td>
                <td class="right">$ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
                <td class="right">{{ $invoice->iva_rate }}%</td>
                <td class="right">$ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- TOTALS --}}
    <table class="totals">
        <tr>
            <td>Subtotal sin IVA:</td>
            <td class="right">$ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>IVA {{ $invoice->iva_rate }}% (Base: $ {{ number_format($invoice->importe_neto, 2, ',', '.') }}):</td>
            <td class="right">$ {{ number_format($invoice->importe_iva, 2, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td>TOTAL:</td>
            <td class="right">$ {{ number_format($invoice->importe_total, 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- OBSERVATIONS --}}
    @if($invoice->observaciones)
    <div class="obs-box">
        <strong>Observaciones:</strong> {{ $invoice->observaciones }}
    </div>
    @endif

    {{-- ARCA FOOTER --}}
    <div class="footer">
        <div class="footer-cae">
            <div><strong>CAE:</strong> {{ $invoice->cae }}</div>
            <div><strong>Fecha Vto. CAE:</strong> {{ $invoice->cae_vencimiento }}</div>
        </div>
        <div class="footer-brand">
            Emitido con sistema RYR Comisiones — Innova Developers
        </div>
    </div>

</body>
</html>
