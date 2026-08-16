<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-483 (SUCURSALES).
 *
 * La sucursal que carga una comisión conserva la propiedad administrativa (eso ya
 * funcionaba: commissions.branch_id se fija al crear y ningún flujo lo modifica).
 * Lo que no se podía era DERIVAR la ejecución: el pool de comisiones disponibles
 * filtraba estricto por la sucursal del cadete, así que un cadete de otra sucursal
 * no podía ver ni tomar el trabajo.
 *
 * Acá se declara qué localidades atiende cada sucursal. Una comisión entra al pool
 * de una sucursal si es suya O si su origen/destino cae en una localidad que esa
 * sucursal atiende. La propiedad no se toca.
 *
 * Sin filas para una sucursal, el comportamiento es el de siempre (sólo lo propio),
 * así que habilitar esto es una decisión explícita por sucursal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('locality');
            $table->timestamps();

            $table->unique(['branch_id', 'locality']);
            $table->index('locality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_localities');
    }
};
