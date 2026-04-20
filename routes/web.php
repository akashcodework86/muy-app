<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CatalogServiceController;
use App\Http\Controllers\Admin\CfaSubmissionController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\HubBatchComplianceController;
use App\Http\Controllers\Admin\LegacyPhase2CfaApplicationController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StaffDeliverableMonthlyTargetController;
use App\Http\Controllers\Admin\TargetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hub\HubBatchController;
use App\Http\Controllers\Incubatee\IncubateeDashboardController;
use App\Http\Controllers\Incubatee\MentorshipRequestController;
use App\Http\Controllers\LiveOpsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\CfaApplyController;
use App\Http\Controllers\Public\PublicCfaWalkInController;
use App\Http\Controllers\Staff\IncubateeServiceCaseController;
use App\Http\Controllers\Staff\StaffPortalController;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\User;
use App\Services\TargetValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/assets/mascot/lakhpati-didi.png', function () {
    $path = public_path('lakhpati_didi.png');
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('assets.mascot.lakhpati-didi');

/** Public CFA (referral link from district staff) */
Route::get('/cfa/apply/{token}', [CfaApplyController::class, 'show'])->name('cfa.apply');
Route::post('/cfa/apply/{token}/check-phone', [CfaApplyController::class, 'checkPhone'])
    ->middleware('throttle:45,1')
    ->name('cfa.apply.check-phone');
Route::post('/cfa/apply/{token}', [CfaApplyController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('cfa.apply.store');
Route::get('/cfa/thanks', [CfaApplyController::class, 'thanks'])->name('cfa.thanks');

/** Public walk-in CFA (no referral token — self-noted, not counted in staff achievements) */
Route::get('/cfa/public', [PublicCfaWalkInController::class, 'show'])->name('cfa.public.show');
Route::get('/api/cfa/blocks', [PublicCfaWalkInController::class, 'blocks'])->name('api.cfa.blocks');
Route::post('/cfa/public/check-phone', [PublicCfaWalkInController::class, 'checkPhone'])
    ->middleware('throttle:45,1')
    ->name('cfa.public.check-phone');
Route::post('/cfa/public', [PublicCfaWalkInController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('cfa.public.store');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::prefix('api/live-ops')->name('live-ops.')->middleware('throttle:120,1')->group(function (): void {
        Route::get('presence', [LiveOpsController::class, 'presence'])->name('presence');
        Route::get('activities', [LiveOpsController::class, 'activities'])->name('activities');
    });

    Route::middleware('incubatee')->prefix('incubatee')->name('incubatee.')->group(function () {
        Route::get('dashboard', [IncubateeDashboardController::class, 'index'])->name('dashboard');
        Route::get('udmita-kosh', [IncubateeDashboardController::class, 'udmitaKosh'])->name('udmita-kosh');
        Route::get('mentorship', [MentorshipRequestController::class, 'index'])->name('mentorship.index');
        Route::post('mentorship-requests', [MentorshipRequestController::class, 'store'])
            ->middleware('throttle:15,1')
            ->name('mentorship-requests.store');
    });

    Route::prefix('account')->name('account.')->group(function () {
        /** Serves file from disk so images work even if public/storage symlink is missing */
        Route::get('avatar', [ProfileController::class, 'showAvatar'])
            ->middleware('throttle:120,1')
            ->name('avatar.show');
        Route::get('settings', [ProfileController::class, 'edit'])->name('settings.edit');
        Route::put('settings/profile', [ProfileController::class, 'updateProfile'])->name('settings.profile.update');
        Route::put('settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password.update');
        Route::post('settings/avatar', [ProfileController::class, 'updateAvatar'])
            ->middleware('throttle:20,1')
            ->name('settings.avatar.update');
        Route::delete('settings/avatar', [ProfileController::class, 'destroyAvatar'])->name('settings.avatar.destroy');
    });

    Route::middleware('district_staff')->prefix('my')->name('staff.')->group(function () {
        Route::get('monthly-targets', [StaffPortalController::class, 'monthlyTargets'])->name('monthly-targets');
        Route::get('cfa-targets', function (Request $request) {
            return redirect()->route('staff.monthly-targets', $request->query(), 301);
        });
        Route::get('applications', [StaffPortalController::class, 'applications'])->name('applications');
        Route::get('applications/{cfa_submission}', [StaffPortalController::class, 'showCfaSubmission'])->name('applications.show');
        Route::get('applications/{cfa_submission}/edit', [StaffPortalController::class, 'editCfaSubmission'])->name('applications.edit');
        Route::put('applications/{cfa_submission}', [StaffPortalController::class, 'updateCfaSubmission'])
            ->middleware('throttle:30,1')
            ->name('applications.update');
        Route::post('applications/{cfa_submission}/service-cases', [IncubateeServiceCaseController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('applications.service-cases.store');
        Route::patch('applications/{cfa_submission}/service-cases/{service_case}', [IncubateeServiceCaseController::class, 'complete'])
            ->middleware('throttle:30,1')
            ->name('applications.service-cases.complete');
        Route::post('applications/{cfa_submission}/check-phone', [StaffPortalController::class, 'checkPhoneForEdit'])
            ->middleware('throttle:45,1')
            ->name('applications.check-phone');
        Route::get('phase2-data', [StaffPortalController::class, 'phase2Data'])->name('phase2-data');
        Route::get('phase2-data/export', [StaffPortalController::class, 'exportPhase2Data'])->name('phase2-data.export');
    });

    Route::middleware('state_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('cfa-applications', [CfaSubmissionController::class, 'index'])->name('cfa.index');
        Route::get('cfa-applications/{cfa_submission}', [CfaSubmissionController::class, 'show'])->name('cfa.show');
        Route::get('cfa-applications-phase2-legacy', [LegacyPhase2CfaApplicationController::class, 'index'])->name('phase2-cfa.index');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('targets/state', [TargetController::class, 'stateForm'])->name('targets.state');
        Route::post('targets/state', [TargetController::class, 'stateUpdate'])->name('targets.state.update');
        Route::get('targets/district', [TargetController::class, 'districtForm'])->name('targets.district');
        Route::post('targets/district', [TargetController::class, 'districtUpdate'])->name('targets.district.update');

        Route::get('service-catalog', [ServiceCategoryController::class, 'index'])->name('service-catalog.index');
        Route::get('service-catalog/categories/create', [ServiceCategoryController::class, 'create'])->name('service-catalog.categories.create');
        Route::post('service-catalog/categories', [ServiceCategoryController::class, 'store'])->name('service-catalog.categories.store');
        Route::get('service-catalog/categories/{service_category}/edit', [ServiceCategoryController::class, 'edit'])->name('service-catalog.categories.edit');
        Route::put('service-catalog/categories/{service_category}', [ServiceCategoryController::class, 'update'])->name('service-catalog.categories.update');
        Route::delete('service-catalog/categories/{service_category}', [ServiceCategoryController::class, 'destroy'])->name('service-catalog.categories.destroy');
        Route::get('service-catalog/services/create', [CatalogServiceController::class, 'create'])->name('service-catalog.services.create');
        Route::post('service-catalog/services', [CatalogServiceController::class, 'store'])->name('service-catalog.services.store');
        Route::get('service-catalog/services/{service}/edit', [CatalogServiceController::class, 'edit'])->name('service-catalog.services.edit');
        Route::put('service-catalog/services/{service}', [CatalogServiceController::class, 'update'])->name('service-catalog.services.update');
        Route::delete('service-catalog/services/{service}', [CatalogServiceController::class, 'destroy'])->name('service-catalog.services.destroy');

        Route::get('designations', [DesignationController::class, 'index'])->name('designations.index');
        Route::get('designations/create', [DesignationController::class, 'create'])->name('designations.create');
        Route::post('designations', [DesignationController::class, 'store'])->name('designations.store');
        Route::get('designations/{designation}/edit', [DesignationController::class, 'edit'])->name('designations.edit');
        Route::put('designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        Route::delete('designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
        Route::post('staff/{user}/toggle-active', [StaffController::class, 'toggleActive'])->name('staff.toggle-active');
        Route::get('staff/{user}/monthly-targets', [StaffDeliverableMonthlyTargetController::class, 'index'])->name('staff.monthly-targets.index');
        Route::get('staff/{user}/monthly-targets/{deliverable_code}/edit', [StaffDeliverableMonthlyTargetController::class, 'edit'])
            ->where('deliverable_code', '[a-z0-9_]+')
            ->name('staff.monthly-targets.edit');
        Route::post('staff/{user}/monthly-targets/{deliverable_code}', [StaffDeliverableMonthlyTargetController::class, 'update'])
            ->where('deliverable_code', '[a-z0-9_]+')
            ->name('staff.monthly-targets.update');
        Route::get('staff/{user}/cfa-targets', function (Request $request, User $user) {
            $q = [];
            if ($request->filled('fiscal_year_id')) {
                $q['fiscal_year_id'] = (int) $request->query('fiscal_year_id');
            }

            return redirect()->route('admin.staff.monthly-targets.edit', array_merge(['user' => $user, 'deliverable_code' => 'cfa'], $q), 301);
        })->name('staff.cfa-targets');
        Route::post('staff/{user}/cfa-targets', [StaffDeliverableMonthlyTargetController::class, 'updateCfaLegacy'])->name('staff.cfa-targets.update');

        Route::get('hub-batch-compliance', [HubBatchComplianceController::class, 'index'])->name('hub-batch-compliance.index');
        Route::post('hub-batch-compliance/extend', [HubBatchComplianceController::class, 'extend'])->name('hub-batch-compliance.extend');
        Route::post('hub-batch-compliance/waive', [HubBatchComplianceController::class, 'waive'])->name('hub-batch-compliance.waive');
        Route::post('hub-batch-compliance/undo-reject', [HubBatchComplianceController::class, 'undoReject'])->name('hub-batch-compliance.undo-reject');
    });

    Route::middleware('hub_admin')->prefix('hub')->name('hub.')->group(function () {
        Route::get('batches', [HubBatchController::class, 'index'])->name('batches.index');
        Route::post('batches/api', [HubBatchController::class, 'api'])->name('batches.api');
        Route::post('batches/upload-cdo', [HubBatchController::class, 'uploadCdo'])->name('batches.upload-cdo');
        Route::get('cfa-applications/{cfa_submission}', [HubBatchController::class, 'showCfaSubmission'])->name('batches.cfa.show');
    });
});

/** Phase 0 smoke test: open /status after migrate */
Route::get('/status', function () {
    try {
        DB::select('select 1 as ok');

        return response()->json([
            'app' => config('app.name'),
            'phase' => 0,
            'database' => config('database.default'),
            'connected' => true,
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'app' => config('app.name'),
            'phase' => 0,
            'connected' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

/** Phase 1 catalog counts (after migrate --seed) */
Route::get('/status/catalog', function () {
    try {
        return response()->json([
            'phase' => 1,
            'hubs' => DB::table('hubs')->count(),
            'districts' => DB::table('districts')->count(),
            'fiscal_years' => DB::table('fiscal_years')->count(),
            'deliverables' => DB::table('deliverables')->count(),
            'service_categories' => DB::table('service_categories')->count(),
            'services' => DB::table('services')->count(),
        ]);
    } catch (Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

/** Phase 2: target tables + validation smoke (after migrate --seed) */
Route::get('/status/targets', function (TargetValidationService $validation) {
    try {
        $fy = DB::table('fiscal_years')->orderBy('id')->first();
        $cfa = Deliverable::query()->where('code', 'cfa')->first();
        $almora = District::query()->where('slug', 'almora')->first();

        if (! $fy || ! $cfa || ! $almora) {
            return response()->json(['phase' => 2, 'ok' => false, 'reason' => 'missing_fy_deliverable_or_district'], 422);
        }

        $stateOk = $validation->stateMatchesDistricts((int) $fy->id, $cfa->id);
        $districtOk = $validation->districtMatchesStaffMonthly((int) $fy->id, $almora->id, $cfa->id);

        return response()->json([
            'phase' => 2,
            'fiscal_year_id' => (int) $fy->id,
            'deliverable' => 'cfa',
            'state_matches_districts' => $stateOk,
            'district_almora_matches_staff_monthly' => $districtOk,
        ]);
    } catch (Throwable $e) {
        return response()->json(['phase' => 2, 'error' => $e->getMessage()], 500);
    }
});
