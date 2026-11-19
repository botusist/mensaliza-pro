<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_integrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained();
            $t->string('provider')->default('automa');
            $t->string('base_url')->default('https://automa.botusist.net.br');
            $t->text('secret')->nullable();
            $t->string('account')->nullable();
            $t->boolean('enabled')->default(false);
            $t->timestamp('last_tested_at')->nullable();
            $t->string('last_status')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_integrations');
    }
};
