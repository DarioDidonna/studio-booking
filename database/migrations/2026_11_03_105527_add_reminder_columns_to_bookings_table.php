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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminder_due_at')->nullable()->index()->after('cancelled_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['reminder_due_at']);
            $table->dropColumn(['reminder_due_at', 'reminder_sent_at']);
        });
    }
};
