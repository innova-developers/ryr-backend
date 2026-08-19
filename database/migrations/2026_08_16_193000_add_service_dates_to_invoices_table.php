<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concepto 2 (servicios) y 3 (productos y servicios) obligan a informarle a ARCA el
 * período facturado y el vencimiento del pago (FchServDesde, FchServHasta y FchVtoPago).
 * ArcaService los mandaba fijos en null y el comprobante no tenía de dónde imprimirlos,
 * así que la factura salía sin período facturado.
 *
 * Se guardan en la factura, y no sólo se calculan al emitir, porque son datos fiscales
 * del comprobante: una vez emitido tiene que poder reimprimirse igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('fecha_servicio_desde')->nullable()->after('fecha_emision');
            $table->date('fecha_servicio_hasta')->nullable()->after('fecha_servicio_desde');
            $table->date('fecha_vto_pago')->nullable()->after('fecha_servicio_hasta');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['fecha_servicio_desde', 'fecha_servicio_hasta', 'fecha_vto_pago']);
        });
    }
};
