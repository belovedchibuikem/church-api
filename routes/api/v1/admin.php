<?php

use App\Admin\AdminDashboardModule;
use App\Http\Controllers\Api\V1\Admin\AccessAdministrationController;
use App\Http\Controllers\Api\V1\Admin\AccessCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminProfileController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkItemController;
use App\Http\Controllers\Api\V1\Admin\AdvisoryAiOperationsController;
use App\Http\Controllers\Api\V1\Admin\AuditReviewController;
use App\Http\Controllers\Api\V1\Admin\ChurchMinistryOperationsController;
use App\Http\Controllers\Api\V1\Admin\ChurchOperationsController;
use App\Http\Controllers\Api\V1\Admin\CommunicationOperationsController;
use App\Http\Controllers\Api\V1\Admin\CommunicationProviderController;
use App\Http\Controllers\Api\V1\Admin\ContentAdministrationController;
use App\Http\Controllers\Api\V1\Admin\DemoDatasetController;
use App\Http\Controllers\Api\V1\Admin\DomainCatalogController;
use App\Http\Controllers\Api\V1\Admin\EventOperationsController;
use App\Http\Controllers\Api\V1\Admin\FileOperationsController;
use App\Http\Controllers\Api\V1\Admin\FinanceOperationsController;
use App\Http\Controllers\Api\V1\Admin\KcaGovernanceController;
use App\Http\Controllers\Api\V1\Admin\KcaOrientationStepController;
use App\Http\Controllers\Api\V1\Admin\KcaAdmissionLetterController;
use App\Http\Controllers\Api\V1\Admin\KcaOperationsController;
use App\Http\Controllers\Api\V1\Admin\KcaOrientationSessionController;
use App\Http\Controllers\Api\V1\Admin\LivestreamOperationsController;
use App\Http\Controllers\Api\V1\Admin\MapsProviderController;
use App\Http\Controllers\Api\V1\Admin\MediaOperationsController;
use App\Http\Controllers\Api\V1\Admin\MissionOperationsController;
use App\Http\Controllers\Api\V1\Admin\ObjectStorageController;
use App\Http\Controllers\Api\V1\Admin\OrganizationController;
use App\Http\Controllers\Api\V1\Admin\PaymentProviderController;
use App\Http\Controllers\Api\V1\Admin\PlatformBrandingController;
use App\Http\Controllers\Api\V1\Admin\PlatformSettingsController;
use App\Http\Controllers\Api\V1\Admin\PressOperationsController;
use App\Http\Controllers\Api\V1\Admin\PrivacyOperationsController;
use App\Http\Controllers\Api\V1\Admin\ReportingOperationsController;
use App\Http\Controllers\Api\V1\Admin\SafeguardingOperationsController;
use App\Http\Controllers\Api\V1\Admin\ScopeAssignmentController;
use App\Http\Controllers\Api\V1\Admin\SearchOperationsController;
use App\Http\Controllers\Api\V1\Admin\UserAdministrationController;
use App\Http\Middleware\RequireDashboardPermissionAndScope;
use App\Http\Middleware\RequirePermissionAndScope;
use App\Services\Admin\ProtectedDomainRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/profile', AdminProfileController::class)
    ->middleware(RequirePermissionAndScope::class.':member.self.manage')
    ->name('profile.show');

Route::prefix('dashboards')->name('dashboards.')->group(function (): void {
    Route::get('/{module}', [AdminDashboardController::class, 'show'])
        ->middleware(RequireDashboardPermissionAndScope::class)
        ->where('module', implode('|', array_map(
            fn (AdminDashboardModule $dashboard): string => $dashboard->value,
            AdminDashboardModule::cases(),
        )))
        ->name('show');
});

Route::controller(UserAdministrationController::class)
    ->prefix('users')
    ->name('users.')
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware(RequirePermissionAndScope::class.':identity.users.view')
            ->name('index');
        Route::post('/', 'store')
            ->middleware(RequirePermissionAndScope::class.':identity.users.manage')
            ->name('store');
        Route::get('/{user}', 'show')
            ->whereUlid('user')
            ->middleware(RequirePermissionAndScope::class.':identity.users.view')
            ->name('show');
        Route::patch('/{user}', 'update')
            ->whereUlid('user')
            ->middleware(RequirePermissionAndScope::class.':identity.users.manage')
            ->name('update');
        Route::post('/{user}/password-reset', 'requestPasswordReset')
            ->whereUlid('user')
            ->middleware(RequirePermissionAndScope::class.':identity.users.manage')
            ->name('password_reset');
        Route::post('/{user}/suspension', 'suspend')
            ->whereUlid('user')
            ->middleware(RequirePermissionAndScope::class.':identity.users.suspend')
            ->name('suspend');
        Route::delete('/{user}/suspension', 'reactivate')
            ->whereUlid('user')
            ->middleware(RequirePermissionAndScope::class.':identity.users.reactivate')
            ->name('reactivate');
    });

Route::prefix('access')->name('access.')->group(function (): void {
    Route::get('/roles', [AccessCatalogController::class, 'roles'])
        ->middleware(RequirePermissionAndScope::class.':identity.roles.view')
        ->name('roles.index');
    Route::post('/roles', [AccessCatalogController::class, 'storeRole'])
        ->middleware(RequirePermissionAndScope::class.':identity.roles.manage')
        ->name('roles.store');
    Route::get('/roles/{role}', [AccessCatalogController::class, 'showRole'])
        ->whereUlid('role')
        ->middleware(RequirePermissionAndScope::class.':identity.roles.view')
        ->name('roles.show');
    Route::patch('/roles/{role}', [AccessCatalogController::class, 'updateRole'])
        ->whereUlid('role')
        ->middleware(RequirePermissionAndScope::class.':identity.roles.manage')
        ->name('roles.update');
    Route::delete('/roles/{role}', [AccessCatalogController::class, 'destroyRole'])
        ->whereUlid('role')
        ->middleware(RequirePermissionAndScope::class.':identity.roles.manage')
        ->name('roles.destroy');
    Route::get('/permissions', [AccessCatalogController::class, 'permissions'])
        ->middleware(RequirePermissionAndScope::class.':identity.permissions.view')
        ->name('permissions.index');
    Route::get('/scope-assignments', ScopeAssignmentController::class)
        ->middleware(RequirePermissionAndScope::class.':identity.scopes.view')
        ->name('scope_assignments.index');
    Route::post('/role-assignments/{roleAssignment}/scopes', [AccessAdministrationController::class, 'assignScope'])
        ->whereUlid('roleAssignment')
        ->middleware(RequirePermissionAndScope::class.':identity.scopes.assign')
        ->name('role_assignments.scopes.store');
    Route::post('/roles/{role}/permissions', [AccessAdministrationController::class, 'grantPermission'])
        ->whereUlid('role')
        ->middleware(RequirePermissionAndScope::class.':identity.permissions.grant')
        ->name('roles.permissions.store');
});

Route::post('/users/{user}/role-assignments', [AccessAdministrationController::class, 'assignRole'])
    ->whereUlid('user')
    ->middleware(RequirePermissionAndScope::class.':identity.roles.assign')
    ->name('users.role_assignments.store');

Route::prefix('administration/work-items')->name('work_items.')->controller(AdminWorkItemController::class)->group(function (): void {
    Route::get('/', 'index')->middleware(RequirePermissionAndScope::class.':administration.work_items.view')->name('index');
    Route::post('/', 'store')->middleware(RequirePermissionAndScope::class.':administration.work_items.manage')->name('store');
    Route::get('/{workItem}', 'show')->whereUlid('workItem')->middleware(RequirePermissionAndScope::class.':administration.work_items.view')->name('show');
    Route::patch('/{workItem}', 'update')->whereUlid('workItem')->middleware(RequirePermissionAndScope::class.':administration.work_items.manage')->name('update');
    Route::post('/{workItem}/archive', 'archive')->whereUlid('workItem')->middleware(RequirePermissionAndScope::class.':administration.work_items.manage')->name('archive');
});

Route::prefix('security')->name('security.')->group(function (): void {
    Route::get('/audit-events', [AuditReviewController::class, 'auditEvents'])
        ->middleware(RequirePermissionAndScope::class.':security.audit.view')->name('audit_events.index');
    Route::get('/audit-events/{auditEvent}', [AuditReviewController::class, 'showAuditEvent'])
        ->whereUlid('auditEvent')
        ->middleware(RequirePermissionAndScope::class.':security.audit.view')->name('audit_events.show');
    Route::get('/sessions', [AuditReviewController::class, 'sessions'])
        ->middleware(RequirePermissionAndScope::class.':identity.security.sessions.view')->name('sessions.index');
    Route::get('/access-decisions', [AuditReviewController::class, 'accessDecisions'])
        ->middleware(RequirePermissionAndScope::class.':security.access_decisions.view')->name('access_decisions.index');
});

Route::prefix('organization')->name('organization.')->group(function (): void {
    Route::get('/countries', [OrganizationController::class, 'countries'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.view')
        ->name('countries.index');
    Route::post('/countries', [OrganizationController::class, 'storeCountry'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.manage')
        ->name('countries.store');
    Route::get('/countries/{country}', [OrganizationController::class, 'showCountry'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.view')
        ->name('countries.show');
    Route::patch('/countries/{country}', [OrganizationController::class, 'updateCountry'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.manage')
        ->name('countries.update');
    Route::delete('/countries/{country}', [OrganizationController::class, 'destroyCountry'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.manage')
        ->name('countries.destroy');
    Route::get('/countries/{country}/levels', [OrganizationController::class, 'levels'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.view')
        ->name('countries.levels.index');
    Route::post('/countries/{country}/levels', [OrganizationController::class, 'storeLevel'])
        ->middleware(RequirePermissionAndScope::class.':organization.countries.manage')
        ->name('countries.levels.store');
    Route::get('/units', [OrganizationController::class, 'units'])
        ->middleware(RequirePermissionAndScope::class.':organization.units.view')
        ->name('units.index');
    Route::post('/units', [OrganizationController::class, 'storeUnit'])
        ->middleware(RequirePermissionAndScope::class.':organization.units.manage')
        ->name('units.store');
    Route::get('/units/{unit}', [OrganizationController::class, 'showUnit'])
        ->whereUlid('unit')
        ->middleware(RequirePermissionAndScope::class.':organization.units.view')
        ->name('units.show');
    Route::patch('/units/{unit}', [OrganizationController::class, 'updateUnit'])
        ->whereUlid('unit')
        ->middleware(RequirePermissionAndScope::class.':organization.units.manage')
        ->name('units.update');
    Route::delete('/units/{unit}', [OrganizationController::class, 'destroyUnit'])
        ->whereUlid('unit')
        ->middleware(RequirePermissionAndScope::class.':organization.units.manage')
        ->name('units.destroy');
    Route::patch('/units/{unit}/parent', [OrganizationController::class, 'moveUnit'])
        ->whereUlid('unit')
        ->middleware(RequirePermissionAndScope::class.':organization.units.manage')
        ->name('units.parent.update');
    Route::get('/locations', [OrganizationController::class, 'locations'])
        ->middleware(RequirePermissionAndScope::class.':organization.locations.view')
        ->name('locations.index');
    Route::post('/locations', [OrganizationController::class, 'storeLocation'])
        ->middleware(RequirePermissionAndScope::class.':organization.locations.manage')
        ->name('locations.store');
    Route::get('/locations/{location}', [OrganizationController::class, 'showLocation'])
        ->whereUlid('location')
        ->middleware(RequirePermissionAndScope::class.':organization.locations.view')
        ->name('locations.show');
    Route::patch('/locations/{location}', [OrganizationController::class, 'updateLocation'])
        ->whereUlid('location')
        ->middleware(RequirePermissionAndScope::class.':organization.locations.manage')
        ->name('locations.update');
    Route::delete('/locations/{location}', [OrganizationController::class, 'destroyLocation'])
        ->whereUlid('location')
        ->middleware(RequirePermissionAndScope::class.':organization.locations.manage')
        ->name('locations.destroy');
    Route::get('/map', [OrganizationController::class, 'map'])
        ->middleware(RequirePermissionAndScope::class.':organization.locations.view')
        ->name('map.index');
    Route::get('/territory-report', [OrganizationController::class, 'territoryReport'])
        ->middleware(RequirePermissionAndScope::class.':organization.units.view')
        ->name('territory_report.index');
    Route::get('/church-tree', [OrganizationController::class, 'churchTree'])
        ->middleware(RequirePermissionAndScope::class.':church.churches.view')
        ->name('church_tree.show');
    Route::get('/home-church-tree', [OrganizationController::class, 'homeChurchTree'])
        ->middleware(RequirePermissionAndScope::class.':church.home_churches.view')
        ->name('home_church_tree.show');
});

Route::prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/configurations', [PlatformSettingsController::class, 'configurations'])
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.view')
        ->name('configurations.index');
    Route::put('/configurations', [PlatformSettingsController::class, 'upsertConfiguration'])
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('configurations.upsert');
    Route::get('/feature-flags', [PlatformSettingsController::class, 'featureFlags'])
        ->middleware(RequirePermissionAndScope::class.':platform.feature_flags.view')
        ->name('feature_flags.index');
    Route::put('/feature-flags', [PlatformSettingsController::class, 'upsertFeatureFlag'])
        ->middleware(RequirePermissionAndScope::class.':platform.feature_flags.manage')
        ->name('feature_flags.upsert');
    Route::post('/feature-flags/{featureFlag}/enabled', [PlatformSettingsController::class, 'enableFeatureFlag'])
        ->whereUlid('featureFlag')
        ->middleware(RequirePermissionAndScope::class.':platform.feature_flags.manage')
        ->name('feature_flags.enable');
    Route::delete('/feature-flags/{featureFlag}/enabled', [PlatformSettingsController::class, 'disableFeatureFlag'])
        ->whereUlid('featureFlag')
        ->middleware(RequirePermissionAndScope::class.':platform.feature_flags.manage')
        ->name('feature_flags.disable');

    Route::prefix('storage/object-storage')
        ->name('storage.object_storage.')
        ->group(function (): void {
            Route::get('/', [ObjectStorageController::class, 'show'])
                ->middleware(RequirePermissionAndScope::class.':platform.storage.view')
                ->name('show');
            Route::put('/', [ObjectStorageController::class, 'configure'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.storage.manage',
                    'throttle:admin-storage',
                ])
                ->name('configure');
            Route::post('/validation', [ObjectStorageController::class, 'validateConnection'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.storage.manage',
                    'throttle:admin-storage',
                ])
                ->name('validate');
            Route::post('/activation', [ObjectStorageController::class, 'activate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.storage.manage',
                    'throttle:admin-storage',
                ])
                ->name('activate');
            Route::delete('/activation', [ObjectStorageController::class, 'deactivate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.storage.manage',
                    'throttle:admin-storage',
                ])
                ->name('deactivate');
        });

    Route::prefix('branding')
        ->name('branding.')
        ->group(function (): void {
            Route::get('/', [PlatformBrandingController::class, 'show'])
                ->middleware(RequirePermissionAndScope::class.':platform.configuration.view')
                ->name('show');
            Route::put('/', [PlatformBrandingController::class, 'update'])
                ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
                ->name('update');
            Route::post('/logo', [PlatformBrandingController::class, 'uploadLogo'])
                ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
                ->name('logo.store');
            Route::delete('/logo', [PlatformBrandingController::class, 'destroyLogo'])
                ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
                ->name('logo.destroy');
            Route::post('/favicon', [PlatformBrandingController::class, 'uploadFavicon'])
                ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
                ->name('favicon.store');
            Route::delete('/favicon', [PlatformBrandingController::class, 'destroyFavicon'])
                ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
                ->name('favicon.destroy');
        });

    Route::prefix('maps')
        ->name('maps.')
        ->group(function (): void {
            Route::get('/', [MapsProviderController::class, 'show'])
                ->middleware(RequirePermissionAndScope::class.':platform.maps.view')
                ->name('show');
            Route::put('/', [MapsProviderController::class, 'configure'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.maps.manage',
                    'throttle:admin-storage',
                ])
                ->name('configure');
            Route::post('/activation', [MapsProviderController::class, 'activate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.maps.manage',
                    'throttle:admin-storage',
                ])
                ->name('activate');
            Route::delete('/activation', [MapsProviderController::class, 'deactivate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.maps.manage',
                    'throttle:admin-storage',
                ])
                ->name('deactivate');
        });

    Route::prefix('payments')
        ->name('payments.')
        ->group(function (): void {
            Route::get('/', [PaymentProviderController::class, 'show'])
                ->middleware(RequirePermissionAndScope::class.':platform.payments.view')
                ->name('show');
            Route::put('/', [PaymentProviderController::class, 'configure'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.payments.manage',
                    'throttle:admin-storage',
                ])
                ->name('configure');
            Route::post('/activation', [PaymentProviderController::class, 'activate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.payments.manage',
                    'throttle:admin-storage',
                ])
                ->name('activate');
            Route::delete('/activation', [PaymentProviderController::class, 'deactivate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.payments.manage',
                    'throttle:admin-storage',
                ])
                ->name('deactivate');
        });

    Route::prefix('communications')
        ->name('communications.')
        ->group(function (): void {
            Route::get('/', [CommunicationProviderController::class, 'show'])
                ->middleware(RequirePermissionAndScope::class.':platform.communications.view')
                ->name('show');
            Route::put('/', [CommunicationProviderController::class, 'configure'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.communications.manage',
                    'throttle:admin-storage',
                ])
                ->name('configure');
            Route::post('/activation', [CommunicationProviderController::class, 'activate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.communications.manage',
                    'throttle:admin-storage',
                ])
                ->name('activate');
            Route::delete('/activation', [CommunicationProviderController::class, 'deactivate'])
                ->middleware([
                    RequirePermissionAndScope::class.':platform.communications.manage',
                    'throttle:admin-storage',
                ])
                ->name('deactivate');
        });

    Route::post('/files', [FileOperationsController::class, 'store'])
        ->middleware(RequirePermissionAndScope::class.':platform.files.manage')
        ->name('files.store');
    Route::get('/files/{file}/content', [FileOperationsController::class, 'stream'])
        ->whereUlid('file')
        ->middleware(RequirePermissionAndScope::class.':platform.files.view')
        ->name('files.content');
    Route::post('/files/{file}/approval', [FileOperationsController::class, 'approve'])
        ->whereUlid('file')
        ->middleware(RequirePermissionAndScope::class.':platform.files.approve')
        ->name('files.approve');

    Route::get('/media', [MediaOperationsController::class, 'index'])
        ->middleware(RequirePermissionAndScope::class.':platform.files.manage')
        ->name('media.index');
    Route::post('/media', [MediaOperationsController::class, 'store'])
        ->middleware(RequirePermissionAndScope::class.':platform.files.manage')
        ->name('media.store');
    Route::post('/media/uploads', [MediaOperationsController::class, 'upload'])
        ->middleware(RequirePermissionAndScope::class.':platform.files.manage')
        ->name('media.uploads.store');
    Route::delete('/media/{media}', [MediaOperationsController::class, 'destroy'])
        ->whereUlid('media')
        ->middleware(RequirePermissionAndScope::class.':platform.files.manage')
        ->name('media.destroy');

    Route::get('/demo', [DemoDatasetController::class, 'show'])
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.view')
        ->name('demo.show');
    Route::post('/demo/wipe', [DemoDatasetController::class, 'wipe'])
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('demo.wipe');
    Route::post('/search/queries', [SearchOperationsController::class, 'query'])
        ->middleware(RequirePermissionAndScope::class.':platform.search.query')
        ->name('search.queries.store');
    Route::post('/advisory/requests', [AdvisoryAiOperationsController::class, 'advise'])
        ->middleware(RequirePermissionAndScope::class.':platform.advisory.request')
        ->name('advisory.requests.store');
});

Route::prefix('church')->name('church.')->controller(ChurchOperationsController::class)->group(function (): void {
    Route::get('/churches', 'churches')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('churches.index');
    Route::post('/churches', 'storeChurch')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('churches.store');
    Route::get('/churches/{church}', 'showChurch')->whereUlid('church')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('churches.show');
    Route::put('/churches/{church}', 'updateChurch')->whereUlid('church')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('churches.update');
    Route::post('/churches/{church}/status', 'updateChurchStatus')->whereUlid('church')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('churches.status');
    Route::delete('/churches/{church}', 'destroyChurch')->whereUlid('church')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('churches.destroy');
    Route::get('/home-churches', 'homeChurches')->middleware(RequirePermissionAndScope::class.':church.home_churches.view')->name('home_churches.index');
    Route::post('/home-churches', 'storeHomeChurch')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.manage')->name('home_churches.store');
    Route::get('/home-churches/{homeChurch}', 'showHomeChurch')->whereUlid('homeChurch')->middleware(RequirePermissionAndScope::class.':church.home_churches.view')->name('home_churches.show');
    Route::put('/home-churches/{homeChurch}', 'updateHomeChurch')->whereUlid('homeChurch')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.manage')->name('home_churches.update');
    Route::post('/home-churches/{homeChurch}/status', 'updateHomeChurchStatus')->whereUlid('homeChurch')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.manage')->name('home_churches.status');
    Route::get('/home-church-applications', 'applications')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.review')->name('home_church_applications.index');
    Route::post('/home-church-applications', 'storeApplication')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.manage')->name('home_church_applications.store');
    Route::get('/home-church-applications/{application}', 'showApplication')->whereUlid('application')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.review')->name('home_church_applications.show');
    Route::post('/home-church-applications/{application}/transitions', 'transitionApplication')->whereUlid('application')->middleware(RequirePermissionAndScope::class.':church.home_church_applications.review')->name('home_church_applications.transitions.store');
    Route::get('/memberships', 'memberships')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('memberships.index');
    Route::post('/memberships', 'startMembership')->middleware(RequirePermissionAndScope::class.':church.memberships.manage')->name('memberships.store');
    Route::post('/memberships/{membership}/end', 'endMembership')->whereUlid('membership')->middleware(RequirePermissionAndScope::class.':church.memberships.manage')->name('memberships.end');
    Route::get('/first-timers', 'firstTimers')->middleware(RequirePermissionAndScope::class.':church.first_timers.view')->name('first_timers.index');
    Route::post('/first-timers', 'registerFirstTimer')->middleware(RequirePermissionAndScope::class.':church.first_timers.manage')->name('first_timers.store');
    Route::put('/first-timers/{firstTimer}', 'updateFirstTimer')->whereUlid('firstTimer')->middleware(RequirePermissionAndScope::class.':church.first_timers.manage')->name('first_timers.update');
    Route::delete('/first-timers/{firstTimer}', 'destroyFirstTimer')->whereUlid('firstTimer')->middleware(RequirePermissionAndScope::class.':church.first_timers.manage')->name('first_timers.destroy');
    Route::get('/follow-up-tasks', 'followUpTasks')->middleware(RequirePermissionAndScope::class.':church.follow_up.view')->name('follow_up_tasks.index');
    Route::post('/follow-up-tasks', 'storeFollowUpTask')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('follow_up_tasks.store');
    Route::put('/follow-up-tasks/{task}', 'updateFollowUpTask')->whereUlid('task')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('follow_up_tasks.update');
    Route::post('/follow-up-tasks/{task}/completion', 'completeFollowUpTask')->whereUlid('task')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('follow_up_tasks.complete');
    Route::get('/prayer-requests', 'prayerRequests')->middleware(RequirePermissionAndScope::class.':church.follow_up.view')->name('prayer_requests.index');
    Route::post('/prayer-requests', 'storePrayerRequest')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('prayer_requests.store');
    Route::put('/prayer-requests/{prayer}', 'updatePrayerRequest')->whereUlid('prayer')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('prayer_requests.update');
    Route::post('/prayer-requests/{prayer}/assignments', 'assignPrayerRequest')->whereUlid('prayer')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('prayer_requests.assignments.store');
    Route::post('/prayer-requests/{prayer}/transitions', 'transitionPrayerRequest')->whereUlid('prayer')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('prayer_requests.transitions.store');
    Route::get('/pastoral-needs', 'pastoralNeeds')->middleware(RequirePermissionAndScope::class.':church.follow_up.view')->name('pastoral_needs.index');
    Route::post('/pastoral-needs', 'storePastoralNeed')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('pastoral_needs.store');
    Route::put('/pastoral-needs/{need}', 'updatePastoralNeed')->whereUlid('need')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('pastoral_needs.update');
    Route::post('/pastoral-needs/{need}/transitions', 'transitionPastoralNeed')->whereUlid('need')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('pastoral_needs.transitions.store');
});

Route::prefix('church')->name('church.ministry.')->controller(ChurchMinistryOperationsController::class)->group(function (): void {
    Route::get('/people', 'people')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('people.index');
    Route::post('/people/matches', 'matchPeople')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('people.matches');
    Route::post('/people', 'storePerson')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('people.store');
    Route::get('/people/{person}', 'showPerson')->whereUlid('person')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('people.show');
    Route::put('/people/{person}', 'updatePersonPhone')->whereUlid('person')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('people.update');
    Route::post('/people/{person}/merge', 'mergePeople')->whereUlid('person')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('people.merge');
    Route::post('/people/{person}/archive', 'archivePerson')->whereUlid('person')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('people.archive');
    Route::get('/converts', 'converts')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('converts.index');
    Route::post('/converts', 'storeConvert')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('converts.store');
    Route::put('/converts/{convert}', 'updateConvert')->whereUlid('convert')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('converts.update');
    Route::delete('/converts/{convert}', 'destroyConvert')->whereUlid('convert')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('converts.destroy');
    Route::get('/evangelism-activities', 'evangelismActivities')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('evangelism.index');
    Route::post('/evangelism-activities', 'storeEvangelismActivity')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('evangelism.store');
    Route::put('/evangelism-activities/{activity}', 'updateEvangelismActivity')->whereUlid('activity')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('evangelism.update');
    Route::delete('/evangelism-activities/{activity}', 'destroyEvangelismActivity')->whereUlid('activity')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('evangelism.destroy');
    Route::get('/departments', 'departments')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('departments.index');
    Route::post('/departments', 'storeDepartment')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('departments.store');
    Route::put('/departments/{department}', 'updateDepartment')->whereUlid('department')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('departments.update');
    Route::delete('/departments/{department}', 'destroyDepartment')->whereUlid('department')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('departments.destroy');
    Route::get('/workers', 'workers')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('workers.index');
    Route::get('/leaders', 'leaders')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('leaders.index');
    Route::get('/disciples', 'disciples')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('disciples.index');
    Route::post('/role-assignments', 'storeRoleAssignment')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('role_assignments.store');
    Route::put('/role-assignments/{assignment}', 'updateRoleAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('role_assignments.update');
    Route::delete('/role-assignments/{assignment}', 'destroyRoleAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('role_assignments.destroy');
    Route::get('/counselling-cases', 'counsellingCases')->middleware(RequirePermissionAndScope::class.':church.follow_up.view')->name('counselling.index');
    Route::post('/counselling-cases', 'storeCounsellingCase')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('counselling.store');
    Route::get('/counselling-cases/{case}', 'showCounsellingCase')->whereUlid('case')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('counselling.show');
    Route::put('/counselling-cases/{case}', 'updateCounsellingCase')->whereUlid('case')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('counselling.update');
    Route::delete('/counselling-cases/{case}', 'destroyCounsellingCase')->whereUlid('case')->middleware(RequirePermissionAndScope::class.':church.follow_up.complete')->name('counselling.destroy');
    Route::get('/testimonies', 'testimonies')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('testimonies.index');
    Route::post('/testimonies', 'storeTestimony')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('testimonies.store');
    Route::put('/testimonies/{testimony}', 'updateTestimony')->whereUlid('testimony')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('testimonies.update');
    Route::delete('/testimonies/{testimony}', 'destroyTestimony')->whereUlid('testimony')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('testimonies.destroy');
    Route::get('/attendance', 'attendance')->middleware(RequirePermissionAndScope::class.':church.home_churches.view')->name('attendance.index');
    Route::post('/attendance', 'storeAttendance')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('attendance.store');
    Route::put('/attendance/{attendance}', 'updateAttendance')->whereUlid('attendance')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('attendance.update');
    Route::delete('/attendance/{attendance}', 'destroyAttendance')->whereUlid('attendance')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('attendance.destroy');
    Route::get('/groups', 'churchGroups')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('groups.index');
    Route::post('/groups', 'storeChurchGroup')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('groups.store');
    Route::put('/groups/{group}', 'updateChurchGroup')->whereUlid('group')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('groups.update');
    Route::delete('/groups/{group}', 'destroyChurchGroup')->whereUlid('group')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('groups.destroy');
    Route::get('/announcements', 'announcements')->middleware(RequirePermissionAndScope::class.':church.churches.view')->name('announcements.index');
    Route::post('/announcements', 'storeAnnouncement')->middleware(RequirePermissionAndScope::class.':church.churches.manage')->name('announcements.store');
});

Route::prefix('safeguarding')->name('safeguarding.')->controller(SafeguardingOperationsController::class)->group(function (): void {
    Route::get('/incidents', [ChurchMinistryOperationsController::class, 'safeguardingIncidents'])->middleware(RequirePermissionAndScope::class.':safeguarding.incidents.report')->name('incidents.index');
    Route::get('/incidents/{incident}', 'showIncident')->whereUlid('incident')->middleware(RequirePermissionAndScope::class.':safeguarding.incidents.report')->name('incidents.show');
    Route::post('/incidents', 'reportIncident')->middleware(RequirePermissionAndScope::class.':safeguarding.incidents.report')->name('incidents.store');
    Route::patch('/incidents/{incident}', 'updateIncident')->whereUlid('incident')->middleware(RequirePermissionAndScope::class.':safeguarding.incidents.report')->name('incidents.update');
    Route::post('/guardian-relationships', 'registerGuardian')->middleware(RequirePermissionAndScope::class.':safeguarding.guardians.register')->name('guardian_relationships.store');
    Route::post('/child-profiles', 'registerChildProfile')->middleware(RequirePermissionAndScope::class.':safeguarding.guardians.register')->name('child_profiles.store');
    Route::patch('/child-profiles/{profile}', 'updateChildProfile')->whereUlid('profile')->middleware(RequirePermissionAndScope::class.':safeguarding.guardians.register')->name('child_profiles.update');
});

Route::prefix('mission')->name('mission.')->controller(MissionOperationsController::class)->group(function (): void {
    Route::get('/crusades', 'crusades')->middleware(RequirePermissionAndScope::class.':mission.crusades.view')->name('crusades.index');
    Route::post('/crusades', 'storeCrusade')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('crusades.store');
    Route::get('/crusades/{crusade}', 'showCrusade')->whereUlid('crusade')->middleware(RequirePermissionAndScope::class.':mission.crusades.view')->name('crusades.show');
    Route::put('/crusades/{crusade}', 'updateCrusade')->whereUlid('crusade')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('crusades.update');
    Route::post('/crusades/{crusade}/transitions', 'transitionCrusade')->whereUlid('crusade')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('crusades.transitions.store');
    Route::post('/crusades/{crusade}/archive', 'archiveCrusade')->whereUlid('crusade')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('crusades.archive');
    Route::get('/souls', 'souls')->middleware(RequirePermissionAndScope::class.':mission.souls.view')->name('souls.index');
    Route::get('/souls/{soul}', 'showSoul')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.souls.view')->name('souls.show');
    Route::get('/souls/{soul}/follow-ups', 'soulFollowUps')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.follow_up.record')->name('souls.follow_ups.index');
    Route::get('/invitations', 'invitations')->middleware(RequirePermissionAndScope::class.':mission.invitations.manage')->name('invitations.index');
    Route::get('/team-assignments', 'teamAssignments')->middleware(RequirePermissionAndScope::class.':mission.crusades.view')->name('team_assignments.index');
    Route::post('/team-assignments', 'storeTeamAssignment')->middleware(RequirePermissionAndScope::class.':mission.mentors.assign')->name('team_assignments.store');
    Route::post('/team-assignments/{assignment}/end', 'endTeamAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':mission.mentors.assign')->name('team_assignments.end');
    Route::post('/invitations', 'storeInvitation')->middleware(RequirePermissionAndScope::class.':mission.invitations.manage')->name('invitations.store');
    Route::post('/crusades/{crusade}/souls', 'captureSoul')->whereUlid('crusade')->middleware(RequirePermissionAndScope::class.':mission.souls.capture')->name('souls.store');
    Route::post('/souls/{soul}/mentor-assignment', 'assignMentor')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.mentors.assign')->name('souls.mentor_assignment.store');
    Route::post('/souls/{soul}/follow-ups', 'recordFollowUp')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.follow_up.record')->name('souls.follow_ups.store');
    Route::post('/souls/{soul}/follow-up-completion', 'completeFollowUp')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.follow_up.complete')->name('souls.follow_up_completion.store');
    Route::post('/souls/{soul}/conversion', 'convertSoul')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.follow_up.complete')->name('souls.conversion.store');
    Route::post('/souls/{soul}/church-connection', 'connectSoulChurch')->whereUlid('soul')->middleware(RequirePermissionAndScope::class.':mission.mentors.assign')->name('souls.church_connection.store');
    Route::post('/invitations/{invitation}/transitions', 'transitionInvitation')->whereUlid('invitation')->middleware(RequirePermissionAndScope::class.':mission.invitations.transition')->name('invitations.transitions.store');
    Route::get('/follow-up/gaps', 'followUpGaps')->middleware(RequirePermissionAndScope::class.':mission.follow_up.record')->name('follow_up.gaps');
    Route::get('/partners', 'partners')->middleware(RequirePermissionAndScope::class.':mission.crusades.view')->name('partners.index');
    Route::post('/partners', 'storePartner')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('partners.store');
    Route::get('/support-requests', 'supportRequests')->middleware(RequirePermissionAndScope::class.':mission.crusades.view')->name('support_requests.index');
    Route::post('/support-requests', 'storeSupportRequest')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('support_requests.store');
    Route::post('/support-requests/{supportRequest}/transitions', 'transitionSupportRequest')->whereUlid('supportRequest')->middleware(RequirePermissionAndScope::class.':mission.crusades.manage')->name('support_requests.transitions.store');
    Route::get('/reports/summary', 'reportsSummary')->middleware(RequirePermissionAndScope::class.':mission.crusades.view')->name('reports.summary');
});

Route::prefix('kca')->name('kca.')->controller(KcaOperationsController::class)->group(function (): void {
    Route::post('/years', 'storeYear')->middleware(RequirePermissionAndScope::class.':kca.years.manage')->name('years.store');
    Route::post('/years/{year}/cohorts', 'storeCohort')->whereUlid('year')->middleware(RequirePermissionAndScope::class.':kca.cohorts.manage')->name('years.cohorts.store');
    Route::patch('/cohorts/{cohort}', 'updateCohort')->whereUlid('cohort')->middleware(RequirePermissionAndScope::class.':kca.cohorts.manage')->name('cohorts.update');
    Route::post('/modules', 'storeModule')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('modules.store');
    Route::patch('/modules/{module}', 'updateModule')->whereUlid('module')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('modules.update');
    Route::post('/modules/{module}/day-map', 'mapModuleDays')->whereUlid('module')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('modules.day_map');
    Route::post('/modules/{module}/publish', 'publishModule')->whereUlid('module')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('modules.publish');
    Route::post('/modules/{module}/lessons', 'storeLesson')->whereUlid('module')->middleware(RequirePermissionAndScope::class.':kca.lessons.manage')->name('modules.lessons.store');
    Route::patch('/lessons/{lesson}', 'updateLesson')->whereUlid('lesson')->middleware(RequirePermissionAndScope::class.':kca.lessons.manage')->name('lessons.update');
    Route::post('/lessons/{lesson}/chapters', 'storeChapter')->whereUlid('lesson')->middleware(RequirePermissionAndScope::class.':kca.lessons.manage')->name('lessons.chapters.store');
    Route::post('/assignments', 'storeAssignment')->middleware(RequirePermissionAndScope::class.':kca.assignments.transition')->name('assignments.store');
    Route::post('/modules/{module}/prerequisites', 'storePrerequisite')->whereUlid('module')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('modules.prerequisites.store');
    Route::post('/lecturer-assignments', 'storeLecturerAssignment')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('lecturer_assignments.store');
    Route::patch('/lecturer-assignments/{assignment}', 'updateLecturerAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('lecturer_assignments.update');
    Route::delete('/lecturer-assignments/{assignment}', 'destroyLecturerAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('lecturer_assignments.destroy');
    Route::post('/mentor-assignments', 'storeMentorAssignment')->middleware(RequirePermissionAndScope::class.':kca.enrollments.manage')->name('mentor_assignments.store');
    Route::patch('/mentor-assignments/{assignment}', 'updateMentorAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':kca.enrollments.manage')->name('mentor_assignments.update');
    Route::delete('/mentor-assignments/{assignment}', 'destroyMentorAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':kca.enrollments.manage')->name('mentor_assignments.destroy');
    Route::delete('/prerequisites/{prerequisite}', 'destroyPrerequisite')->whereUlid('prerequisite')->middleware(RequirePermissionAndScope::class.':kca.modules.manage')->name('prerequisites.destroy');
    Route::post('/enrollments/{enrollment}/attendance', 'recordAttendance')->whereUlid('enrollment')->middleware(RequirePermissionAndScope::class.':kca.attendance.record')->name('enrollments.attendance.store');
    Route::get('/enrollments/registration-number-preview', 'previewRegistrationNumber')->middleware(RequirePermissionAndScope::class.':kca.enrollments.manage')->name('enrollments.registration_number.preview');
    Route::post('/assessment-results', 'recordAssessmentResults')->middleware(RequirePermissionAndScope::class.':kca.enrollments.manage')->name('assessment_results.store');
    Route::post('/applications', 'storeApplication')->middleware(RequirePermissionAndScope::class.':kca.applications.transition')->name('applications.store');
    Route::post('/applications/{application}/transitions', 'transitionApplication')->whereUlid('application')->middleware(RequirePermissionAndScope::class.':kca.applications.transition')->name('applications.transitions.store');
    Route::get('/applications/{application}/admission-letter', [KcaAdmissionLetterController::class, 'show'])->whereUlid('application')->middleware(RequirePermissionAndScope::class.':kca.applications.view')->name('applications.admission_letter.show');
    Route::post('/applications/{application}/admission-letter/issue', [KcaAdmissionLetterController::class, 'issue'])->whereUlid('application')->middleware(RequirePermissionAndScope::class.':kca.applications.transition')->name('applications.admission_letter.issue');
    Route::get('/applications/{application}/admission-letter/download', [KcaAdmissionLetterController::class, 'download'])->whereUlid('application')->middleware(RequirePermissionAndScope::class.':kca.applications.view')->name('applications.admission_letter.download');
    Route::get('/applications/{application}/admission-letter/assets/{file}', [KcaAdmissionLetterController::class, 'streamAsset'])->whereUlid('application')->whereUlid('file')->middleware(RequirePermissionAndScope::class.':kca.applications.view')->name('applications.admission_letter.assets.stream');
    Route::post('/applications/{application}/orientation/complete', 'completeOrientation')->whereUlid('application')->middleware(RequirePermissionAndScope::class.':kca.applications.transition')->name('applications.orientation.complete');
    Route::post('/applications/{application}/enrollments', 'enroll')->whereUlid('application')->middleware(RequirePermissionAndScope::class.':kca.enrollments.manage')->name('applications.enrollments.store');
    Route::post('/recommendations/{recommendation}/verify', 'verifyRecommendation')->whereUlid('recommendation')->middleware(RequirePermissionAndScope::class.':kca.applications.transition')->name('recommendations.verify');
    Route::post('/assignments/{assignment}/transitions', 'transitionAssignment')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':kca.assignments.transition')->name('assignments.transitions.store');
    Route::post('/assignments/{assignment}/evidence', 'submitEvidence')->whereUlid('assignment')->middleware(RequirePermissionAndScope::class.':kca.evidence.submit')->name('assignments.evidence.store');
    Route::post('/evidence/{evidence}/reviews', 'reviewEvidence')->whereUlid('evidence')->middleware(RequirePermissionAndScope::class.':kca.evidence.review')->name('evidence.reviews.store');
    Route::post('/enrollments/{enrollment}/certificates', 'issueCertificate')->whereUlid('enrollment')->middleware(RequirePermissionAndScope::class.':kca.certificates.issue')->name('enrollments.certificates.store');
    Route::post('/certificates/{certificate}/revocation', 'revokeCertificate')->whereUlid('certificate')->middleware(RequirePermissionAndScope::class.':kca.certificates.revoke')->name('certificates.revocation.store');
    Route::get('/governance', [KcaGovernanceController::class, 'show'])->middleware(RequirePermissionAndScope::class.':kca.governance.view')->name('governance.show');
    Route::put('/governance', [KcaGovernanceController::class, 'configure'])->middleware(RequirePermissionAndScope::class.':kca.governance.manage')->name('governance.update');
    Route::get('/orientation-steps', [KcaOrientationStepController::class, 'index'])->middleware(RequirePermissionAndScope::class.':kca.governance.view')->name('orientation_steps.index');
    Route::put('/orientation-steps', [KcaOrientationStepController::class, 'sync'])->middleware(RequirePermissionAndScope::class.':kca.governance.manage')->name('orientation_steps.sync');
    Route::post('/orientation-sessions', [KcaOrientationSessionController::class, 'store'])->middleware(RequirePermissionAndScope::class.':kca.orientation.manage')->name('orientation_sessions.store');
    Route::get('/orientation-sessions/{session}', [KcaOrientationSessionController::class, 'show'])->whereUlid('session')->middleware(RequirePermissionAndScope::class.':kca.orientation.view')->name('orientation_sessions.show');
    Route::put('/orientation-sessions/{session}', [KcaOrientationSessionController::class, 'update'])->whereUlid('session')->middleware(RequirePermissionAndScope::class.':kca.orientation.manage')->name('orientation_sessions.update');
    Route::delete('/orientation-sessions/{session}', [KcaOrientationSessionController::class, 'destroy'])->whereUlid('session')->middleware(RequirePermissionAndScope::class.':kca.orientation.manage')->name('orientation_sessions.destroy');
});

Route::prefix('press')->name('press.')->controller(PressOperationsController::class)->group(function (): void {
    Route::post('/publications', 'storePublication')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('publications.store');
    Route::get('/publications/{publication}', 'showPublication')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.view')->name('publications.show');
    Route::put('/publications/{publication}', 'updatePublication')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('publications.update');
    Route::delete('/publications/{publication}', 'destroyPublication')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('publications.destroy');
    Route::post('/publications/{publication}/schedule', 'schedulePublication')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.transition')->name('publications.schedule');
    Route::post('/publications/{publication}/transitions', 'transitionPublication')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.transition')->name('publications.transitions.store');
    Route::post('/publications/{publication}/isbn', 'assignIsbn')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.assign_isbn')->name('publications.isbn.store');
    Route::post('/publications/{publication}/contributors', 'addContributor')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('publications.contributors.store');
    Route::post('/publications/{publication}/assets', 'storeAsset')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('publications.assets.store');
    Route::post('/publications/{publication}/reviews', 'storeReview')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.publications.transition')->name('publications.reviews.store');
    Route::post('/authors', 'storeAuthor')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('authors.store');
    Route::put('/authors/{author}', 'updateAuthor')->whereUlid('author')->middleware(RequirePermissionAndScope::class.':press.publications.manage')->name('authors.update');
    Route::post('/publications/{publication}/translations', 'storeTranslation')->whereUlid('publication')->middleware(RequirePermissionAndScope::class.':press.translations.manage')->name('publications.translations.store');
    Route::post('/translations/{translation}/transitions', 'transitionTranslation')->whereUlid('translation')->middleware(RequirePermissionAndScope::class.':press.translations.transition')->name('translations.transitions.store');
});

Route::prefix('events')->name('events.')->controller(EventOperationsController::class)->group(function (): void {
    Route::post('/', 'store')->middleware(RequirePermissionAndScope::class.':events.events.manage')->name('store');
    Route::get('/{event}', 'show')->whereUlid('event')->middleware(RequirePermissionAndScope::class.':events.events.view')->name('show');
    Route::put('/{event}', 'update')->whereUlid('event')->middleware(RequirePermissionAndScope::class.':events.events.manage')->name('update');
    Route::delete('/{event}', 'destroy')->whereUlid('event')->middleware(RequirePermissionAndScope::class.':events.events.manage')->name('destroy');
    Route::post('/{event}/registrations', 'register')->whereUlid('event')->middleware(RequirePermissionAndScope::class.':events.registrations.manage')->name('registrations.store');
    Route::post('/registrations/{registration}/attendance', 'recordAttendance')->whereUlid('registration')->middleware(RequirePermissionAndScope::class.':events.attendance.record')->name('registrations.attendance.store');
    Route::post('/registrations/{registration}/feedback', 'recordFeedback')->whereUlid('registration')->middleware(RequirePermissionAndScope::class.':events.feedback.record')->name('registrations.feedback.store');
});

Route::prefix('finance')->name('finance.')->controller(FinanceOperationsController::class)->group(function (): void {
    Route::get('/payment-transactions/{transaction}', 'showTransaction')->whereUlid('transaction')->middleware(RequirePermissionAndScope::class.':finance.payment_transactions.view')->name('payment_transactions.show');
    Route::post('/payment-intents', 'createIntent')->middleware(RequirePermissionAndScope::class.':finance.payment_intents.create')->name('payment_intents.store');
    Route::post('/payment-transactions/{transaction}/refunds', 'requestRefund')->whereUlid('transaction')->middleware(RequirePermissionAndScope::class.':finance.payment_refunds.request')->name('payment_transactions.refunds.store');
    Route::post('/payment-transactions/{transaction}/reconciliations', 'reconcileTransaction')->whereUlid('transaction')->middleware(RequirePermissionAndScope::class.':finance.payment_reconciliations.manage')->name('payment_transactions.reconciliations.store');
    Route::post('/payment-transactions/{transaction}/receipts', 'issueReceipt')->whereUlid('transaction')->middleware(RequirePermissionAndScope::class.':finance.payment_receipts.issue')->name('payment_transactions.receipts.store');
});

Route::prefix('communications')->name('communications.')->controller(CommunicationOperationsController::class)->group(function (): void {
    Route::post('/templates', 'storeTemplate')->middleware(RequirePermissionAndScope::class.':communications.templates.manage')->name('templates.store');
    Route::post('/audiences', 'storeAudience')->middleware(RequirePermissionAndScope::class.':communications.audiences.manage')->name('audiences.store');
    Route::post('/broadcasts', 'prepareBroadcast')->middleware(RequirePermissionAndScope::class.':communications.broadcasts.prepare')->name('broadcasts.store');
    Route::post('/broadcasts/{broadcast}/resolve', 'resolveBroadcast')->whereUlid('broadcast')->middleware(RequirePermissionAndScope::class.':communications.broadcasts.resolve')->name('broadcasts.resolve');
    Route::post('/recipients/{recipient}/deliveries', 'attemptDelivery')->whereUlid('recipient')->middleware(RequirePermissionAndScope::class.':communications.deliveries.attempt')->name('recipients.deliveries.store');
    Route::post('/recipients/{recipient}/notifications', 'createNotification')->whereUlid('recipient')->middleware(RequirePermissionAndScope::class.':communications.notifications.create')->name('recipients.notifications.store');
});

Route::put('/livestreams/current', [LivestreamOperationsController::class, 'upsert'])
    ->middleware(RequirePermissionAndScope::class.':platform.communications.manage')
    ->name('livestreams.current.upsert');

Route::prefix('reporting')->name('reporting.')->controller(ReportingOperationsController::class)->group(function (): void {
    Route::post('/alert-rules', 'storeRule')->middleware(RequirePermissionAndScope::class.':reporting.alert_rules.manage')->name('alert_rules.store');
    Route::post('/alert-rules/{alertRule}/enabled', 'setEnabled')->whereUlid('alertRule')->middleware(RequirePermissionAndScope::class.':reporting.alert_rules.manage')->name('alert_rules.enabled');
    Route::post('/alert-rules/{alertRule}/evaluations', 'evaluate')->whereUlid('alertRule')->middleware(RequirePermissionAndScope::class.':reporting.alert_rules.evaluate')->name('alert_rules.evaluations.store');
    Route::post('/alert-occurrences/{occurrence}/acknowledgement', 'acknowledge')->whereUlid('occurrence')->middleware(RequirePermissionAndScope::class.':reporting.alert_occurrences.acknowledge')->name('alert_occurrences.acknowledge');
    Route::post('/alert-occurrences/{occurrence}/resolution', 'resolve')->whereUlid('occurrence')->middleware(RequirePermissionAndScope::class.':reporting.alert_occurrences.resolve')->name('alert_occurrences.resolve');
});

Route::prefix('privacy')->name('privacy.')->controller(PrivacyOperationsController::class)->group(function (): void {
    Route::post('/data-subject-requests', 'submit')->middleware(RequirePermissionAndScope::class.':privacy.data_subject_requests.submit')->name('data_subject_requests.store');
    Route::post('/data-subject-requests/{dataSubjectRequest}/exports/begin', 'beginExport')->whereUlid('dataSubjectRequest')->middleware(RequirePermissionAndScope::class.':privacy.data_exports.begin')->name('data_subject_requests.exports.begin');
    Route::post('/data-subject-requests/{dataSubjectRequest}/exports/complete', 'completeExport')->whereUlid('dataSubjectRequest')->middleware(RequirePermissionAndScope::class.':privacy.data_exports.complete')->name('data_subject_requests.exports.complete');
    Route::post('/data-subject-requests/{dataSubjectRequest}/exports/expire', 'expireExport')->whereUlid('dataSubjectRequest')->middleware(RequirePermissionAndScope::class.':privacy.data_exports.expire')->name('data_subject_requests.exports.expire');
    Route::post('/data-subject-requests/{dataSubjectRequest}/erasure', 'executeErasure')->whereUlid('dataSubjectRequest')->middleware(RequirePermissionAndScope::class.':privacy.data_subject_requests.submit')->name('data_subject_requests.erasure');
    Route::post('/data-subject-requests/{dataSubjectRequest}/erasure-denial', 'recordErasureDenial')->whereUlid('dataSubjectRequest')->middleware(RequirePermissionAndScope::class.':privacy.data_subject_requests.submit')->name('data_subject_requests.erasure_denial');
});

Route::prefix('catalog')->name('catalog.')->group(function (): void {
    foreach ((new ProtectedDomainRegistry)->definitions() as $catalog => $definition) {
        Route::get($definition['path'], [DomainCatalogController::class, 'index'])
            ->defaults('catalog', $catalog)
            ->middleware(RequirePermissionAndScope::class.':'.$definition['permission'])
            ->name(str_replace('.', '_', $catalog));
    }
});

/*
| Content CMS is gated with platform.configuration.manage (no dedicated
| content.content.manage permission seed yet). Replace when content permissions land.
*/
Route::prefix('content')->name('content.')->group(function (): void {
    Route::get('/pages', [ContentAdministrationController::class, 'index'])
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('pages.index');
    Route::post('/pages', [ContentAdministrationController::class, 'store'])
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('pages.store');
    Route::put('/pages/{page}', [ContentAdministrationController::class, 'update'])
        ->whereUlid('page')
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('pages.update');
    Route::post('/pages/{page}/items', [ContentAdministrationController::class, 'storeItem'])
        ->whereUlid('page')
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('pages.items.store');
    Route::put('/pages/{page}/items/{item}', [ContentAdministrationController::class, 'updateItem'])
        ->whereUlid('page')
        ->whereUlid('item')
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('pages.items.update');
    Route::delete('/pages/{page}/items/{item}', [ContentAdministrationController::class, 'destroyItem'])
        ->whereUlid('page')
        ->whereUlid('item')
        ->middleware(RequirePermissionAndScope::class.':platform.configuration.manage')
        ->name('pages.items.destroy');
});
