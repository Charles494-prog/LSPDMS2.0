<?php

use App\Http\Controllers\BroadcastAuthController;
use App\Http\Controllers\AdminDatabaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\DeliberationController;
use App\Http\Controllers\DelistmentController;
use App\Http\Controllers\FilterBnhHelperController;
use App\Http\Controllers\FormGeneratorController;
use App\Http\Controllers\AssignedTaskController;
use App\Http\Controllers\BuiltInHelpChatbotController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePictureController;
use App\Http\Controllers\R402R403Controller;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SecretaryTodoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperadminController;
use App\Http\Controllers\SystemSmtpSettingsController;
use App\Http\Controllers\TarhetaStatusController;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/intro');
});

Route::get('/intro', function () {
    return view('intro');
})->middleware('guest.redirect');

Route::get('/login', [LoginController::class, 'showLogin'])->middleware('guest.redirect')->name('login.form');
Route::get('/login/csrf-token', fn (Request $request) => response()
    ->json(['token' => $request->session()->token()])
    ->header('Cache-Control', 'no-store, private'))
    ->name('login.csrf-token');
Route::get('/register', [RegistrationController::class, 'create'])->middleware('guest.redirect')->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');

Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:60,1')->name('login');
Route::get('/password/reset', [PasswordResetController::class, 'requestForm'])->name('password.request');
Route::post('/password/email', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
Route::get('/password/reset/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset.form');
Route::get('/password/link-expired', [PasswordResetController::class, 'expiredLink'])->name('password.reset.expired');
Route::post('/password/email/resend', [PasswordResetController::class, 'resendLink'])->middleware('throttle:5,1')->name('password.email.resend');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.reset');
Route::get('/verify-code', [LoginController::class, 'showVerificationForm'])->name('verify-code');
Route::post('/verify-code', [LoginController::class, 'verifyCode'])->middleware('throttle:10,1');
Route::post('/verify-code/resend', [LoginController::class, 'resendCode'])->middleware('throttle:3,1')->name('verify-code.resend');
Route::middleware(['login.session', 'no-store'])->group(function () {
    Route::get('/secretary-todos', [SecretaryTodoController::class, 'index'])->name('secretary-todos.index');
    Route::post('/secretary-todos', [SecretaryTodoController::class, 'store'])->middleware('throttle:60,1')->name('secretary-todos.store');
    Route::patch('/secretary-todos/{todo}', [SecretaryTodoController::class, 'update'])->middleware('throttle:60,1')->name('secretary-todos.update');
    Route::post('/secretary-todos/{todo}/complete', [SecretaryTodoController::class, 'complete'])->middleware('throttle:60,1')->name('secretary-todos.complete');
    // Tasks the Head Secretary assigns to secretaries (Tasks page in the sidebar).
    Route::get('/tasks', [AssignedTaskController::class, 'page'])->name('tasks');
    Route::get('/assigned-tasks', [AssignedTaskController::class, 'index'])->middleware('throttle:120,1')->name('assigned-tasks.index');
    Route::get('/assigned-tasks/unseen', [AssignedTaskController::class, 'unseen'])->middleware('throttle:120,1')->name('assigned-tasks.unseen');
    Route::post('/assigned-tasks/seen', [AssignedTaskController::class, 'markSeenRequest'])->middleware('throttle:60,1')->name('assigned-tasks.seen');
    Route::get('/assigned-tasks/secretaries', [AssignedTaskController::class, 'secretaries'])->name('assigned-tasks.secretaries');
    Route::post('/assigned-tasks', [AssignedTaskController::class, 'store'])->middleware('throttle:30,1')->name('assigned-tasks.store');
    Route::put('/assigned-tasks/{task}', [AssignedTaskController::class, 'update'])->middleware('throttle:30,1')->name('assigned-tasks.update');
    Route::delete('/assigned-tasks/{task}', [AssignedTaskController::class, 'destroy'])->middleware('throttle:30,1')->name('assigned-tasks.destroy');
    Route::post('/assigned-tasks/{task}/comments', [AssignedTaskController::class, 'comment'])->middleware('throttle:60,1')->name('assigned-tasks.comment');
    Route::post('/assigned-tasks/{task}/reactions', [AssignedTaskController::class, 'react'])->middleware('throttle:120,1')->name('assigned-tasks.react');
    Route::post('/assigned-tasks/{task}/complete', [AssignedTaskController::class, 'complete'])->middleware('throttle:30,1')->name('assigned-tasks.complete');
    Route::post('/assigned-tasks/{task}/reopen', [AssignedTaskController::class, 'reopen'])->middleware('throttle:30,1')->name('assigned-tasks.reopen');
    Route::post('/assigned-tasks/{task}/confirm', [AssignedTaskController::class, 'confirm'])->middleware('throttle:30,1')->name('assigned-tasks.confirm');
    Route::post('/messages/broadcasting/auth', BroadcastAuthController::class)->name('broadcasting.auth');
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/assistant', [BuiltInHelpChatbotController::class, 'index'])->name('messages.assistant');
    Route::post('/messages/assistant', [BuiltInHelpChatbotController::class, 'reply'])
        ->middleware('throttle:10,1')
        ->name('messages.assistant.reply');
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread-count');
    Route::get('/messages/accounts', [MessageController::class, 'accounts'])->name('messages.accounts');
    Route::get('/messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations');
    Route::post('/messages/conversations', [MessageController::class, 'createConversation'])->middleware('throttle:20,1')->name('messages.conversations.create');
    Route::get('/messages/conversations/{conversation}/messages', [MessageController::class, 'messages'])->name('messages.list');
    Route::post('/messages/conversations/{conversation}/messages', [MessageController::class, 'send'])->middleware('throttle:60,1')->name('messages.send');
    Route::post('/messages/conversations/{conversation}/typing', [MessageController::class, 'typing'])->middleware('throttle:30,1')->name('messages.typing');
    Route::post('/messages/{message}/delivered', [MessageController::class, 'delivered'])->middleware('throttle:120,1')->name('messages.delivered');
    Route::post('/messages/{message}/read', [MessageController::class, 'read'])->middleware('throttle:120,1')->name('messages.read');
    Route::get('/messages/{message}/attachment', [MessageController::class, 'attachment'])->name('messages.attachment');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard_allow')->name('dashboard');
    Route::get('/de-listment', [DelistmentController::class, 'index'])->middleware('permission:Delistment_allow')->name('de-listment');
    Route::post('/de-listment/records/mk', [DelistmentController::class, 'addMkMember'])->middleware('permission:Delistment_allow')->name('de-listment.mk.store');
    Route::get('/de-listment/export/{category}', [DelistmentController::class, 'exportCategory'])->middleware(['permission:Delistment_allow', 'throttle:20,1'])->name('de-listment.export');
    Route::get('/de-listment/history/download', [DelistmentController::class, 'exportHistory'])->middleware(['permission:Delistment_allow', 'throttle:20,1'])->name('de-listment.history.download');
    Route::put('/de-listment/records/{record}/endorsement', [DelistmentController::class, 'endorse'])->middleware('permission:Delistment_allow')->name('de-listment.endorse');
    Route::put('/de-listment/records/{record}/delist', [DelistmentController::class, 'delist'])->middleware('permission:Delistment_allow')->name('de-listment.delist');
    Route::put('/de-listment/records/dates', [DelistmentController::class, 'updateDates'])->middleware('permission:Delistment_allow')->name('de-listment.update-dates');
    Route::middleware('permission:tarheta_status_allow')->group(function (): void {
        Route::get('/tarheta-status', [TarhetaStatusController::class, 'index'])->name('tarheta-status');
        Route::put('/tarheta-status/records/{record}', [TarhetaStatusController::class, 'update'])->name('tarheta-status.update');
    });
    Route::middleware('permission:filter_bnh_helper_allow')->group(function (): void {
        Route::get('/filter-bnh-helper', [FilterBnhHelperController::class, 'index'])->name('filter-bnh-helper');
        Route::get('/filter-bnh-helper/matrix', [FilterBnhHelperController::class, 'matrix'])->name('filter-bnh-helper.matrix');
        Route::get('/filter-bnh-helper/members', [FilterBnhHelperController::class, 'members'])->name('filter-bnh-helper.members');
        Route::get('/filter-bnh-helper/bnh-records', [FilterBnhHelperController::class, 'bnhRecords'])->name('filter-bnh-helper.bnh-records');
        Route::get('/filter-bnh-helper/qualified-counts', [FilterBnhHelperController::class, 'qualifiedCounts'])->name('filter-bnh-helper.qualified-counts');
        Route::get('/filter-bnh-helper/bnh-word', [FilterBnhHelperController::class, 'downloadBnhWord'])->middleware('throttle:20,1')->name('filter-bnh-helper.bnh-word');
        Route::get('/filter-bnh-helper/counts-word', [FilterBnhHelperController::class, 'downloadCountsWord'])->middleware('throttle:20,1')->name('filter-bnh-helper.counts-word');
        Route::put('/filter-bnh-helper/records/{record}/checks', [FilterBnhHelperController::class, 'updateChecks'])->name('filter-bnh-helper.checks.update');
    });
    Route::middleware('permission:Inquire_Allow')->group(function (): void {
        Route::get('/records/search', [RecordsController::class, 'search'])->middleware('throttle:60,1')->name('records.search');
        Route::get('/records/smart-helper', [RecordsController::class, 'smartHelper'])->middleware('throttle:10,1')->name('records.smart-helper');
        Route::delete('/records/{record}', [RecordsController::class, 'destroy'])->middleware('throttle:30,1')->name('records.destroy');
        Route::put('/records/{record}', [RecordsController::class, 'update'])->middleware('throttle:30,1')->name('records.update');
    });
    // Note Checker: its own page and permission (it used to be a Form Generator tab).
    Route::middleware('permission:note_checker_allow')->group(function (): void {
        Route::get('/note-checker', [FormGeneratorController::class, 'noteChecker'])->name('note-checker');
        Route::get('/forms/notes', [FormGeneratorController::class, 'notes'])->name('forms.notes');
        Route::post('/forms/notes/records/{record}', [FormGeneratorController::class, 'appendNote'])->middleware('throttle:30,1')->name('forms.notes.append');
        Route::delete('/forms/notes/records/{record}/{noteIndex}', [FormGeneratorController::class, 'deleteNote'])
            ->whereNumber('noteIndex')
            ->name('forms.notes.delete');
    });
    Route::middleware('permission:form_generator_allow')->group(function (): void {
        Route::get('/form-generator', [FormGeneratorController::class, 'index'])->name('form-generator');
        Route::get('/forms/monthly/deliberation', [DeliberationController::class, 'index'])->name('forms.monthly.deliberation');
        Route::post('/forms/monthly/deliberation/duplicates', [DeliberationController::class, 'duplicates'])
            ->middleware('throttle:30,1')
            ->name('forms.monthly.deliberation.duplicates');
        Route::get('/forms/monthly/deliberation/suggestions', [DeliberationController::class, 'addSuggestions'])
            ->middleware('throttle:120,1')
            ->name('forms.monthly.deliberation.suggestions');
        Route::post('/forms/monthly/deliberation/registry-check', [DeliberationController::class, 'checkRegistry'])
            ->middleware('throttle:60,1')
            ->name('forms.monthly.deliberation.registry-check');
        Route::get('/forms/monthly/deliberation/subtract/search', [DeliberationController::class, 'searchSubtractRecords'])
            ->middleware('throttle:60,1')
            ->name('forms.monthly.deliberation.subtract.search');
        Route::post('/forms/monthly/deliberation/subtract', [DeliberationController::class, 'subtract'])
            ->middleware('throttle:15,1')
            ->name('forms.monthly.deliberation.subtract');
        Route::post('/forms/monthly/deliberation', [DeliberationController::class, 'store'])
            ->middleware('throttle:15,1')
            ->name('forms.monthly.deliberation.store');
        Route::get('/forms/monthly/modify', [DeliberationController::class, 'modifyIndex'])->name('forms.monthly.modify');
        Route::get('/forms/monthly/modify/search', [DeliberationController::class, 'modifySearch'])->name('forms.monthly.modify.search');
        Route::post('/forms/monthly/modify', [DeliberationController::class, 'modify'])->middleware('throttle:15,1')->name('forms.monthly.modify.save');
        Route::get('/forms/monthly/session', [DeliberationController::class, 'sessionMonitor'])->name('forms.monthly.session');
        Route::get('/forms/monthly/session/status', [DeliberationController::class, 'sessionStatus'])->name('forms.monthly.session.status');
        Route::post('/forms/monthly/session/start', [DeliberationController::class, 'startSession'])->name('forms.monthly.session.start');
        Route::post('/forms/monthly/session/finish', [DeliberationController::class, 'finishSession'])
            ->middleware('head.secretary')
            ->name('forms.monthly.session.finish');
        Route::delete('/forms/monthly/session/movements/{movement}', [DeliberationController::class, 'undoMovement'])->name('forms.monthly.session.undo');
        Route::delete('/forms/monthly/session/activities/{activity}', [DeliberationController::class, 'undoActivity'])->name('forms.monthly.session.activity.undo');
        Route::get('/forms/monthly/generators', [FormGeneratorController::class, 'monthlyGenerators'])->name('forms.monthly.generators');
        Route::get('/forms/monthly/r4-01', [FormGeneratorController::class, 'r401'])->name('forms.monthly.r4-01');
        Route::get('/forms/monthly/r4-01/snapshots', [FormGeneratorController::class, 'r401Snapshots'])->name('forms.monthly.r4-01.snapshots');
        Route::get('/forms/monthly/r4-01/snapshots/{snapshot}/summary', [FormGeneratorController::class, 'r401SnapshotSummary'])->name('forms.monthly.r4-01.snapshots.summary');
        Route::get('/forms/monthly/r4-01/snapshots/{snapshot}/view', [FormGeneratorController::class, 'viewR401Snapshot'])->name('forms.monthly.r4-01.snapshots.view');
        Route::get('/forms/monthly/r4-01/snapshots/{snapshot}/download', [FormGeneratorController::class, 'downloadR401Snapshot'])->name('forms.monthly.r4-01.snapshots.download');
        Route::get('/forms/monthly/r4-01/download', [FormGeneratorController::class, 'downloadR401'])->middleware('throttle:20,1')->name('forms.monthly.r4-01.download');
        Route::get('/forms/monthly/resumen', [FormGeneratorController::class, 'resumen'])->name('forms.monthly.resumen');
        Route::get('/forms/monthly/resumen/download', [FormGeneratorController::class, 'downloadResumen'])->middleware('throttle:20,1')->name('forms.monthly.resumen.download');
        Route::get('/forms/weekly/r4-02', [R402R403Controller::class, 'r402'])->name('forms.weekly.r4-02');
        Route::post('/forms/weekly/r4-02', [R402R403Controller::class, 'storeR402'])
            ->middleware('throttle:20,1')
            ->name('forms.weekly.r4-02.store');
        Route::get('/forms/weekly/r4-02/{generatedForm}/print', [R402R403Controller::class, 'printR402'])
            ->whereNumber('generatedForm')
            ->name('forms.weekly.r4-02.print');
        Route::get('/forms/weekly/r4-03', [R402R403Controller::class, 'r403'])->name('forms.weekly.r4-03');
        Route::get('/forms/weekly/r4-03/print', [R402R403Controller::class, 'printR403'])->name('forms.weekly.r4-03.print');
        // Former placeholder pages: the names stay for old links, the forms live in the Form Generator.
        Route::get('/forms/weekly', fn () => redirect()->route('form-generator'))->name('forms.weekly');
        Route::get('/forms/monthly', fn () => redirect()->route('form-generator'))->name('forms.monthly');
    });
    Route::middleware('head.secretary')->group(function (): void {
        Route::delete('/forms/monthly/r4-01/snapshots/{snapshot}', [FormGeneratorController::class, 'deleteR401Snapshot'])
            ->name('forms.monthly.r4-01.snapshots.delete');
        Route::get('/superadmin-dashboard', [SuperadminController::class, 'index'])->name('superadmin-dashboard');
        Route::get('/superadmin/database/export.sql', [AdminDatabaseController::class, 'exportSql'])
            ->middleware('throttle:10,1')
            ->name('superadmin.database.export-sql');
        Route::post('/superadmin/database/import/preview', [AdminDatabaseController::class, 'previewSqlImport'])
            ->middleware('throttle:10,1')
            ->name('superadmin.database.import-preview');
        Route::post('/superadmin/database/import', [AdminDatabaseController::class, 'importSql'])
            ->middleware('throttle:5,1')
            ->name('superadmin.database.import');
        Route::post('/superadmin/database/export.xlsx', [AdminDatabaseController::class, 'exportXlsx'])
            ->middleware('throttle:10,1')
            ->name('superadmin.database.export-xlsx');
        Route::get('/superadmin/database/backups', [DatabaseBackupController::class, 'index'])
            ->name('superadmin.database.backups');
        Route::post('/superadmin/database/backups', [DatabaseBackupController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('superadmin.database.backups.store');
        Route::get('/superadmin/database/backups/{file}', [DatabaseBackupController::class, 'download'])
            ->middleware('throttle:20,1')
            ->where('file', '[^/]+')
            ->name('superadmin.database.backups.download');
        Route::put('/superadmin/database/backups/{file}', [DatabaseBackupController::class, 'rename'])
            ->middleware('throttle:20,1')
            ->where('file', '[^/]+')
            ->name('superadmin.database.backups.rename');
        Route::put('/superadmin/smtp-settings', [SystemSmtpSettingsController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('superadmin.smtp-settings.update');
        Route::delete('/superadmin/smtp-settings', [SystemSmtpSettingsController::class, 'destroy'])
            ->middleware('throttle:6,1')
            ->name('superadmin.smtp-settings.destroy');
        Route::get('/superadmin/smtp-settings/verification', [SystemSmtpSettingsController::class, 'verificationStatus'])
            ->name('superadmin.smtp-settings.verification.status');
        Route::post('/superadmin/smtp-settings/verification/send', [SystemSmtpSettingsController::class, 'sendVerification'])
            ->middleware('throttle:6,1')
            ->name('superadmin.smtp-settings.verification.send');
        Route::post('/superadmin/smtp-settings/verification/verify', [SystemSmtpSettingsController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('superadmin.smtp-settings.verification.verify');
        Route::get('/superadmin/record-activity/download', [SuperadminController::class, 'downloadRecordAuditLogs'])
            ->name('superadmin.record-activity.download');
        Route::post('/superadmin/r4-02/hakbangs', [SuperadminController::class, 'storeR402Hakbang'])->name('superadmin.r4-02.hakbangs.store');
        Route::put('/superadmin/r4-02/hakbangs/{hakbang}', [SuperadminController::class, 'updateR402Hakbang'])->name('superadmin.r4-02.hakbangs.update');
        Route::delete('/superadmin/r4-02/hakbangs/{hakbang}', [SuperadminController::class, 'destroyR402Hakbang'])->name('superadmin.r4-02.hakbangs.destroy');
        Route::put('/superadmin/users/{user}', [SuperadminController::class, 'updateUser'])->middleware('throttle:20,1')->name('superadmin.users.update');
        Route::post('/superadmin/users/transfer-head-secretary', [SuperadminController::class, 'transferHeadSecretary'])
            ->middleware('throttle:20,1')
            ->name('superadmin.users.transfer-head-secretary');
        Route::put('/superadmin/permissions', [SuperadminController::class, 'updatePermissions'])->middleware('throttle:20,1')->name('superadmin.permissions.update');
        Route::put('/superadmin/signatories', [SuperadminController::class, 'updateSignatories'])->name('superadmin.signatories.update');
        Route::put('/superadmin/global-variables', [SuperadminController::class, 'updateGlobalVariables'])->name('superadmin.global-variables.update');
        Route::put('/superadmin/security-settings', [SuperadminController::class, 'updateSecuritySettings'])->middleware('throttle:10,1')->name('superadmin.security-settings.update');
        Route::post('/superadmin/reset-bnh-helper-checks/code', [SuperadminController::class, 'generateBnhResetCode'])
            ->middleware('throttle:10,1')
            ->name('superadmin.reset-bnh-helper-checks.code');
        Route::post('/superadmin/reset-bnh-helper-checks', [SuperadminController::class, 'resetBnhHelperChecks'])
            ->middleware('throttle:10,1')
            ->name('superadmin.reset-bnh-helper-checks');
    });
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/session/status', function (Request $request) {
        $user = $request->session()->get('login_user', []);
        $isHeadSecretary = Role::isHeadSecretary($user['tungkulin'] ?? '');

        return response()->json([
            // Identity lets an open tab notice that the browser now belongs to another login.
            'user_id' => (int) ($user['id'] ?? 0),
            'auth_version' => (int) ($user['auth_version'] ?? 0),
            'permissions' => [
                'head_secretary' => $isHeadSecretary,
                'dashboard_allow' => $isHeadSecretary || (bool) ($user['dashboard_allow'] ?? false),
                'form_generator_allow' => $isHeadSecretary || (bool) ($user['form_generator_allow'] ?? false),
                'Delistment_allow' => $isHeadSecretary || (bool) ($user['Delistment_allow'] ?? false),
                'Inquire_Allow' => $isHeadSecretary || (bool) ($user['Inquire_Allow'] ?? false),
                'tarheta_status_allow' => $isHeadSecretary || (bool) ($user['tarheta_status_allow'] ?? false),
                'filter_bnh_helper_allow' => $isHeadSecretary || (bool) ($user['filter_bnh_helper_allow'] ?? false),
                'note_checker_allow' => $isHeadSecretary || (bool) ($user['note_checker_allow'] ?? false),
            ],
        ]);
    })->name('session.status');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile-pictures/{user}', [ProfilePictureController::class, 'show'])->whereNumber('user')->name('profile-pictures.show');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1')->name('profile.password.update');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::view('/home', 'home', ['title' => 'Home'])->name('home');
    Route::get('/inquire-modify', function (Request $request) {
        $user = $request->session()->get('login_user', []);
        $canViewEligibility = Role::isHeadSecretary($user['tungkulin'] ?? '')
            || (bool) ($user['Delistment_allow'] ?? false);

        return view('inquire-modify', [
            'title' => 'Inquire / Modify',
            'canViewEligibility' => $canViewEligibility,
        ]);
    })->middleware('permission:Inquire_Allow')->name('inquire-modify');
});