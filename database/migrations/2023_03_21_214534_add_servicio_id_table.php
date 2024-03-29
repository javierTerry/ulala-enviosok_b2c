<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rango_guias', function (Blueprint $table) {
            $table->unsignedTinyInteger('servicio_id')->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rango_guias', function (Blueprint $table) {
            $table->dropColumn('servicio_id');
        });
    }
    
};
