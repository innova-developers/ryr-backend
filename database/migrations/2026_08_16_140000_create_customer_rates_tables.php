<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-484 (TARIFAS ESPECIALES).
 *
 * Hasta acá los precios vivían únicamente en destinations (fixed_price,
 * small_bulk_price, large_bulk_price). La clasificación individual/empresa del
 * cliente era sólo identificatoria: los dos pagaban la misma tabla.
 *
 * customer_rates guarda la tarifa particular de un cliente. destination_id nulo
 * significa "para todos los destinos"; con destination_id se define una tarifa
 * para una ruta puntual, que tiene prioridad sobre la general del cliente.
 *
 * customer_rate_tiers cubre el precio por cantidad/volumen: a partir de N bultos
 * de un tamaño, rige otro precio unitario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // Nulo = aplica a cualquier destino.
            $table->foreignId('destination_id')->nullable()->constrained('destinations')->nullOnDelete();

            // Cada precio es opcional: lo que no se define cae a la tabla general.
            $table->decimal('fixed_price', 12, 2)->nullable();       // precio de base
            $table->decimal('small_bulk_price', 12, 2)->nullable();  // bulto chico
            $table->decimal('large_bulk_price', 12, 2)->nullable();  // bulto grande

            // Acuerdo particular: precio cerrado de la comisión, ignora bultos y base.
            $table->decimal('agreement_price', 12, 2)->nullable();

            // Porcentaje sobre valor declarado, que se suma al total.
            $table->decimal('declared_value_percentage', 5, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'destination_id'], 'customer_rates_customer_destination_unique');
            $table->index('is_active');
        });

        Schema::create('customer_rate_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_rate_id')->constrained('customer_rates')->cascadeOnDelete();
            // CHICO o GRANDE
            $table->string('size', 20);
            // Desde esta cantidad de bultos (inclusive) rige unit_price.
            $table->unsignedInteger('min_quantity');
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();

            $table->index(['customer_rate_id', 'size', 'min_quantity'], 'customer_rate_tiers_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rate_tiers');
        Schema::dropIfExists('customer_rates');
    }
};
