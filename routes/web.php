<?php

use App\Http\Controllers\CooperativeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PlatformController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;

Route::get('/', function () {
    if (auth('platform')->check()) {
        return redirect()->route('platform.dashboard');
    }

    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/choose-cooperative', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'selectCooperative'])
    ->middleware('guest')
    ->name('login.cooperative.select');
Route::post('/choose-cooperative', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'authenticateSelectedCooperative'])
    ->middleware('guest')
    ->name('login.cooperative.authenticate');

Route::middleware('auth:platform')->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', [PlatformController::class, 'index'])->name('dashboard');
    Route::get('/accounts', [PlatformController::class, 'accounts'])->name('accounts');
    Route::get('/cooperatives', [PlatformController::class, 'cooperatives'])->name('cooperatives');
    Route::get('/cooperatives/{cooperative}', [PlatformController::class, 'show'])->name('cooperatives.show');
    Route::post('/cooperatives', [PlatformController::class, 'store'])->name('cooperatives.store');
    Route::patch('/cooperatives/{cooperative}/status', [PlatformController::class, 'updateCooperativeStatus'])->name('cooperatives.status');
    Route::delete('/cooperatives/{cooperative}', [PlatformController::class, 'destroyCooperative'])->name('cooperatives.destroy');
    Route::patch('/cooperatives/{cooperativeId}/accounts/{userId}', [PlatformController::class, 'updateAccount'])->name('accounts.update');
    Route::post('/cooperatives/{cooperativeId}/accounts/{userId}/reset-password', [PlatformController::class, 'resetAccountPassword'])->name('accounts.reset-password');
    Route::patch('/cooperatives/{cooperativeId}/accounts/{userId}/status', [PlatformController::class, 'updateAccountStatus'])->name('accounts.status');
    Route::delete('/cooperatives/{cooperativeId}/accounts/{userId}', [PlatformController::class, 'destroyAccount'])->name('accounts.destroy');
    Route::post('/profile/password', [PlatformController::class, 'updateProfilePassword'])->name('profile.password');
    Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');
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
    Route::post('/cooperative/borrowers/{borrower}/password', [CooperativeController::class, 'updateBorrowerPassword'])->name('cooperative.borrowers.update-password');
    Route::delete('/cooperative/borrowers/{borrower}', [CooperativeController::class, 'destroyBorrower'])->name('cooperative.borrowers.destroy');
    Route::post('/cooperative/staff/{user}/password', [CooperativeController::class, 'updateStaffPassword'])->name('cooperative.staff.update-password');
    Route::get('/cooperative/loans', [CooperativeController::class, 'loans'])->name('cooperative.loans');
    Route::post('/cooperative/loans', [CooperativeController::class, 'storeLoan'])->name('cooperative.loans.store');
    Route::patch('/cooperative/loans/{loan}', [CooperativeController::class, 'correctLoan'])->name('cooperative.loans.correct');
    Route::delete('/cooperative/loans/{loan}', [CooperativeController::class, 'archiveLoan'])->name('cooperative.loans.archive');
    Route::get('/cooperative/payments', [CooperativeController::class, 'payments'])->name('cooperative.payments');
    Route::post('/cooperative/payments', [CooperativeController::class, 'storePayment'])->name('cooperative.payments.store');
    Route::patch('/cooperative/payments/{payment}', [CooperativeController::class, 'updatePayment'])->name('cooperative.payments.update');
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
    Route::get('/cooperative/notifications', [CooperativeController::class, 'notifications'])->name('cooperative.notifications');
    Route::get('/cooperative/notifications/unread', [CooperativeController::class, 'unreadNotifications'])->name('cooperative.notifications.unread');
    Route::post('/cooperative/notifications/{notification}/open', [CooperativeController::class, 'openNotification'])->name('cooperative.notifications.open');
    Route::post('/cooperative/notifications/{notification}/read', [CooperativeController::class, 'markNotificationRead'])->name('cooperative.notifications.read');
    Route::post('/cooperative/notifications/read-all', [CooperativeController::class, 'markAllNotificationsRead'])->name('cooperative.notifications.read-all');
});

require __DIR__.'/auth.php';
