<?php

use App\Http\Controllers\Api\V1\User\ConsentController;
use App\Http\Controllers\Api\V1\User\CurrentUserController;
use App\Http\Controllers\Api\V1\User\DashboardController;
use App\Http\Controllers\Api\V1\User\DeviceController;
use App\Http\Controllers\Api\V1\User\KcaApplicationController;
use App\Http\Controllers\Api\V1\User\KcaCurriculumController;
use App\Http\Controllers\Api\V1\User\MessageController;
use App\Http\Controllers\Api\V1\User\NotificationController;
use App\Http\Controllers\Api\V1\User\PastoralNeedController;
use App\Http\Controllers\Api\V1\User\PaymentController;
use App\Http\Controllers\Api\V1\User\PrayerRequestController;
use App\Http\Controllers\Api\V1\User\PreferenceController;
use App\Http\Controllers\Api\V1\User\ProfileController;
use App\Http\Controllers\Api\V1\User\SecuritySessionController;
use App\Http\Controllers\Api\V1\User\SyncController;
use App\Http\Controllers\Api\V1\User\UserAuthorizationController;
use App\Http\Controllers\Api\V1\User\UserChurchOperationsController;
use App\Http\Controllers\Api\V1\User\UserDomainOperationsController;
use App\Http\Middleware\EnsureRecentMfa;
use Illuminate\Support\Facades\Route;

Route::get('/me', CurrentUserController::class)->name('me.show');
Route::get('/dashboard', DashboardController::class)->name('dashboard.show');
Route::get('/capabilities', [UserAuthorizationController::class, 'capabilities'])->name('capabilities.show');
Route::post('/authorization/check', [UserAuthorizationController::class, 'check'])
    ->middleware('throttle:60,1')
    ->name('authorization.check');
Route::put('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
Route::get('/consents', [ConsentController::class, 'index'])->name('consents.index');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
    ->whereUlid('notification')
    ->name('notifications.read');

Route::get('/payments/intents', [PaymentController::class, 'intents'])->name('payments.intents.index');
Route::get('/payments/intents/{intent}', [PaymentController::class, 'showIntent'])
    ->whereUlid('intent')
    ->name('payments.intents.show');
Route::get('/payments/transactions', [PaymentController::class, 'transactions'])->name('payments.transactions.index');
Route::get('/payments/receipts/{receipt}', [PaymentController::class, 'receipt'])
    ->whereUlid('receipt')
    ->name('payments.receipts.show');

Route::get('/prayers', [PrayerRequestController::class, 'index'])->name('prayers.index');
Route::post('/prayers', [PrayerRequestController::class, 'store'])->name('prayers.store');

Route::prefix('kca')->name('kca.')->group(function (): void {
    Route::post('/applications', [KcaApplicationController::class, 'store'])->name('applications.store');
    Route::get('/dashboard', [KcaCurriculumController::class, 'dashboard'])->name('dashboard');
    Route::get('/modules', [KcaCurriculumController::class, 'modules'])->name('modules.index');
    Route::get('/modules/{module}', [KcaCurriculumController::class, 'module'])
        ->whereUlid('module')
        ->name('modules.show');
    Route::get('/assignments', [KcaCurriculumController::class, 'assignments'])->name('assignments.index');
    Route::get('/mentor', [KcaCurriculumController::class, 'mentor'])->name('mentor.show');
    Route::get('/attendance', [KcaCurriculumController::class, 'attendance'])->name('attendance.index');
});

Route::get('/needs', [PastoralNeedController::class, 'index'])->name('needs.index');
Route::post('/needs', [PastoralNeedController::class, 'store'])->name('needs.store');

Route::get('/messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations.index');
Route::post('/messages/conversations', [MessageController::class, 'storeConversation'])->name('messages.conversations.store');
Route::get('/messages/conversations/{conversation}/messages', [MessageController::class, 'messages'])
    ->whereUlid('conversation')
    ->name('messages.conversations.messages.index');
Route::post('/messages/conversations/{conversation}/messages', [MessageController::class, 'storeMessage'])
    ->whereUlid('conversation')
    ->name('messages.conversations.messages.store');

Route::get('/sync/checkpoint', [SyncController::class, 'checkpoint'])->name('sync.checkpoint.show');
Route::put('/sync/checkpoint', [SyncController::class, 'updateCheckpoint'])->name('sync.checkpoint.update');
Route::get('/sync/changes', [SyncController::class, 'changes'])->name('sync.changes.index');

Route::get('/events/registrations/{registration}', [UserDomainOperationsController::class, 'showRegistration'])
    ->whereUlid('registration')
    ->name('events.registrations.show');
Route::get('/memberships', [UserChurchOperationsController::class, 'memberships'])->name('memberships.index');
Route::get('/home-churches/{homeChurch}', [UserChurchOperationsController::class, 'showHomeChurch'])
    ->whereUlid('homeChurch')
    ->name('home-churches.show');
Route::get('/files', [UserDomainOperationsController::class, 'files'])->name('files.index');
Route::get('/files/{file}', [UserDomainOperationsController::class, 'stream'])
    ->whereUlid('file')
    ->name('files.stream');

Route::middleware(EnsureRecentMfa::class)->group(function (): void {
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('/consents', [ConsentController::class, 'store'])->name('consents.store');
    Route::delete('/consents/{consent}', [ConsentController::class, 'destroy'])->name('consents.destroy');

    Route::get('/security/sessions', [SecuritySessionController::class, 'index'])
        ->name('security.sessions.index');
    Route::delete('/security/sessions/{securitySession}', [SecuritySessionController::class, 'destroy'])
        ->name('security.sessions.destroy');
    Route::get('/security/devices', [DeviceController::class, 'index'])
        ->name('security.devices.index');
    Route::delete('/security/devices/{device}', [DeviceController::class, 'destroy'])
        ->name('security.devices.destroy');

    Route::post('/payments/giving-intents', [PaymentController::class, 'storeGivingIntent'])
        ->name('payments.giving_intents.store');
    Route::post('/payments/giving-intents/{intent}/complete', [PaymentController::class, 'completeGivingIntent'])
        ->whereUlid('intent')
        ->name('payments.giving_intents.complete');
    Route::post('/events/registrations/{registration}/payment-intents', [PaymentController::class, 'storeEventPaymentIntent'])
        ->whereUlid('registration')
        ->name('events.registrations.payment_intents.store');

    Route::post('/events/{event}/registrations', [UserDomainOperationsController::class, 'registerForEvent'])
        ->whereUlid('event')
        ->name('events.registrations.store');
    Route::post('/events/feedback', [UserDomainOperationsController::class, 'recordFeedback'])
        ->name('events.feedback.store');
    Route::post('/churches/{church}/memberships', [UserChurchOperationsController::class, 'startMembership'])
        ->whereUlid('church')
        ->name('churches.memberships.store');
    Route::post('/home-churches/{homeChurch}/reports', [UserChurchOperationsController::class, 'storeHomeChurchReport'])
        ->whereUlid('homeChurch')
        ->name('home-churches.reports.store');
    Route::post('/privacy/data-subject-requests', [UserDomainOperationsController::class, 'submitDataSubjectRequest'])
        ->name('privacy.data_subject_requests.store');
    Route::post('/files', [UserDomainOperationsController::class, 'storeFile'])
        ->name('files.store');
});
