<?php

use App\Http\Controllers\CredentialCardPrintController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PublicInvoicePaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', fn () => redirect('/admin/login'))->name('login');

Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'fr', 'en'], true)) {
        session(['locale' => $locale]);
        if (auth()->check()) {
            auth()->user()->update(['locale' => $locale]);
        }
    }

    return redirect()->back();
})->name('locale.switch');

Route::get('/pay/invoice/{token}', [PublicInvoicePaymentController::class, 'show'])
    ->name('invoice.public-pay');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/{tenant:slug}/payments/{payment}/receipt', [PaymentReceiptController::class, 'show'])
        ->name('filament.admin.payment-receipt');

    Route::get('/admin/{tenant:slug}/credentials/print-cards', [CredentialCardPrintController::class, 'show'])
        ->name('filament.admin.credentials.print-cards');
});
