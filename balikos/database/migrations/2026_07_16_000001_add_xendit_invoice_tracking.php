<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihans', function (Blueprint $table) {
            $table->string('gateway_provider')->nullable()->after('metode_pembayaran')->index();
            $table->string('gateway_reference')->nullable()->after('gateway_provider')->unique();
            $table->string('gateway_invoice_id')->nullable()->after('gateway_reference')->unique();
            $table->string('gateway_invoice_url')->nullable()->after('gateway_invoice_id');
            $table->string('gateway_payment_id')->nullable()->after('gateway_invoice_url')->index();
            $table->string('gateway_status')->nullable()->after('gateway_payment_id')->index();
            $table->unsignedBigInteger('gateway_paid_amount')->nullable()->after('gateway_status');
            $table->json('gateway_payload')->nullable()->after('gateway_paid_amount');
        });

        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('event_key')->unique();
            $table->string('event_type')->nullable()->index();
            $table->string('gateway_reference')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->foreignId('tagihan_id')->nullable()->constrained('tagihans')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_events');

        Schema::table('tagihans', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_provider',
                'gateway_reference',
                'gateway_invoice_id',
                'gateway_invoice_url',
                'gateway_payment_id',
                'gateway_status',
                'gateway_paid_amount',
                'gateway_payload',
            ]);
        });
    }
};
