<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->string('name');
            $t->integer('days_offset');
            $t->time('send_time')->default('09:00');
            $t->text('template');
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('daily_limit')->default(100);
            $t->timestamps();
            $t->unique(['tenant_id', 'days_offset']);
        });
        Schema::table('message_outbox', function (Blueprint $t) {
            $t->foreignId('reminder_rule_id')->nullable()->after('charge_id')->constrained();
            $t->string('recipient')->nullable();
            $t->text('message')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('message_outbox', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reminder_rule_id');
            $t->dropColumn(['recipient', 'message', 'sent_at', 'attempts']);
        });
        Schema::dropIfExists('reminder_rules');
    }
};
