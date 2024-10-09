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
        Schema::create('wallets_fantom_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('from_address');
            $table->string('type');
            $table->string('to_address')->nullable();
            $table->string('hash')->nullable();
            $table->decimal('amount', 20, 8);
            $table->decimal('gas', 20, 8);
            $table->decimal('gas_price', 20, 8);
            $table->string('status');
            $table->string('block_number');
            $table->string('nonce');
            $table->string('currency');
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
        Schema::dropIfExists('wallets_fantom_transactions');
    }
};
