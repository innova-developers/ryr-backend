<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* La estructura del encabezado (recuadro negro con la letra centrada) la
           impone ARCA. La marca entra por los acentos: rojo #B91117, no por mover
           los bloques obligatorios. */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12.5px; color: #111; padding: 26px 34px 150px 34px; }

        .doc-kind { text-align: right; font-size: 10px; letter-spacing: 1.5px; color: #666; text-transform: uppercase; margin-bottom: 6px; }

        .header-box { border: 2px solid #000; border-radius: 4px; overflow: hidden; }
        .header-grid { display: table; width: 100%; }
        .header-cell { display: table-cell; vertical-align: top; padding: 14px 16px; width: 39%; }
        .header-cell-center { display: table-cell; vertical-align: middle; text-align: center; padding: 10px 18px; border-left: 2px solid #000; border-right: 2px solid #000; width: 22%; }

        .emisor-head { display: table; width: 100%; margin-bottom: 8px; }
        .emisor-logo-cell { display: table-cell; vertical-align: middle; width: 48px; }
        .emisor-logo { width: 44px; height: auto; }
        .emisor-name-cell { display: table-cell; vertical-align: middle; padding-left: 9px; }
        .emisor-name { font-weight: 700; font-size: 14px; line-height: 1.2; }
        .emisor-detail { font-size: 11px; color: #444; line-height: 1.7; }

        .letter-box { width: 50px; height: 50px; border: 3px solid #000; border-radius: 6px; display: inline-block; text-align: center; line-height: 50px; font-size: 30px; font-weight: 700; }
        .letter-code { font-size: 10px; color: #666; margin-top: 4px; }

        .comprobante-title { font-weight: 700; font-size: 15px; margin-bottom: 8px; text-transform: uppercase; color: #B91117; }
        .comprobante-detail { font-size: 12.5px; line-height: 1.8; }

        /* Regla de marca que cierra el encabezado obligatorio. */
        .brand-rule { height: 3px; background: #B91117; border-radius: 2px; margin: 14px 0 16px 0; }

        .section-title { font-weight: 700; font-size: 10px; color: #B91117; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }

        /* Bloque obligatorio en comprobantes de servicios (concepto 2 y 3). */
        .periodo-box { display: table; width: 100%; font-size: 11.5px; margin-bottom: 14px; padding: 8px 16px; background: #f7f7f7; border-radius: 4px; }
        .periodo-cell { display: table-cell; width: 33.33%; }
        .periodo-label { color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        .client-box { border: 1px solid #ddd; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; }
        .client-grid { display: table; width: 100%; font-size: 12.5px; line-height: 1.6; }
        .client-row { display: table-row; }
        .client-cell { display: table-cell; padding: 3px 0; width: 50%; }
        .client-label { color: #666; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        table.detail th { text-align: left; padding: 9px 12px; background: #B91117; color: #fff; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; }
        table.detail th:first-child { border-top-left-radius: 3px; }
        table.detail th:last-child { border-top-right-radius: 3px; }
        table.detail th.right { text-align: right; }
        table.detail th.center { text-align: center; }
        table.detail td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        table.detail td.right { text-align: right; }
        table.detail td.center { text-align: center; }
        /* Sostiene el alto del cuerpo para que los totales no floten a media hoja
           cuando el comprobante tiene un solo renglón. */
        table.detail td.filler { height: 215px; border-bottom: 1px solid #eee; }

        table.totals { margin-left: auto; border-collapse: collapse; font-size: 12.5px; min-width: 300px; margin-top: 16px; }
        table.totals td { padding: 7px 14px; }
        table.totals td.label { color: #555; }
        table.totals td.right { text-align: right; }
        table.totals tr.total-row td { border-top: 2px solid #B91117; padding: 10px 14px; font-weight: 700; font-size: 16px; color: #B91117; }

        .obs-box { font-size: 11.5px; color: #555; margin-top: 16px; padding: 10px 14px; background: #fafafa; border-left: 3px solid #B91117; border-radius: 0 3px 3px 0; }

        /* Pie anclado abajo: antes el bloque del CAE quedaba flotando a media hoja. */
        .footer { position: fixed; bottom: 22px; left: 34px; right: 34px; height: 128px; padding-top: 10px; border-top: 1px solid #ddd; }
        .footer-grid { display: table; width: 100%; }
        .footer-qr-cell { display: table-cell; vertical-align: top; width: 118px; }
        .footer-qr { width: 108px; height: 108px; }
        .footer-data-cell { display: table-cell; vertical-align: top; padding-left: 14px; }
        .footer-legend { font-size: 9px; color: #888; line-height: 1.4; }
        .cae-label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.8px; }
        .cae-value { font-size: 17px; font-weight: 700; letter-spacing: 0.5px; }
        .cae-vto { font-size: 11.5px; color: #444; margin-top: 2px; }
        .footer-brand { position: absolute; bottom: 0; left: 0; right: 0; font-size: 9px; color: #999; text-align: center; padding-bottom: 4px; }
    </style>
</head>
<body>

    {{-- ARCA exige distinguir el ejemplar. El PDF que descarga el sistema es el original. --}}
    <div class="doc-kind">Original</div>

    {{-- HEADER --}}
    <div class="header-box">
        <div class="header-grid">
            <div class="header-cell">
                <div class="emisor-head">
                    @if(!empty($logo_data_uri ?? null))
                        <div class="emisor-logo-cell">
                            <img src="{{ $logo_data_uri }}" class="emisor-logo" alt="">
                        </div>
                    @endif
                    <div class="emisor-name-cell">
                        <div class="emisor-name">{{ $emisor['razon_social'] }}</div>
                    </div>
                </div>
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

    <div class="brand-rule"></div>

    {{-- PERIODO FACTURADO: obligatorio para concepto 2 y 3 --}}
    @if(!empty($periodo ?? null))
    <div class="periodo-box">
        <div class="periodo-cell">
            <div class="periodo-label">Período facturado desde</div>
            <strong>{{ $periodo['desde'] }}</strong>
        </div>
        <div class="periodo-cell">
            <div class="periodo-label">Hasta</div>
            <strong>{{ $periodo['hasta'] }}</strong>
        </div>
        <div class="periodo-cell">
            <div class="periodo-label">Fecha Vto. para el pago</div>
            <strong>{{ $periodo['vto_pago'] ?? '—' }}</strong>
        </div>
    </div>
    @endif

    {{-- CLIENT --}}
    <div class="section-title">Datos del cliente</div>
    <div class="client-box">
        <div class="client-grid">
            <div class="client-row">
                <div class="client-cell"><span class="client-label">Razón Social:</span> <strong>{{ strtoupper($invoice->razon_social ?? '') }}</strong></div>
                <div class="client-cell"><span class="client-label">Condición IVA:</span> {{ $invoice->condicion_iva ?? 'Consumidor Final' }}</div>
            </div>
            <div class="client-row">
                <div class="client-cell"><span class="client-label">Domicilio:</span> {{ $invoice->domicilio_cliente ?: '—' }}</div>
                <div class="client-cell"><span class="client-label">{{ $doc_label }}:</span> {{ $invoice->doc_numero ?: '—' }}</div>
            </div>
        </div>
    </div>

    {{-- DETAIL TABLE --}}
    <div class="section-title">Detalle</div>
    <table class="detail">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="center" style="width:60px">Cant.</th>
                <th class="right" style="width:110px">Precio Unit.</th>
                <th class="right" style="width:70px">% IVA</th>
                <th class="right" style="width:110px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $description }}</td>
                <td class="center">1</td>
                <td class="right">$ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
                <td class="right">{{ number_format($invoice->iva_rate, 2, ',', '.') }}%</td>
                <td class="right">$ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="filler" colspan="5"></td>
            </tr>
        </tbody>
    </table>

    {{-- TOTALS --}}
    <table class="totals">
        <tr>
            <td class="label">Subtotal sin IVA</td>
            <td class="right">$ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">IVA {{ number_format($invoice->iva_rate, 2, ',', '.') }}% sobre $ {{ number_format($invoice->importe_neto, 2, ',', '.') }}</td>
            <td class="right">$ {{ number_format($invoice->importe_iva, 2, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right">$ {{ number_format($invoice->importe_total, 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- OBSERVATIONS --}}
    @if($invoice->observaciones)
    <div class="obs-box">
        <strong>Observaciones:</strong> {{ $invoice->observaciones }}
    </div>
    @endif

    {{-- ARCA FOOTER: QR de constatación (RG 4892/2020) + CAE --}}
    <div class="footer">
        <div class="footer-grid">
            @if(!empty($qr_data_uri ?? null))
                <div class="footer-qr-cell">
                    <img src="{{ $qr_data_uri }}" class="footer-qr" alt="">
                </div>
            @endif
            <div class="footer-data-cell">
                <div class="cae-label">CAE Nº</div>
                <div class="cae-value">{{ $invoice->cae ?: '—' }}</div>
                <div class="cae-vto">Fecha de Vto. del CAE: <strong>{{ $cae_vencimiento_fmt ?? '—' }}</strong></div>
                @if(!empty($qr_data_uri ?? null))
                    <div class="footer-legend" style="margin-top:8px">
                        Comprobante autorizado por ARCA. Escaneá el código QR para constatar su validez
                        en el sitio oficial (RG 4892/2020).
                    </div>
                @endif
            </div>
        </div>
        <div class="footer-brand">
            Emitido con sistema RYR Comisiones — Innova Developers
        </div>
    </div>

</body>
</html>
