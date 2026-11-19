<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('customer_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $t->index(['tenant_id', 'role']);
        });
        Schema::create('email_reminder_outbox', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('charge_id')->constrained();
            $t->foreignId('reminder_rule_id')->constrained();
            $t->string('recipient');
            $t->string('subject');
            $t->text('message');
            $t->timestamp('scheduled_at');
            $t->string('status')->default('pending');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'charge_id', 'reminder_rule_id', 'scheduled_at'], 'email_reminder_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_reminder_outbox');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_id');
            $t->dropIndex(['tenant_id', 'role']);
        });
    }
};
