<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paddle_payouts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('reference');
            $table->string('region'); // usually "us" and "row"

            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->string('invoice_link');
            $table->string('invoice_attachment');

            $table->unsignedBigInteger('paddle_account_id');
            $table->foreign('paddle_account_id')->references('id')->on('paddle_accounts')->onDelete('cascade');

            $table->unique(['reference', 'region']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paddle_payouts');
    }
};
