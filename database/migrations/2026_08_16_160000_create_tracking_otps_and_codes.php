<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RC-500 (TRACKING PUBLICO).
 *
 * Dos piezas:
 *
 * 1. commissions.tracking_code — identificador opaco. El id es un entero secuencial,
 *    así que cualquiera podía recorrer 1..9353 y bajarse la agenda de clientes.
 *
 * 2. tracking_otps — códigos de un solo uso para ver el detalle. Se guarda el HASH
 *    del código, no el código: si se filtra la tabla, no sirve para entrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commissions', 'tracking_code')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->string('tracking_code', 32)->nullable()->unique()->after('id');
            });
        }

        Schema::create('tracking_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
            $table->string('code_hash');
            // Canales a los que se envió, para poder informar "te lo mandamos por X"
            // sin exponer el teléfono ni el mail completos.
            $table->string('sent_to_masked')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['commission_id', 'expires_at']);
        });

        $this->backfillTrackingCodes();
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_otps');

        if (Schema::hasColumn('commissions', 'tracking_code')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->dropColumn('tracking_code');
            });
        }
    }

    /**
     * Código opaco para las comisiones existentes. Se procesa por lotes para no
     * cargar las 9353 filas en memoria de una.
     */
    private function backfillTrackingCodes(): void
    {
        DB::table('commissions')
            ->whereNull('tracking_code')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('commissions')
                        ->where('id', $row->id)
                        ->update(['tracking_code' => $this->generarCodigo()]);
                }
            });
    }

    private function generarCodigo(): string
    {
        // Sin caracteres ambiguos (0/O, 1/I) porque el código se dicta por teléfono.
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';

        for ($i = 0; $i < 12; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $codigo;
    }
};
