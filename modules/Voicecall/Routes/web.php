<?php

use Illuminate\Support\Facades\Route;
use Modules\Voicecall\Http\Controllers\SetupController;
use Modules\Voicecall\Http\Controllers\TwilioWebhookController;

Route::middleware(['web', 'auth', 'verified', 'acivatedProject'])->group(function () {
    Route::get('/voicecall/settings', [SetupController::class, 'index'])->name('voicecall.settings');
    Route::post('/voicecall/settings', [SetupController::class, 'store'])->name('voicecall.settings.store');
    Route::post('/voicecall/numbers', [SetupController::class, 'storeNumber'])->name('voicecall.numbers.store');
    Route::delete('/voicecall/numbers/{number}', [SetupController::class, 'destroyNumber'])->name('voicecall.numbers.destroy');
});

Route::prefix('webhook/voicecall/twilio')->group(function () {
    Route::post('/incoming', [TwilioWebhookController::class, 'incoming'])->name('voicecall.webhook.incoming');
    Route::post('/gather/{voiceCall}', [TwilioWebhookController::class, 'gather'])->name('voicecall.webhook.gather');
    Route::post('/status', [TwilioWebhookController::class, 'status'])->name('voicecall.webhook.status');
});

Route::post('/webhook/voicecall/telnyx', [\Modules\Voicecall\Http\Controllers\TelnyxWebhookController::class, 'handle'])
    ->name('voicecall.webhook.telnyx');
