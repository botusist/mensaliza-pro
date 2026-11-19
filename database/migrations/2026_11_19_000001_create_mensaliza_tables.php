<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('timezone')->default('America/Sao_Paulo'), $t->timestamps()]);
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained();
            $t->string('role')->default('admin');
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->string('name');
            $t->string('document')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('category')->default('Cliente');
            $t->string('status')->default('active');
            $t->boolean('contact_opt_in')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->string('name');
            $t->unsignedBigInteger('amount_cents');
            $t->string('periodicity')->default('monthly');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('payer_id')->constrained('customers');
            $t->foreignId('plan_id')->constrained();
            $t->date('starts_at');
            $t->unsignedTinyInteger('due_day');
            $t->string('status')->default('active');
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
        Schema::create('charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('contract_id')->constrained();
            $t->foreignId('customer_id')->constrained();
            $t->date('competence');
            $t->date('due_date');
            $t->string('description');
            $t->unsignedBigInteger('amount_cents');
            $t->string('status')->default('open');
            $t->timestamps();
            $t->unique(['tenant_id', 'contract_id', 'competence']);
            $t->index(['tenant_id', 'due_date', 'status']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('charge_id')->constrained();
            $t->unsignedBigInteger('amount_cents');
            $t->string('method');
            $t->string('status')->default('confirmed');
            $t->timestamp('confirmed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'confirmed_at']);
        });
        Schema::create('message_outbox', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('charge_id')->nullable()->constrained();
            $t->string('stage');
            $t->timestamp('scheduled_at');
            $t->string('status')->default('pending');
            $t->string('provider_id')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'charge_id', 'stage', 'scheduled_at']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'message_outbox', 'payments', 'charges', 'contracts', 'plans', 'customers'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('tenants');
    }
};
