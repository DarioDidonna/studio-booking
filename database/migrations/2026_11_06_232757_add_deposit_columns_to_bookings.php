<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online bookings take a deposit through Stripe Checkout. A booking waits as
 * pending until Stripe confirms the payment, and holds its slot until then.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('deposit_cents')->nullable()->after('price_cents');
            $table->string('deposit_status')->nullable()->after('deposit_cents');
            $table->string('stripe_checkout_session_id')->nullable()->unique()->after('deposit_status');
            $table->text('stripe_checkout_url')->nullable()->after('stripe_checkout_session_id');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_checkout_url');
            $table->timestamp('payment_due_at')->nullable()->index()->after('stripe_payment_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['stripe_checkout_session_id']);
            $table->dropIndex(['payment_due_at']);
            $table->dropColumn(['deposit_cents', 'deposit_status', 'stripe_checkout_session_id', 'stripe_checkout_url', 'stripe_payment_intent_id', 'payment_due_at']);
        });
    }
};
