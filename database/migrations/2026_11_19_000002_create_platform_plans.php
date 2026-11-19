<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('price_cents');
            $t->unsignedInteger('customer_limit')->nullable();
            $t->unsignedInteger('user_limit')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('tenants', function (Blueprint $t) {
            $t->foreignId('platform_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('active')->index();
            $t->string('document')->nullable();
            $t->string('contact_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropConstrainedForeignId('platform_plan_id');
            $t->dropColumn(['status', 'document', 'contact_email']);
        });
        Schema::dropIfExists('platform_plans');
    }
};
