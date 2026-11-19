<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\PortalAuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'dashboard'])->name('dashboard');
Route::get('/clientes', [AppController::class, 'customers'])->name('customers.index');
Route::get('/clientes/novo', [AppController::class, 'createCustomer'])->name('customers.create');
Route::post('/clientes', [AppController::class, 'storeCustomer'])->name('customers.store');
Route::post('/clientes/{customer}/acesso', [AppController::class, 'createCustomerAccess'])->name('customers.access');
Route::get('/clientes/{customer}/acesso', [AppController::class, 'customerAccessForm'])->name('customers.access.form');
Route::get('/clientes/{customer}', [AppController::class, 'showCustomer'])->name('customers.show');
Route::get('/clientes/{customer}/editar', [AppController::class, 'editCustomer'])->name('customers.edit');
Route::put('/clientes/{customer}', [AppController::class, 'updateCustomer'])->name('customers.update');
Route::delete('/clientes/{customer}', [AppController::class, 'destroyCustomer'])->name('customers.destroy');
Route::get('/cobrancas', [AppController::class, 'charges'])->name('charges.index');
Route::get('/cobrancas/nova', [AppController::class, 'createCharge'])->name('charges.create');
Route::post('/cobrancas', [AppController::class, 'storeCharge'])->name('charges.store');
Route::get('/cobrancas/{charge}', [AppController::class, 'showCharge'])->name('charges.show');
Route::get('/cobrancas/{charge}/editar', [AppController::class, 'editCharge'])->name('charges.edit');
Route::put('/cobrancas/{charge}', [AppController::class, 'updateCharge'])->name('charges.update');
Route::delete('/cobrancas/{charge}', [AppController::class, 'destroyCharge'])->name('charges.destroy');
Route::post('/cobrancas/{charge}/receber', [AppController::class, 'receive'])->name('charges.receive');
Route::get('/contratos', [AppController::class, 'contracts'])->name('contracts.index');
Route::get('/contratos/novo', [AppController::class, 'createContract'])->name('contracts.create');
Route::post('/contratos', [AppController::class, 'storeContract'])->name('contracts.store');
Route::get('/contratos/{contract}', [AppController::class, 'showContract'])->name('contracts.show');
Route::get('/contratos/{contract}/editar', [AppController::class, 'editContract'])->name('contracts.edit');
Route::put('/contratos/{contract}', [AppController::class, 'updateContract'])->name('contracts.update');
Route::delete('/contratos/{contract}', [AppController::class, 'destroyContract'])->name('contracts.destroy');
Route::get('/automacoes', [AppController::class, 'automations'])->name('automations');
Route::post('/automacoes', [AppController::class, 'saveAutomations'])->name('automations.save');
Route::post('/automacoes/gerar', [AppController::class, 'generateReminders'])->name('automations.generate');
Route::get('/relatorios', [AppController::class, 'reports'])->name('reports');
Route::get('/configuracoes', [AppController::class, 'settings'])->name('settings');
Route::post('/configuracoes/whatsapp', [AppController::class, 'saveWhatsApp'])->name('settings.whatsapp.save');
Route::post('/configuracoes/whatsapp/testar', [AppController::class, 'testWhatsApp'])->name('settings.whatsapp.test');
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [SuperAdminController::class, 'index'])->name('index');
    Route::get('/empresas/nova', [SuperAdminController::class, 'createCompany'])->name('companies.create');
    Route::post('/empresas', [SuperAdminController::class, 'storeCompany'])->name('companies.store');
    Route::get('/empresas/{company}', [SuperAdminController::class, 'showCompany'])->name('companies.show');
    Route::get('/empresas/{company}/editar', [SuperAdminController::class, 'editCompany'])->name('companies.edit');
    Route::put('/empresas/{company}', [SuperAdminController::class, 'updateCompany'])->name('companies.update');
    Route::delete('/empresas/{company}', [SuperAdminController::class, 'destroyCompany'])->name('companies.destroy');
    Route::get('/planos/novo', [SuperAdminController::class, 'createPlan'])->name('plans.create');
    Route::post('/planos', [SuperAdminController::class, 'storePlan'])->name('plans.store');
});

Route::middleware('guest')->group(function () {
    Route::get('/portal/login', [PortalAuthController::class, 'loginForm'])->name('portal.login');
    Route::post('/portal/login', [PortalAuthController::class, 'login'])->name('portal.login.submit');
    Route::get('/portal/esqueci-senha', [PortalAuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/portal/esqueci-senha', [PortalAuthController::class, 'forgot'])->name('password.email');
    Route::get('/portal/redefinir-senha/{token}', [PortalAuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/portal/redefinir-senha', [PortalAuthController::class, 'reset'])->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::get('/portal', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::post('/portal/sair', [PortalAuthController::class, 'logout'])->name('portal.logout');
});
