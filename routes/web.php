<?php

use App\Http\Controllers\CooperativeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'password.changed'])
    ->name('dashboard');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/password/change', [ProfileController::class, 'edit'])->name('password.change');
    Route::patch('/password/change', function (Request $request) {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard');
    })->name('password.change.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/cooperative/borrowers', [CooperativeController::class, 'borrowers'])->name('cooperative.borrowers');
    Route::post('/cooperative/borrowers', [CooperativeController::class, 'storeBorrower'])->name('cooperative.borrowers.store');
    Route::post('/cooperative/borrowers/{borrower}/reset-password', [CooperativeController::class, 'resetBorrowerPassword'])->name('cooperative.borrowers.reset-password');
    Route::get('/cooperative/loans', [CooperativeController::class, 'loans'])->name('cooperative.loans');
    Route::post('/cooperative/loans', [CooperativeController::class, 'storeLoan'])->name('cooperative.loans.store');
    Route::get('/cooperative/payments', [CooperativeController::class, 'payments'])->name('cooperative.payments');
    Route::post('/cooperative/payments', [CooperativeController::class, 'storePayment'])->name('cooperative.payments.store');
    Route::get('/cooperative/payments/{payment}/proof', [CooperativeController::class, 'paymentProof'])->name('cooperative.payments.proof');
    Route::get('/cooperative/approvals', [CooperativeController::class, 'approvals'])->name('cooperative.approvals');
    Route::post('/cooperative/loans/{loan}/approve', [CooperativeController::class, 'approveLoan'])->name('cooperative.loans.approve');
    Route::post('/cooperative/loans/{loan}/reject', [CooperativeController::class, 'rejectLoan'])->name('cooperative.loans.reject');
    Route::post('/cooperative/payments/{payment}/approve', [CooperativeController::class, 'approvePayment'])->name('cooperative.payments.approve');
    Route::post('/cooperative/payments/{payment}/reject', [CooperativeController::class, 'rejectPayment'])->name('cooperative.payments.reject');

    Route::get('/cooperative/finances', [CooperativeController::class, 'cashHistory'])->name('cooperative.finances');
    Route::post('/cooperative/finances', [CooperativeController::class, 'storeCashAdjustment'])->name('cooperative.finances.store');
    Route::get('/cooperative/profits', [CooperativeController::class, 'profits'])->name('cooperative.profits');
    Route::post('/cooperative/profits/{user}/withdraw', [CooperativeController::class, 'withdrawProfit'])->name('cooperative.profits.withdraw');
    Route::post('/cooperative/profits/{user}/transfer', [CooperativeController::class, 'transferProfit'])->name('cooperative.profits.transfer');
    Route::get('/cooperative/activity-logs', [CooperativeController::class, 'activityLogs'])->name('cooperative.activity-logs');
});

require __DIR__.'/auth.php';
