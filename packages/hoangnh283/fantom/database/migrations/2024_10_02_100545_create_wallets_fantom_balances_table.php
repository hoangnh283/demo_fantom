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
        Schema::create('wallets_fantom_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_id')->constrained('wallets_fantom_address')->onDelete('cascade');
            $table->decimal('ftm', 20, 8);
            $table->decimal('usdt', 20, 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wallets_fantom_balances');
    }
};
