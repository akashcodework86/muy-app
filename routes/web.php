<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CatalogServiceController;
use App\Http\Controllers\Admin\CfaSubmissionController;
use App\Http\Controllers\Admin\DataCentreController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\DistrictSpocController;
use App\Http\Controllers\Admin\DocumentRepositoryController;
use App\Http\Controllers\Admin\BlockWorkshopAdminController;
use App\Http\Controllers\Admin\FieldCoordinatorAttendanceAdminController;
use App\Http\Controllers\Admin\StaffCheckInAdminController;
use App\Http\Controllers\Admin\StateLiveMapController;
use App\Http\Controllers\Admin\GramPanchayatImportController;
use App\Http\Controllers\Admin\HubBatchComplianceController;
use App\Http\Controllers\Admin\LegacyPhase1CfaApplicationController;
use App\Http\Controllers\Admin\LegacyPhase2CfaApplicationController;
use App\Http\Controllers\Admin\MigrationRunController;
use App\Http\Controllers\Admin\OnboardedApplicantController;
use App\Http\Controllers\Admin\PendingActionsController;
use App\Http\Controllers\Admin\SpocApprovalAuditController;
use App\Http\Controllers\Admin\Phase3ServiceCasesController;
use App\Http\Controllers\Admin\ProgrammeStructureWipeController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceModuleSettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\ServiceTargetAllocationController;
use App\Http\Controllers\Admin\StaffDeliverableMonthlyTargetController;
use App\Http\Controllers\Admin\StaffPhase3AttendanceNavController;
use App\Http\Controllers\Admin\StateStaffController;
use App\Http\Controllers\Admin\StateTaskController as AdminStateTaskController;
use App\Http\Controllers\DeliverablesReportController;
use App\Http\Controllers\Admin\TargetController;
use App\Http\Controllers\Admin\TeamDirectoryController;
use App\Http\Controllers\Admin\TeamPerformanceController;
use App\Http\Controllers\Admin\TrainingPackageMonthPlanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BatchReadOnlyController;
use App\Http\Controllers\CommunityOrganizationOutreachController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistrictWorkshopSessionAttendanceController;
use App\Http\Controllers\EapEdpSessionAttendanceController;
use App\Http\Controllers\DocumentLibraryController;
use App\Http\Controllers\FieldCoordinatorReportController;
use App\Http\Controllers\Hub\HubApplicationsController;
use App\Http\Controllers\Hub\HubBatchController;
use App\Http\Controllers\Hub\HubOnboardingInsightController;
use App\Http\Controllers\Hub\HubPendingActionsController;
use App\Http\Controllers\Hub\HubStaffPerformanceController;
use App\Http\Controllers\LakhpatiTechnicalTrainingController;
use App\Http\Controllers\Incubatee\IncubateeDashboardController;
use App\Http\Controllers\Incubatee\MentorshipRequestController;
use App\Http\Controllers\LiveOpsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\CfaApplyController;
use App\Http\Controllers\Public\PublicCfaWalkInController;
use App\Http\Controllers\Staff\BlockWorkshopController;
use App\Http\Controllers\Staff\FieldCoordinatorAttendanceController;
use App\Http\Controllers\Staff\IncubateeServiceCaseController;
use App\Http\Controllers\Staff\LegacyPhase2IncubateeProfileController;
use App\Http\Controllers\Staff\StaffPortalController;
use App\Http\Controllers\StaffCheckInController;
use App\Http\Controllers\MarketLinkageController;
use App\Http\Controllers\CapacityBuildingStakeholdersLandingController;
use App\Http\Controllers\PitchDeckPreparationController;
use App\Http\Controllers\PitchDeckPreparationLandingController;
use App\Http\Controllers\SocialMediaPostLandingController;
use App\Http\Controllers\SocialMediaPostController;
use App\Http\Controllers\StakeholderCapacityBuildingSessionController;
use App\Http\Controllers\Staff\StaffServiceCaseController;
use App\Http\Controllers\StateStaff\SpocMarketLinkageController;
use App\Http\Controllers\Spoc\StateTaskController as SpocStateTaskController;
use App\Http\Controllers\StateStaff\SpocServiceCaseController;
use App\Http\Controllers\TechnicalTrainingAttendanceController;
use App\Http\Controllers\TrainingPackageAttendanceController;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\TargetValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

/** DANGER: runs programme:wipe-structure. ?key= must match PROGRAMME_WIPE_SECRET (.env). Optional &app_settings=1. Remove route + ProgrammeStructureWipeController after one-time use. No throttle — key is the gate; avoids 429 during setup retries. */
Route::get('programme-wipe-run', [ProgrammeStructureWipeController::class, 'execute'])
    ->name('programme-wipe-run');

/** One-shot: runs migrate --force. ?key= must match MIGRATION_RUN_SECRET (.env). Remove after use. */
Route::get('migration-run', [MigrationRunController::class, 'execute'])
    ->name('migration-run');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('team-directory', [TeamDirectoryController::class, 'index'])->name('team.index');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('documents', [DocumentLibraryController::class, 'internalIndex'])->name('library.documents.index');
    Route::get('documents/{document}/download', [DocumentLibraryController::class, 'download'])->name('library.documents.download');

    Route::middleware('staff_daily_check_in')->prefix('check-in')->name('staff-daily-check-in.')->group(function (): void {
        Route::get('/', [StaffCheckInController::class, 'index'])->name('index');
        Route::post('/', [StaffCheckInController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('store');
    });

    Route::prefix('api/live-ops')->name('live-ops.')->middleware('throttle:120,1')->group(function (): void {
        Route::get('presence', [LiveOpsController::class, 'presence'])->name('presence');
        Route::get('activities', [LiveOpsController::class, 'activities'])->name('activities');
    });

    Route::middleware('incubatee')->prefix('incubatee')->name('incubatee.')->group(function () {
        Route::get('dashboard', [IncubateeDashboardController::class, 'index'])->name('dashboard');
        Route::get('udmita-kosh', [IncubateeDashboardController::class, 'udmitaKosh'])->name('udmita-kosh');
        Route::get('documents', [DocumentLibraryController::class, 'incubateeIndex'])->name('documents.index');
        Route::get('mentorship', [MentorshipRequestController::class, 'index'])->name('mentorship.index');
        Route::post('mentorship-requests', [MentorshipRequestController::class, 'store'])
            ->middleware('throttle:15,1')
            ->name('mentorship-requests.store');
    });

    Route::middleware('training_package_month_plan_manager')
        ->prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::get('training-package-month-plans', [TrainingPackageMonthPlanController::class, 'index'])->name('training-package-month-plans.index');
            Route::post('training-package-month-plans', [TrainingPackageMonthPlanController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('training-package-month-plans.store');
            Route::post('training-package-month-plans/assign-default-sessions', [TrainingPackageMonthPlanController::class, 'assignDefaultSessions'])
                ->middleware('throttle:30,1')
                ->name('training-package-month-plans.assign-default-sessions');
            Route::post('training-package-month-plans/clear-all-sessions', [TrainingPackageMonthPlanController::class, 'clearAllSessions'])
                ->middleware('throttle:30,1')
                ->name('training-package-month-plans.clear-all-sessions');
            Route::delete('training-package-month-plans/sessions/{trainingPackageMonthSession}', [TrainingPackageMonthPlanController::class, 'destroySession'])
                ->middleware('throttle:30,1')
                ->name('training-package-month-plans.sessions.destroy');
        });

    Route::prefix('account')->name('account.')->group(function () {
        /** Serves file from disk so images work even if public/storage symlink is missing */
        Route::get('avatar', [ProfileController::class, 'showAvatar'])
            ->middleware('throttle:120,1')
            ->name('avatar.show');
        /** Stream another user's avatar for authenticated internal users (team pages, org charts, etc.) */
        Route::get('users/{user}/avatar', [ProfileController::class, 'showUserAvatar'])
            ->middleware('throttle:240,1')
            ->name('user.avatar.show');
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
        Route::get('deliverables', [DeliverablesReportController::class, 'index'])->name('deliverables.index');
        Route::get('deliverables/breakdown/export', [DeliverablesReportController::class, 'breakdownExport'])->name('deliverables.breakdown.export');
        Route::get('deliverables/breakdown/export/csv', [DeliverablesReportController::class, 'breakdownExportCsv'])->name('deliverables.breakdown.export.csv');
        Route::get('deliverables/breakdown/export/pdf', [DeliverablesReportController::class, 'breakdownExportPdf'])->name('deliverables.breakdown.export.pdf');
        Route::get('deliverables/breakdown', [DeliverablesReportController::class, 'breakdown'])->name('deliverables.breakdown');
        Route::get('deliverables/export', [DeliverablesReportController::class, 'export'])->name('deliverables.export');
        Route::get('cfa-targets', function (Request $request) {
            return redirect()->route('staff.monthly-targets', $request->query(), 301);
        });
        Route::get('applications', [StaffPortalController::class, 'applications'])->name('applications');
        Route::get('applications/export', [StaffPortalController::class, 'applicationsExport'])
            ->middleware('throttle:15,1')
            ->name('applications.export');
        Route::get('applications/{cfa_submission}/export-csv', [StaffPortalController::class, 'exportSingleCfaSubmission'])
            ->middleware('throttle:30,1')
            ->name('applications.export-single');
        Route::get('applications/{cfa_submission}', [StaffPortalController::class, 'showCfaSubmission'])->name('applications.show');
        Route::get('applications/{cfa_submission}/edit', [StaffPortalController::class, 'editCfaSubmission'])->name('applications.edit');
        Route::get('onboarded', [OnboardedApplicantController::class, 'index'])->name('onboarded.index');
        Route::get('onboarded/export', [OnboardedApplicantController::class, 'export'])->name('onboarded.export');
        Route::get('field-coordinator-reports', [FieldCoordinatorReportController::class, 'index'])->name('field-coordinator-reports.index');
        Route::get('field-coordinator-reports/{attendanceReport}/attachment', [FieldCoordinatorReportController::class, 'downloadAttachment'])->name('field-coordinator-reports.attachment');
        Route::get('field-coordinator-reports/{attendanceReport}/attendance-sheet', [FieldCoordinatorReportController::class, 'downloadAttendanceSheet'])->name('field-coordinator-reports.sheet');
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
        Route::get('phase1-data', [StaffPortalController::class, 'phase1Data'])->name('phase1-data');
        Route::get('phase2-data', [StaffPortalController::class, 'phase2Data'])->name('phase2-data');
        Route::get('phase2-data/export', [StaffPortalController::class, 'exportPhase2Data'])->name('phase2-data.export');
        Route::get('phase2-applications/logo-mark', [LegacyPhase2IncubateeProfileController::class, 'headerLogo'])
            ->middleware('throttle:60,1')
            ->name('phase2-profile.logo');
        Route::get('phase2-applications/{legacy_application}', [LegacyPhase2IncubateeProfileController::class, 'show'])
            ->whereNumber('legacy_application')
            ->name('phase2-profile.show');
        Route::get('phase2-applications/{legacy_application}/photo', [LegacyPhase2IncubateeProfileController::class, 'photo'])
            ->whereNumber('legacy_application')
            ->middleware('throttle:120,1')
            ->name('phase2-profile.photo');
        Route::post('phase2-applications/{legacy_application}/photo', [LegacyPhase2IncubateeProfileController::class, 'uploadPhoto'])
            ->whereNumber('legacy_application')
            ->middleware('throttle:15,1')
            ->name('phase2-profile.photo.upload');
        Route::post('phase2-applications/{legacy_application}/lakhpati', [LegacyPhase2IncubateeProfileController::class, 'updateLakhpati'])
            ->whereNumber('legacy_application')
            ->middleware('throttle:30,1')
            ->name('phase2-profile.lakhpati.update');
        Route::get('attendance', [FieldCoordinatorAttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/draft', [FieldCoordinatorAttendanceController::class, 'createDraft'])
            ->middleware('throttle:30,1')
            ->name('attendance.draft.create');
        Route::patch('attendance/{attendanceReport}/draft-meta', [FieldCoordinatorAttendanceController::class, 'updateDraftMeta'])
            ->middleware('throttle:60,1')
            ->name('attendance.draft.meta');
        Route::patch('attendance/{attendanceReport}/participants', [FieldCoordinatorAttendanceController::class, 'saveParticipants'])
            ->middleware('throttle:60,1')
            ->name('attendance.participants.save');
        Route::get('attendance/{attendanceReport}/submit', [FieldCoordinatorAttendanceController::class, 'redirectSubmitPage'])
            ->name('attendance.draft.submit.redirect');
        Route::post('attendance/{attendanceReport}/submit', [FieldCoordinatorAttendanceController::class, 'submitDraft'])
            ->middleware('throttle:30,1')
            ->name('attendance.draft.submit');
        Route::post('attendance/{attendanceReport}/photos', [FieldCoordinatorAttendanceController::class, 'uploadPhotos'])
            ->middleware('throttle:30,1')
            ->name('attendance.photos.upload');
        Route::delete('attendance/{attendanceReport}/photos/{photoIndex}', [FieldCoordinatorAttendanceController::class, 'deletePhoto'])
            ->middleware('throttle:30,1')
            ->whereNumber('photoIndex')
            ->name('attendance.photos.delete');
        Route::get('attendance/attendance-sheet-template', [FieldCoordinatorAttendanceController::class, 'downloadAttendanceSheetTemplate'])->name('attendance.sheet-template');
        Route::get('attendance/gram-panchayats', [FieldCoordinatorAttendanceController::class, 'gramPanchayats'])->name('attendance.gram-panchayats');
        Route::get('attendance/view', [FieldCoordinatorAttendanceController::class, 'view'])->name('attendance.view');
        Route::get('attendance/{attendanceReport}/show', [FieldCoordinatorAttendanceController::class, 'show'])->name('attendance.show');
        Route::get('attendance/{attendanceReport}/participants-export', [FieldCoordinatorAttendanceController::class, 'exportParticipants'])->name('attendance.participants.export');
        Route::get('attendance/{attendanceReport}/attachment', [FieldCoordinatorAttendanceController::class, 'downloadAttachment'])->name('attendance.attachment');
        Route::get('attendance/{attendanceReport}/edit', [FieldCoordinatorAttendanceController::class, 'edit'])->name('attendance.edit');
        Route::put('attendance/{attendanceReport}', [FieldCoordinatorAttendanceController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('attendance.update');
        Route::post('attendance/{attendanceReport}/save', [FieldCoordinatorAttendanceController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('attendance.save');
        Route::delete('attendance/{attendanceReport}', [FieldCoordinatorAttendanceController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('attendance.destroy');
        Route::get('attendance/{attendanceReport}/attendance-sheet-template', [FieldCoordinatorAttendanceController::class, 'downloadAttendanceSheetTemplateForReport'])->name('attendance.sheet-template.report');
        Route::get('attendance/{attendanceReport}/attendance-sheet', [FieldCoordinatorAttendanceController::class, 'downloadAttendanceSheet'])->name('attendance.sheet.download');
        Route::post('attendance/{attendanceReport}/attendance-sheet', [FieldCoordinatorAttendanceController::class, 'uploadAttendanceSheet'])
            ->middleware('throttle:30,1')
            ->name('attendance.sheet.upload');
        Route::post('attendance', [FieldCoordinatorAttendanceController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('attendance.store');

        // ── Block level workshops (separate table) ────────────────────────────
        Route::get('workshops', [BlockWorkshopController::class, 'index'])->name('workshops.index');
        Route::get('workshops/view', [BlockWorkshopController::class, 'view'])->name('workshops.view');
        Route::get('workshops/export', [BlockWorkshopController::class, 'exportList'])->name('workshops.export');
        Route::get('workshops/gram-panchayats', [BlockWorkshopController::class, 'gramPanchayats'])->name('workshops.gram-panchayats');
        Route::get('workshops/attendance-sheet-template', [BlockWorkshopController::class, 'downloadAttendanceSheetTemplate'])->name('workshops.sheet-template');
        Route::post('workshops/draft', [BlockWorkshopController::class, 'createDraft'])
            ->middleware('throttle:30,1')
            ->name('workshops.draft.create');
        Route::patch('workshops/{blockWorkshop}/draft-meta', [BlockWorkshopController::class, 'updateDraftMeta'])
            ->middleware('throttle:60,1')
            ->name('workshops.draft.meta');
        Route::patch('workshops/{blockWorkshop}/participants', [BlockWorkshopController::class, 'saveParticipants'])
            ->middleware('throttle:60,1')
            ->name('workshops.participants.save');
        Route::get('workshops/{blockWorkshop}/submit', [BlockWorkshopController::class, 'redirectSubmitPage'])
            ->name('workshops.draft.submit.redirect');
        Route::post('workshops/{blockWorkshop}/submit', [BlockWorkshopController::class, 'submitDraft'])
            ->middleware('throttle:30,1')
            ->name('workshops.draft.submit');
        Route::post('workshops/{blockWorkshop}/photos', [BlockWorkshopController::class, 'uploadPhotos'])
            ->middleware('throttle:30,1')
            ->name('workshops.photos.upload');
        Route::delete('workshops/{blockWorkshop}/photos/{photoIndex}', [BlockWorkshopController::class, 'deletePhoto'])
            ->middleware('throttle:30,1')
            ->whereNumber('photoIndex')
            ->name('workshops.photos.delete');
        Route::get('workshops/{blockWorkshop}/attachment', [BlockWorkshopController::class, 'downloadAttachment'])
            ->name('workshops.attachment');
        Route::get('workshops/{blockWorkshop}/show', [BlockWorkshopController::class, 'show'])
            ->name('workshops.show');
        Route::get('workshops/{blockWorkshop}/edit', [BlockWorkshopController::class, 'edit'])
            ->name('workshops.edit');
        Route::put('workshops/{blockWorkshop}', [BlockWorkshopController::class, 'update'])
            ->name('workshops.update');
        Route::post('workshops/{blockWorkshop}/save', [BlockWorkshopController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('workshops.save');
        Route::get('workshops/{blockWorkshop}/participants-export', [BlockWorkshopController::class, 'exportParticipants'])
            ->name('workshops.participants.export');
        Route::get('workshops/{blockWorkshop}/attendance-sheet', [BlockWorkshopController::class, 'downloadAttendanceSheet'])
            ->name('workshops.sheet.download');
        Route::post('workshops/{blockWorkshop}/attendance-sheet', [BlockWorkshopController::class, 'uploadAttendanceSheet'])
            ->middleware('throttle:30,1')
            ->name('workshops.sheet.upload');
        Route::get('workshops/{blockWorkshop}/attendance-sheet-template', [BlockWorkshopController::class, 'downloadAttendanceSheetTemplateForReport'])
            ->name('workshops.sheet-template.report');
        Route::delete('workshops/{blockWorkshop}', [BlockWorkshopController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('workshops.destroy');

        Route::middleware('staff_phase3_attendance_nav:training_package')->group(function (): void {
            Route::get('training-packages', [TrainingPackageAttendanceController::class, 'create'])->name('training-packages.create');
            Route::post('training-packages', [TrainingPackageAttendanceController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('training-packages.store');
            Route::get('training-packages/dashboard', [TrainingPackageAttendanceController::class, 'dashboard'])->name('training-packages.dashboard');
            Route::get('training-packages/export', [TrainingPackageAttendanceController::class, 'export'])->name('training-packages.export');
            Route::get('training-packages/{trainingPackage}', [TrainingPackageAttendanceController::class, 'show'])->name('training-packages.show');
            Route::get('training-packages/{trainingPackage}/export', [TrainingPackageAttendanceController::class, 'exportSingle'])->name('training-packages.export-single');
            Route::get('training-packages/{trainingPackage}/edit', [TrainingPackageAttendanceController::class, 'edit'])->name('training-packages.edit');
            Route::put('training-packages/{trainingPackage}', [TrainingPackageAttendanceController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('training-packages.update');
            Route::get('training-packages/{trainingPackage}/attachment', [TrainingPackageAttendanceController::class, 'downloadAttachment'])
                ->name('training-packages.attachment');
        });

        Route::middleware('staff_phase3_attendance_nav:technical_training')->group(function (): void {
            Route::get('technical-trainings', [TechnicalTrainingAttendanceController::class, 'create'])->name('technical-trainings.create');
            Route::post('technical-trainings', [TechnicalTrainingAttendanceController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('technical-trainings.store');
            Route::get('technical-trainings/dashboard', [TechnicalTrainingAttendanceController::class, 'dashboard'])->name('technical-trainings.dashboard');
            Route::get('technical-trainings/export', [TechnicalTrainingAttendanceController::class, 'export'])->name('technical-trainings.export');
            Route::get('technical-trainings/{technicalTraining}', [TechnicalTrainingAttendanceController::class, 'show'])->name('technical-trainings.show');
            Route::get('technical-trainings/{technicalTraining}/export', [TechnicalTrainingAttendanceController::class, 'exportSingle'])->name('technical-trainings.export-single');
            Route::get('technical-trainings/{technicalTraining}/edit', [TechnicalTrainingAttendanceController::class, 'edit'])->name('technical-trainings.edit');
            Route::put('technical-trainings/{technicalTraining}', [TechnicalTrainingAttendanceController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('technical-trainings.update');
            Route::get('technical-trainings/{technicalTraining}/attachment', [TechnicalTrainingAttendanceController::class, 'downloadAttachment'])
                ->name('technical-trainings.attachment');

            Route::get('lakhpati-technical-trainings', [LakhpatiTechnicalTrainingController::class, 'create'])->name('lakhpati-technical-trainings.create');
            Route::post('lakhpati-technical-trainings', [LakhpatiTechnicalTrainingController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('lakhpati-technical-trainings.store');
            Route::get('lakhpati-technical-trainings/dashboard', [LakhpatiTechnicalTrainingController::class, 'dashboard'])->name('lakhpati-technical-trainings.dashboard');
            Route::get('lakhpati-technical-trainings/export', [LakhpatiTechnicalTrainingController::class, 'export'])->name('lakhpati-technical-trainings.export');
            Route::get('lakhpati-technical-trainings/gram-panchayats', [LakhpatiTechnicalTrainingController::class, 'workshopGramPanchayats'])->name('lakhpati-technical-trainings.gram-panchayats');
            Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}', [LakhpatiTechnicalTrainingController::class, 'show'])->name('lakhpati-technical-trainings.show');
            Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/export', [LakhpatiTechnicalTrainingController::class, 'exportSingle'])->name('lakhpati-technical-trainings.export-single');
            Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/edit', [LakhpatiTechnicalTrainingController::class, 'edit'])->name('lakhpati-technical-trainings.edit');
            Route::put('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}', [LakhpatiTechnicalTrainingController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('lakhpati-technical-trainings.update');
            Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/attachment', [LakhpatiTechnicalTrainingController::class, 'downloadAttachment'])
                ->name('lakhpati-technical-trainings.attachment');
        });

        Route::middleware('staff_phase3_attendance_nav:eap_edp_session')->group(function (): void {
            Route::get('eap-edp-sessions', [EapEdpSessionAttendanceController::class, 'create'])->name('eap-edp-sessions.create');
            Route::post('eap-edp-sessions', [EapEdpSessionAttendanceController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('eap-edp-sessions.store');
            Route::get('eap-edp-sessions/dashboard', [EapEdpSessionAttendanceController::class, 'dashboard'])->name('eap-edp-sessions.dashboard');
            Route::get('eap-edp-sessions/export', [EapEdpSessionAttendanceController::class, 'export'])->name('eap-edp-sessions.export');
            Route::get('eap-edp-sessions/gram-panchayats', [EapEdpSessionAttendanceController::class, 'workshopGramPanchayats'])->name('eap-edp-sessions.gram-panchayats');
            Route::get('eap-edp-sessions/{eapEdpSession}', [EapEdpSessionAttendanceController::class, 'show'])->name('eap-edp-sessions.show');
            Route::get('eap-edp-sessions/{eapEdpSession}/export', [EapEdpSessionAttendanceController::class, 'exportSingle'])->name('eap-edp-sessions.export-single');
            Route::get('eap-edp-sessions/{eapEdpSession}/edit', [EapEdpSessionAttendanceController::class, 'edit'])->name('eap-edp-sessions.edit');
            Route::put('eap-edp-sessions/{eapEdpSession}', [EapEdpSessionAttendanceController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('eap-edp-sessions.update');
            Route::delete('eap-edp-sessions/{eapEdpSession}', [EapEdpSessionAttendanceController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('eap-edp-sessions.destroy');
            Route::get('eap-edp-sessions/{eapEdpSession}/attachment', [EapEdpSessionAttendanceController::class, 'downloadAttachment'])
                ->name('eap-edp-sessions.attachment');
            Route::get('eap-edp-sessions/{eapEdpSession}/photo', [EapEdpSessionAttendanceController::class, 'downloadPhoto'])
                ->name('eap-edp-sessions.photo');
        });

        Route::middleware('staff_phase3_attendance_nav:district_workshop')->group(function (): void {
            Route::get('district-workshop-sessions', [DistrictWorkshopSessionAttendanceController::class, 'create'])->name('district-workshop-sessions.create');
            Route::post('district-workshop-sessions', [DistrictWorkshopSessionAttendanceController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('district-workshop-sessions.store');
            Route::get('district-workshop-sessions/dashboard', [DistrictWorkshopSessionAttendanceController::class, 'dashboard'])->name('district-workshop-sessions.dashboard');
            Route::get('district-workshop-sessions/export', [DistrictWorkshopSessionAttendanceController::class, 'export'])->name('district-workshop-sessions.export');
            Route::get('district-workshop-sessions/gram-panchayats', [DistrictWorkshopSessionAttendanceController::class, 'workshopGramPanchayats'])->name('district-workshop-sessions.gram-panchayats');
            Route::get('district-workshop-sessions/{districtWorkshopSession}', [DistrictWorkshopSessionAttendanceController::class, 'show'])->name('district-workshop-sessions.show');
            Route::get('district-workshop-sessions/{districtWorkshopSession}/export', [DistrictWorkshopSessionAttendanceController::class, 'exportSingle'])->name('district-workshop-sessions.export-single');
            Route::get('district-workshop-sessions/{districtWorkshopSession}/edit', [DistrictWorkshopSessionAttendanceController::class, 'edit'])->name('district-workshop-sessions.edit');
            Route::put('district-workshop-sessions/{districtWorkshopSession}', [DistrictWorkshopSessionAttendanceController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('district-workshop-sessions.update');
            Route::delete('district-workshop-sessions/{districtWorkshopSession}', [DistrictWorkshopSessionAttendanceController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('district-workshop-sessions.destroy');
            Route::get('district-workshop-sessions/{districtWorkshopSession}/attachment', [DistrictWorkshopSessionAttendanceController::class, 'downloadAttachment'])
                ->name('district-workshop-sessions.attachment');
        });

        /** Read-only batches view for district staff (scoped to their own district) */
        Route::get('batches', [BatchReadOnlyController::class, 'index'])->name('batches.index');
        Route::get('batches/legacy/{legacy_batch}', [BatchReadOnlyController::class, 'showLegacy'])
            ->whereNumber('legacy_batch')
            ->name('batches.legacy.show');
        Route::get('batches/{batch}', [BatchReadOnlyController::class, 'show'])->name('batches.show');
        Route::get('batches/{batch}/onboarding-letter', [BatchReadOnlyController::class, 'downloadOnboardingLetter'])->name('batches.onboarding-letter');

        /** Service delivery (maker–checker) — gated by AppSettingsService in controller + topbar */
        Route::get('services', [StaffServiceCaseController::class, 'index'])->name('services.index');
        Route::get('services/create', [StaffServiceCaseController::class, 'create'])->name('services.create');
        Route::post('services', [StaffServiceCaseController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('services.store');
        Route::get('services/{service_case}/edit', [StaffServiceCaseController::class, 'edit'])->name('services.edit');
        Route::patch('services/{service_case}', [StaffServiceCaseController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('services.update');
        Route::get('services/{service_case}', [StaffServiceCaseController::class, 'show'])->name('services.show');
        Route::get('services/{service_case}/attachments/{attachment}/download', [StaffServiceCaseController::class, 'downloadAttachment'])
            ->name('services.attachments.download');
        Route::delete('services/{service_case}', [StaffServiceCaseController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('services.destroy');

        Route::get('market-linkages/create', [MarketLinkageController::class, 'create'])->name('market-linkages.create');
        Route::post('market-linkages', [MarketLinkageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('market-linkages.store');
        Route::get('market-linkages/{market_linkage}/edit', [MarketLinkageController::class, 'edit'])->name('market-linkages.edit');
        Route::put('market-linkages/{market_linkage}', [MarketLinkageController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('market-linkages.update');
        Route::delete('market-linkages/{market_linkage}', [MarketLinkageController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('market-linkages.destroy');
        Route::get('market-linkages/dashboard', [MarketLinkageController::class, 'dashboard'])->name('market-linkages.dashboard');
        Route::get('market-linkages/export', [MarketLinkageController::class, 'export'])->name('market-linkages.export');
        Route::get('market-linkages/{market_linkage}', [MarketLinkageController::class, 'show'])->name('market-linkages.show');
        Route::get('market-linkages/{market_linkage}/partners/{partner}/document', [MarketLinkageController::class, 'downloadDocument'])
            ->name('market-linkages.document');
    });

    /** State Staff (SPOC) — maker-checker approval queue */
    Route::middleware('state_staff')->prefix('spoc')->name('spoc.')->group(function () {
        Route::get('state-tasks', [SpocStateTaskController::class, 'index'])->name('state-tasks.index');
        Route::get('state-tasks/{state_task}', [SpocStateTaskController::class, 'show'])->name('state-tasks.show');
        Route::post('state-tasks/{state_task}/progress', [SpocStateTaskController::class, 'updateProgress'])
            ->middleware('throttle:60,1')
            ->name('state-tasks.progress');
        Route::post('state-tasks/{state_task}/submit', [SpocStateTaskController::class, 'submit'])
            ->middleware('throttle:30,1')
            ->name('state-tasks.submit');
        Route::get('state-tasks/{state_task}/attachments/{attachment}/download', [SpocStateTaskController::class, 'downloadAttachment'])
            ->name('state-tasks.attachments.download');

        Route::get('service-cases', [SpocServiceCaseController::class, 'index'])->name('service-cases.index');
        Route::post('service-cases/bulk-approve', [SpocServiceCaseController::class, 'bulkApprove'])
            ->middleware('throttle:30,1')
            ->name('service-cases.bulk-approve');
        Route::get('onboarded', [OnboardedApplicantController::class, 'index'])->name('onboarded.index');
        Route::get('onboarded/export', [OnboardedApplicantController::class, 'export'])->name('onboarded.export');
        Route::get('field-coordinator-reports', [FieldCoordinatorReportController::class, 'index'])->name('field-coordinator-reports.index');
        Route::get('field-coordinator-reports/{attendanceReport}/attachment', [FieldCoordinatorReportController::class, 'downloadAttachment'])->name('field-coordinator-reports.attachment');
        Route::get('field-coordinator-reports/{attendanceReport}/attendance-sheet', [FieldCoordinatorReportController::class, 'downloadAttendanceSheet'])->name('field-coordinator-reports.sheet');
        Route::get('deliverables', [DeliverablesReportController::class, 'index'])->name('deliverables.index');
        Route::get('deliverables/breakdown/export', [DeliverablesReportController::class, 'breakdownExport'])->name('deliverables.breakdown.export');
        Route::get('deliverables/breakdown/export/csv', [DeliverablesReportController::class, 'breakdownExportCsv'])->name('deliverables.breakdown.export.csv');
        Route::get('deliverables/breakdown/export/pdf', [DeliverablesReportController::class, 'breakdownExportPdf'])->name('deliverables.breakdown.export.pdf');
        Route::get('deliverables/breakdown', [DeliverablesReportController::class, 'breakdown'])->name('deliverables.breakdown');
        Route::get('deliverables/export', [DeliverablesReportController::class, 'export'])->name('deliverables.export');
        Route::get('service-cases/{service_case}', [SpocServiceCaseController::class, 'show'])->name('service-cases.show');
        Route::post('service-cases/{service_case}/review-telemetry', [SpocServiceCaseController::class, 'recordReviewTelemetry'])
            ->middleware('throttle:120,1')
            ->name('service-cases.review-telemetry');
        Route::post('service-cases/{service_case}/approve', [SpocServiceCaseController::class, 'approve'])
            ->middleware('throttle:60,1')
            ->name('service-cases.approve');
        Route::post('service-cases/{service_case}/send-back', [SpocServiceCaseController::class, 'sendBack'])
            ->middleware('throttle:60,1')
            ->name('service-cases.send-back');
        Route::post('service-cases/{service_case}/reject', [SpocServiceCaseController::class, 'reject'])
            ->middleware('throttle:60,1')
            ->name('service-cases.reject');
        Route::get('service-cases/{service_case}/attachments/{attachment}/download', [SpocServiceCaseController::class, 'downloadAttachment'])
            ->name('service-cases.attachments.download');
        Route::get('market-linkages/{market_linkage}', [SpocMarketLinkageController::class, 'show'])->name('market-linkages.show');
        Route::post('market-linkages/{market_linkage}/approve', [SpocMarketLinkageController::class, 'approve'])
            ->middleware('throttle:60,1')
            ->name('market-linkages.approve');
        Route::post('market-linkages/{market_linkage}/send-back', [SpocMarketLinkageController::class, 'sendBack'])
            ->middleware('throttle:60,1')
            ->name('market-linkages.send-back');
        Route::post('market-linkages/{market_linkage}/reject', [SpocMarketLinkageController::class, 'reject'])
            ->middleware('throttle:60,1')
            ->name('market-linkages.reject');
        Route::get('market-linkages/{market_linkage}/partners/{partner}/document', [SpocMarketLinkageController::class, 'downloadDocument'])
            ->name('market-linkages.document');
        Route::get('training-packages/dashboard', [TrainingPackageAttendanceController::class, 'dashboard'])->name('training-packages.dashboard');
        Route::get('training-packages/export', [TrainingPackageAttendanceController::class, 'export'])->name('training-packages.export');
        Route::get('training-packages/{trainingPackage}', [TrainingPackageAttendanceController::class, 'show'])->name('training-packages.show');
        Route::get('training-packages/{trainingPackage}/export', [TrainingPackageAttendanceController::class, 'exportSingle'])->name('training-packages.export-single');
        Route::get('training-packages/{trainingPackage}/attachment', [TrainingPackageAttendanceController::class, 'downloadAttachment'])
            ->name('training-packages.attachment');
        Route::get('technical-trainings/dashboard', [TechnicalTrainingAttendanceController::class, 'dashboard'])->name('technical-trainings.dashboard');
        Route::get('technical-trainings/export', [TechnicalTrainingAttendanceController::class, 'export'])->name('technical-trainings.export');
        Route::get('technical-trainings/{technicalTraining}', [TechnicalTrainingAttendanceController::class, 'show'])->name('technical-trainings.show');
        Route::get('technical-trainings/{technicalTraining}/export', [TechnicalTrainingAttendanceController::class, 'exportSingle'])->name('technical-trainings.export-single');
        Route::get('technical-trainings/{technicalTraining}/attachment', [TechnicalTrainingAttendanceController::class, 'downloadAttachment'])
            ->name('technical-trainings.attachment');
        Route::get('lakhpati-technical-trainings/dashboard', [LakhpatiTechnicalTrainingController::class, 'dashboard'])->name('lakhpati-technical-trainings.dashboard');
        Route::get('lakhpati-technical-trainings/export', [LakhpatiTechnicalTrainingController::class, 'export'])->name('lakhpati-technical-trainings.export');
        Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}', [LakhpatiTechnicalTrainingController::class, 'show'])->name('lakhpati-technical-trainings.show');
        Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/export', [LakhpatiTechnicalTrainingController::class, 'exportSingle'])->name('lakhpati-technical-trainings.export-single');
        Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/attachment', [LakhpatiTechnicalTrainingController::class, 'downloadAttachment'])
            ->name('lakhpati-technical-trainings.attachment');
        Route::get('eap-edp-sessions/dashboard', [EapEdpSessionAttendanceController::class, 'dashboard'])->name('eap-edp-sessions.dashboard');
        Route::get('eap-edp-sessions/export', [EapEdpSessionAttendanceController::class, 'export'])->name('eap-edp-sessions.export');
        Route::get('eap-edp-sessions/{eapEdpSession}', [EapEdpSessionAttendanceController::class, 'show'])->name('eap-edp-sessions.show');
        Route::get('eap-edp-sessions/{eapEdpSession}/export', [EapEdpSessionAttendanceController::class, 'exportSingle'])->name('eap-edp-sessions.export-single');
        Route::get('eap-edp-sessions/{eapEdpSession}/attachment', [EapEdpSessionAttendanceController::class, 'downloadAttachment'])
            ->name('eap-edp-sessions.attachment');
        Route::get('eap-edp-sessions/{eapEdpSession}/photo', [EapEdpSessionAttendanceController::class, 'downloadPhoto'])
            ->name('eap-edp-sessions.photo');
        Route::get('district-workshop-sessions/dashboard', [DistrictWorkshopSessionAttendanceController::class, 'dashboard'])->name('district-workshop-sessions.dashboard');
        Route::get('district-workshop-sessions/export', [DistrictWorkshopSessionAttendanceController::class, 'export'])->name('district-workshop-sessions.export');
        Route::get('district-workshop-sessions/{districtWorkshopSession}', [DistrictWorkshopSessionAttendanceController::class, 'show'])->name('district-workshop-sessions.show');
        Route::get('district-workshop-sessions/{districtWorkshopSession}/export', [DistrictWorkshopSessionAttendanceController::class, 'exportSingle'])->name('district-workshop-sessions.export-single');
        Route::get('district-workshop-sessions/{districtWorkshopSession}/attachment', [DistrictWorkshopSessionAttendanceController::class, 'downloadAttachment'])
            ->name('district-workshop-sessions.attachment');

        Route::get('social-media-posts/preview', [SocialMediaPostController::class, 'preview'])->name('social-media-posts.preview');
        Route::get('social-media-posts/thumbnail', [SocialMediaPostController::class, 'thumbnail'])->name('social-media-posts.thumbnail');
        Route::get('social-media-posts/create', [SocialMediaPostController::class, 'create'])->name('social-media-posts.create');
        Route::post('social-media-posts', [SocialMediaPostController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('social-media-posts.store');
        Route::get('social-media-posts', SocialMediaPostLandingController::class)->name('social-media-posts.index');
        Route::get('social-media-posts/dashboard', [SocialMediaPostController::class, 'dashboard'])->name('social-media-posts.dashboard');
        Route::get('social-media-posts/export', [SocialMediaPostController::class, 'export'])->name('social-media-posts.export');
        Route::get('social-media-posts/{socialMediaPost}', [SocialMediaPostController::class, 'show'])->name('social-media-posts.show');
        Route::delete('social-media-posts/{socialMediaPost}', [SocialMediaPostController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('social-media-posts.destroy');

        Route::get('capacity-building-stakeholders/create', [StakeholderCapacityBuildingSessionController::class, 'create'])->name('capacity-building-stakeholders.create');
        Route::post('capacity-building-stakeholders', [StakeholderCapacityBuildingSessionController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('capacity-building-stakeholders.store');
        Route::get('capacity-building-stakeholders/{cbsSession}/edit', [StakeholderCapacityBuildingSessionController::class, 'edit'])->name('capacity-building-stakeholders.edit');
        Route::put('capacity-building-stakeholders/{cbsSession}', [StakeholderCapacityBuildingSessionController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('capacity-building-stakeholders.update');
        Route::delete('capacity-building-stakeholders/{cbsSession}', [StakeholderCapacityBuildingSessionController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('capacity-building-stakeholders.destroy');
        Route::get('capacity-building-stakeholders', CapacityBuildingStakeholdersLandingController::class)->name('capacity-building-stakeholders.index');
        Route::get('capacity-building-stakeholders/dashboard', [StakeholderCapacityBuildingSessionController::class, 'dashboard'])->name('capacity-building-stakeholders.dashboard');
        Route::get('capacity-building-stakeholders/export', [StakeholderCapacityBuildingSessionController::class, 'export'])->name('capacity-building-stakeholders.export');
        Route::get('capacity-building-stakeholders/{cbsSession}', [StakeholderCapacityBuildingSessionController::class, 'show'])->name('capacity-building-stakeholders.show');
        Route::get('capacity-building-stakeholders/{cbsSession}/attachment', [StakeholderCapacityBuildingSessionController::class, 'downloadAttachment'])
            ->name('capacity-building-stakeholders.attachment');

        Route::get('pitch-deck-preparations/incubatees/search', [PitchDeckPreparationController::class, 'searchIncubatees'])
            ->name('pitch-deck-preparations.incubatees.search');
        Route::get('pitch-deck-preparations/create', [PitchDeckPreparationController::class, 'create'])->name('pitch-deck-preparations.create');
        Route::post('pitch-deck-preparations', [PitchDeckPreparationController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('pitch-deck-preparations.store');
        Route::get('pitch-deck-preparations', PitchDeckPreparationLandingController::class)->name('pitch-deck-preparations.index');
        Route::get('pitch-deck-preparations/dashboard', [PitchDeckPreparationController::class, 'dashboard'])->name('pitch-deck-preparations.dashboard');
        Route::get('pitch-deck-preparations/export', [PitchDeckPreparationController::class, 'export'])->name('pitch-deck-preparations.export');
        Route::get('pitch-deck-preparations/{pitchDeckPreparation}/edit', [PitchDeckPreparationController::class, 'edit'])->name('pitch-deck-preparations.edit');
        Route::put('pitch-deck-preparations/{pitchDeckPreparation}', [PitchDeckPreparationController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('pitch-deck-preparations.update');
        Route::delete('pitch-deck-preparations/{pitchDeckPreparation}', [PitchDeckPreparationController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('pitch-deck-preparations.destroy');
        Route::get('pitch-deck-preparations/{pitchDeckPreparation}/deck', [PitchDeckPreparationController::class, 'downloadDeck'])
            ->name('pitch-deck-preparations.deck');
        Route::get('pitch-deck-preparations/{pitchDeckPreparation}', [PitchDeckPreparationController::class, 'show'])->name('pitch-deck-preparations.show');
    });

    Route::middleware('state_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('ops/cache-clear', function () {
            $results = [];
            foreach (['optimize:clear', 'view:clear'] as $cmd) {
                try {
                    $code = Artisan::call($cmd);
                    $results[] = [
                        'command' => $cmd,
                        'exit_code' => $code,
                        'output' => trim((string) Artisan::output()),
                    ];
                } catch (Throwable $e) {
                    $results[] = [
                        'command' => $cmd,
                        'exit_code' => 1,
                        'output' => $e->getMessage(),
                    ];
                }
            }

            return view('admin.ops.cache-clear', [
                'ranAt' => now(),
                'results' => $results,
            ]);
        })->name('ops.cache-clear');

        Route::get('data-centre', [DataCentreController::class, 'index'])->name('data-centre.index');
        Route::post('data-centre/refresh', [DataCentreController::class, 'refresh'])->name('data-centre.refresh');
        Route::get('data-centre/export-all', [DataCentreController::class, 'exportAll'])->name('data-centre.export-all');
        Route::get('data-centre/export/{section}', [DataCentreController::class, 'export'])
            ->where('section', '[a-z\-]+')
            ->name('data-centre.export');

        Route::get('cfa-applications', [CfaSubmissionController::class, 'index'])->name('cfa.index');
        Route::get('cfa-applications/export', [CfaSubmissionController::class, 'export'])->name('cfa.export');
        Route::get('cfa-applications/{cfa_submission}', [CfaSubmissionController::class, 'show'])->name('cfa.show');
        Route::get('cfa-applications-phase1-legacy', [LegacyPhase1CfaApplicationController::class, 'index'])->name('phase1-cfa.index');
        Route::get('cfa-applications-phase2-legacy', [LegacyPhase2CfaApplicationController::class, 'index'])->name('phase2-cfa.index');
        Route::get('onboarded', [OnboardedApplicantController::class, 'index'])->name('onboarded.index');
        Route::get('onboarded/export', [OnboardedApplicantController::class, 'export'])->name('onboarded.export');
        Route::get('phase3-services', [Phase3ServiceCasesController::class, 'index'])->name('phase3-services.index');
        Route::get('phase3-services/export', [Phase3ServiceCasesController::class, 'export'])->name('phase3-services.export');
        Route::get('phase3-services/{service_case}', [Phase3ServiceCasesController::class, 'show'])->name('phase3-services.show');
        Route::get('phase3-services/{service_case}/attachments/{attachment}/view', [Phase3ServiceCasesController::class, 'viewAttachment'])
            ->name('phase3-services.attachments.view');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('deliverables', [DeliverablesReportController::class, 'index'])->name('deliverables.index');
        Route::get('deliverables/activity-guide', [DeliverablesReportController::class, 'activityGuide'])->name('deliverables.activity-guide');
        Route::get('deliverables/breakdown/export', [DeliverablesReportController::class, 'breakdownExport'])->name('deliverables.breakdown.export');
        Route::get('deliverables/breakdown/export/csv', [DeliverablesReportController::class, 'breakdownExportCsv'])->name('deliverables.breakdown.export.csv');
        Route::get('deliverables/breakdown/export/pdf', [DeliverablesReportController::class, 'breakdownExportPdf'])->name('deliverables.breakdown.export.pdf');
        Route::get('deliverables/breakdown', [DeliverablesReportController::class, 'breakdown'])->name('deliverables.breakdown');
        Route::get('deliverables/export', [DeliverablesReportController::class, 'export'])->name('deliverables.export');
        Route::get('targets/state-monthly', [\App\Http\Controllers\Admin\StateMonthlyTargetsController::class, 'index'])->name('targets.state-monthly');
        Route::post('targets/state-monthly', [\App\Http\Controllers\Admin\StateMonthlyTargetsController::class, 'update'])->name('targets.state-monthly.update');
        Route::get('targets/state', [TargetController::class, 'stateForm'])->name('targets.state');
        Route::post('targets/state', [TargetController::class, 'stateUpdate'])->name('targets.state.update');
        Route::get('targets/district', [TargetController::class, 'districtForm'])->name('targets.district');
        Route::post('targets/district', [TargetController::class, 'districtUpdate'])->name('targets.district.update');
        Route::get('targets/allocate-by-service', [ServiceTargetAllocationController::class, 'index'])->name('targets.allocate-by-service');
        Route::post('targets/allocate-by-service', [ServiceTargetAllocationController::class, 'apply'])->name('targets.allocate-by-service.apply');
        Route::get('targets/district-hub-monthly', [\App\Http\Controllers\Admin\DistrictHubMonthlyTargetsController::class, 'index'])->name('targets.district-hub-monthly');
        Route::post('targets/district-hub-monthly/preset', [\App\Http\Controllers\Admin\DistrictHubMonthlyTargetsController::class, 'applyDistrictPreset'])->name('targets.district-hub-monthly.preset');
        Route::post('targets/district-hub-monthly/district', [\App\Http\Controllers\Admin\DistrictHubMonthlyTargetsController::class, 'updateDistrict'])->name('targets.district-hub-monthly.district');
        Route::post('targets/district-hub-monthly/hub', [\App\Http\Controllers\Admin\DistrictHubMonthlyTargetsController::class, 'updateHub'])->name('targets.district-hub-monthly.hub');
        Route::get('service-catalog', [ServiceCategoryController::class, 'index'])->name('service-catalog.index');
        Route::get('service-catalog/categories/create', [ServiceCategoryController::class, 'create'])->name('service-catalog.categories.create');
        Route::post('service-catalog/categories', [ServiceCategoryController::class, 'store'])->name('service-catalog.categories.store');
        // Convenience: /categories/{id} -> edit (avoid errors when /edit is omitted).
        Route::get('service-catalog/categories/{service_category}', function (ServiceCategory $serviceCategory) {
            return redirect()->route('admin.service-catalog.categories.edit', $serviceCategory);
        })->name('service-catalog.categories.redirect-edit');
        Route::get('service-catalog/categories/{service_category}/edit', [ServiceCategoryController::class, 'edit'])->name('service-catalog.categories.edit');
        Route::put('service-catalog/categories/{service_category}', [ServiceCategoryController::class, 'update'])->name('service-catalog.categories.update');
        Route::delete('service-catalog/categories/{service_category}', [ServiceCategoryController::class, 'destroy'])->name('service-catalog.categories.destroy');
        Route::get('service-catalog/services/create', [CatalogServiceController::class, 'create'])->name('service-catalog.services.create');
        Route::post('service-catalog/services', [CatalogServiceController::class, 'store'])->name('service-catalog.services.store');
        Route::post('service-catalog/services/bulk-active', [CatalogServiceController::class, 'bulkSetActive'])->name('service-catalog.services.bulk-active');
        Route::patch('service-catalog/services/{service}/active', [CatalogServiceController::class, 'setActive'])->name('service-catalog.services.set-active');
        // Convenience: /services/{id} → edit (avoids 404/500 when /edit is omitted).
        Route::get('service-catalog/services/{service}', function (Service $service) {
            return redirect()->route('admin.service-catalog.services.edit', $service);
        })->name('service-catalog.services.redirect-edit');
        Route::get('service-catalog/services/{service}/edit', [CatalogServiceController::class, 'edit'])->name('service-catalog.services.edit');
        Route::post('service-catalog/services/{service}/recover-schema', [CatalogServiceController::class, 'recoverSchema'])->name('service-catalog.services.recover-schema');
        Route::put('service-catalog/services/{service}/quick-update', [CatalogServiceController::class, 'quickUpdate'])->name('service-catalog.services.quick-update');
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

        /** State staff (SPOC) user management — checkers for maker-checker service flow */
        Route::get('state-staff', [StateStaffController::class, 'index'])->name('state-staff.index');
        Route::get('state-staff/create', [StateStaffController::class, 'create'])->name('state-staff.create');
        Route::post('state-staff', [StateStaffController::class, 'store'])->name('state-staff.store');
        Route::get('state-staff/{user}/edit', [StateStaffController::class, 'edit'])->name('state-staff.edit');
        Route::put('state-staff/{user}', [StateStaffController::class, 'update'])->name('state-staff.update');
        Route::delete('state-staff/{user}', [StateStaffController::class, 'destroy'])->name('state-staff.destroy');
        Route::post('state-staff/{user}/toggle-active', [StateStaffController::class, 'toggleActive'])->name('state-staff.toggle-active');

        /** District -> SPOC mapping used by the maker-checker service flow. */
        Route::get('service-spocs', [DistrictSpocController::class, 'index'])->name('service-spocs.index');
        Route::put('service-spocs', [DistrictSpocController::class, 'update'])->name('service-spocs.update');
        Route::put('service-spocs/by-spoc', [DistrictSpocController::class, 'updateForSpoc'])->name('service-spocs.update-for-spoc');
        Route::get('pending-actions', [PendingActionsController::class, 'index'])->name('pending-actions.index');
        Route::get('spoc-approval-audit', [SpocApprovalAuditController::class, 'index'])->name('spoc-approval-audit.index');

        Route::get('state-tasks', [AdminStateTaskController::class, 'index'])->name('state-tasks.index');
        Route::get('state-tasks/create', [AdminStateTaskController::class, 'create'])->name('state-tasks.create');
        Route::post('state-tasks', [AdminStateTaskController::class, 'store'])->name('state-tasks.store');
        Route::get('state-tasks/{state_task}', [AdminStateTaskController::class, 'show'])->name('state-tasks.show');
        Route::get('state-tasks/{state_task}/edit', [AdminStateTaskController::class, 'edit'])->name('state-tasks.edit');
        Route::put('state-tasks/{state_task}', [AdminStateTaskController::class, 'update'])->name('state-tasks.update');
        Route::post('state-tasks/{state_task}/publish', [AdminStateTaskController::class, 'publish'])->name('state-tasks.publish');
        Route::post('state-tasks/{state_task}/close', [AdminStateTaskController::class, 'close'])->name('state-tasks.close');
        Route::post('state-tasks/{state_task}/cancel', [AdminStateTaskController::class, 'cancel'])->name('state-tasks.cancel');
        Route::post('state-tasks/{state_task}/assignments/{state_task_assignment}/complete', [AdminStateTaskController::class, 'completeAssignment'])->name('state-tasks.assignments.complete');
        Route::post('state-tasks/{state_task}/assignments/{state_task_assignment}/send-back', [AdminStateTaskController::class, 'sendBackAssignment'])->name('state-tasks.assignments.send-back');
        Route::get('state-tasks/{state_task}/attachments/{attachment}/download', [AdminStateTaskController::class, 'downloadAttachment'])->name('state-tasks.attachments.download');
        Route::delete('state-tasks/{state_task}/attachments/{attachment}', [AdminStateTaskController::class, 'destroyAttachment'])->name('state-tasks.attachments.destroy');

        /** Service module runtime settings (master switch + eligibility scope). */
        Route::get('service-module-settings', [ServiceModuleSettingsController::class, 'edit'])->name('service-module-settings.edit');
        Route::put('service-module-settings', [ServiceModuleSettingsController::class, 'update'])->name('service-module-settings.update');
        Route::get('staff-phase3-attendance-nav', [StaffPhase3AttendanceNavController::class, 'edit'])->name('staff-phase3-attendance-nav.edit');
        Route::put('staff-phase3-attendance-nav', [StaffPhase3AttendanceNavController::class, 'update'])->name('staff-phase3-attendance-nav.update');

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

        Route::get('team-performance', [TeamPerformanceController::class, 'index'])->name('team-performance.index');
        Route::get('documents', [DocumentRepositoryController::class, 'index'])->name('documents.index');
        Route::get('documents/create', [DocumentRepositoryController::class, 'create'])->name('documents.create');
        Route::post('documents', [DocumentRepositoryController::class, 'store'])->name('documents.store');
        Route::post('documents/categories', [DocumentRepositoryController::class, 'storeCategory'])->name('documents.categories.store');
        Route::post('documents/subcategories', [DocumentRepositoryController::class, 'storeSubcategory'])->name('documents.subcategories.store');
        Route::post('documents/sync-cdo', [DocumentRepositoryController::class, 'syncCdo'])->name('documents.sync-cdo');
        Route::get('documents/{document}/edit', [DocumentRepositoryController::class, 'edit'])->name('documents.edit');
        Route::put('documents/{document}', [DocumentRepositoryController::class, 'update'])->name('documents.update');
        Route::post('documents/{document}/versions', [DocumentRepositoryController::class, 'uploadVersion'])->name('documents.upload-version');
        Route::delete('documents/{document}', [DocumentRepositoryController::class, 'destroy'])->name('documents.destroy');
        Route::get('attendance', [FieldCoordinatorAttendanceAdminController::class, 'index'])->name('attendance.index');
        Route::get('staff-check-ins', [StaffCheckInAdminController::class, 'index'])->name('staff-check-ins.index');
        Route::get('staff-check-ins/export', [StaffCheckInAdminController::class, 'export'])
            ->middleware('throttle:15,1')
            ->name('staff-check-ins.export');
        Route::get('live-map', [StateLiveMapController::class, 'index'])->name('live-map.index');
        Route::get('live-map/data', [StateLiveMapController::class, 'data'])
            ->middleware('throttle:60,1')
            ->name('live-map.data');
        Route::get('live-map/geojson', [StateLiveMapController::class, 'geojson'])->name('live-map.geojson');
        Route::get('field-coordinator-reports', [FieldCoordinatorReportController::class, 'index'])->name('field-coordinator-reports.index');
        Route::get('field-coordinator-reports/{attendanceReport}/attachment', [FieldCoordinatorReportController::class, 'downloadAttachment'])->name('field-coordinator-reports.attachment');
        Route::get('field-coordinator-reports/{attendanceReport}/attendance-sheet', [FieldCoordinatorReportController::class, 'downloadAttendanceSheet'])->name('field-coordinator-reports.sheet');
        Route::get('attendance/{attendanceReport}/attachment', [FieldCoordinatorAttendanceAdminController::class, 'downloadAttachment'])
            ->name('attendance.attachment');
        Route::get('attendance/{attendanceReport}/attendance-sheet', [FieldCoordinatorAttendanceAdminController::class, 'downloadAttendanceSheet'])
            ->name('attendance.sheet');
        Route::get('gram-panchayats/import', [GramPanchayatImportController::class, 'show'])->name('gram-panchayats.import');
        Route::post('gram-panchayats/import', [GramPanchayatImportController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('gram-panchayats.import.store');
        Route::get('training-packages/dashboard', [TrainingPackageAttendanceController::class, 'dashboard'])->name('training-packages.dashboard');
        Route::get('training-packages/export', [TrainingPackageAttendanceController::class, 'export'])->name('training-packages.export');
        Route::get('training-packages/{trainingPackage}', [TrainingPackageAttendanceController::class, 'show'])->name('training-packages.show');
        Route::get('training-packages/{trainingPackage}/export', [TrainingPackageAttendanceController::class, 'exportSingle'])->name('training-packages.export-single');
        Route::get('training-packages/{trainingPackage}/attachment', [TrainingPackageAttendanceController::class, 'downloadAttachment'])
            ->name('training-packages.attachment');
        Route::get('technical-trainings/dashboard', [TechnicalTrainingAttendanceController::class, 'dashboard'])->name('technical-trainings.dashboard');
        Route::get('technical-trainings/export', [TechnicalTrainingAttendanceController::class, 'export'])->name('technical-trainings.export');
        Route::get('technical-trainings/{technicalTraining}', [TechnicalTrainingAttendanceController::class, 'show'])->name('technical-trainings.show');
        Route::get('technical-trainings/{technicalTraining}/export', [TechnicalTrainingAttendanceController::class, 'exportSingle'])->name('technical-trainings.export-single');
        Route::get('technical-trainings/{technicalTraining}/attachment', [TechnicalTrainingAttendanceController::class, 'downloadAttachment'])
            ->name('technical-trainings.attachment');
        Route::get('lakhpati-technical-trainings/dashboard', [LakhpatiTechnicalTrainingController::class, 'dashboard'])->name('lakhpati-technical-trainings.dashboard');
        Route::get('lakhpati-technical-trainings/export', [LakhpatiTechnicalTrainingController::class, 'export'])->name('lakhpati-technical-trainings.export');
        Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}', [LakhpatiTechnicalTrainingController::class, 'show'])->name('lakhpati-technical-trainings.show');
        Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/export', [LakhpatiTechnicalTrainingController::class, 'exportSingle'])->name('lakhpati-technical-trainings.export-single');
        Route::get('lakhpati-technical-trainings/{lakhpatiTechnicalTraining}/attachment', [LakhpatiTechnicalTrainingController::class, 'downloadAttachment'])
            ->name('lakhpati-technical-trainings.attachment');
        Route::get('eap-edp-sessions/dashboard', [EapEdpSessionAttendanceController::class, 'dashboard'])->name('eap-edp-sessions.dashboard');
        Route::get('eap-edp-sessions/export', [EapEdpSessionAttendanceController::class, 'export'])->name('eap-edp-sessions.export');
        Route::get('eap-edp-sessions/{eapEdpSession}', [EapEdpSessionAttendanceController::class, 'show'])->name('eap-edp-sessions.show');
        Route::get('eap-edp-sessions/{eapEdpSession}/export', [EapEdpSessionAttendanceController::class, 'exportSingle'])->name('eap-edp-sessions.export-single');
        Route::get('eap-edp-sessions/{eapEdpSession}/attachment', [EapEdpSessionAttendanceController::class, 'downloadAttachment'])
            ->name('eap-edp-sessions.attachment');
        Route::get('eap-edp-sessions/{eapEdpSession}/photo', [EapEdpSessionAttendanceController::class, 'downloadPhoto'])
            ->name('eap-edp-sessions.photo');
        Route::get('district-workshop-sessions/dashboard', [DistrictWorkshopSessionAttendanceController::class, 'dashboard'])->name('district-workshop-sessions.dashboard');
        Route::get('district-workshop-sessions/export', [DistrictWorkshopSessionAttendanceController::class, 'export'])->name('district-workshop-sessions.export');
        Route::get('district-workshop-sessions/{districtWorkshopSession}', [DistrictWorkshopSessionAttendanceController::class, 'show'])->name('district-workshop-sessions.show');
        Route::get('district-workshop-sessions/{districtWorkshopSession}/export', [DistrictWorkshopSessionAttendanceController::class, 'exportSingle'])->name('district-workshop-sessions.export-single');
        Route::get('district-workshop-sessions/{districtWorkshopSession}/attachment', [DistrictWorkshopSessionAttendanceController::class, 'downloadAttachment'])
            ->name('district-workshop-sessions.attachment');
        Route::get('block-workshops', [BlockWorkshopAdminController::class, 'index'])->name('block-workshops.index');
        Route::get('block-workshops/export', [BlockWorkshopAdminController::class, 'export'])->name('block-workshops.export');
        Route::get('block-workshops/{blockWorkshop}', [BlockWorkshopAdminController::class, 'show'])->name('block-workshops.show');
        Route::get('block-workshops/{blockWorkshop}/attachment', [BlockWorkshopAdminController::class, 'downloadAttachment'])->name('block-workshops.attachment');
        Route::get('block-workshops/{blockWorkshop}/participants-export', [BlockWorkshopAdminController::class, 'exportParticipants'])->name('block-workshops.participants-export');
        Route::get('social-media-posts/dashboard', [SocialMediaPostController::class, 'dashboard'])->name('social-media-posts.dashboard');
        Route::get('social-media-posts', SocialMediaPostLandingController::class)->name('social-media-posts.index');
        Route::get('social-media-posts/thumbnail', [SocialMediaPostController::class, 'thumbnail'])->name('social-media-posts.thumbnail');
        Route::get('social-media-posts/export', [SocialMediaPostController::class, 'export'])->name('social-media-posts.export');
        Route::get('social-media-posts/{socialMediaPost}', [SocialMediaPostController::class, 'show'])->name('social-media-posts.show');
        Route::delete('social-media-posts/{socialMediaPost}', [SocialMediaPostController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('social-media-posts.destroy');

        Route::get('capacity-building-stakeholders/dashboard', [StakeholderCapacityBuildingSessionController::class, 'dashboard'])->name('capacity-building-stakeholders.dashboard');
        Route::get('capacity-building-stakeholders', CapacityBuildingStakeholdersLandingController::class)->name('capacity-building-stakeholders.index');
        Route::get('capacity-building-stakeholders/export', [StakeholderCapacityBuildingSessionController::class, 'export'])->name('capacity-building-stakeholders.export');
        Route::get('capacity-building-stakeholders/{cbsSession}', [StakeholderCapacityBuildingSessionController::class, 'show'])->name('capacity-building-stakeholders.show');
        Route::get('capacity-building-stakeholders/{cbsSession}/attachment', [StakeholderCapacityBuildingSessionController::class, 'downloadAttachment'])
            ->name('capacity-building-stakeholders.attachment');

        Route::get('pitch-deck-preparations/dashboard', [PitchDeckPreparationController::class, 'dashboard'])->name('pitch-deck-preparations.dashboard');
        Route::get('pitch-deck-preparations', PitchDeckPreparationLandingController::class)->name('pitch-deck-preparations.index');
        Route::get('pitch-deck-preparations/export', [PitchDeckPreparationController::class, 'export'])->name('pitch-deck-preparations.export');
        Route::delete('pitch-deck-preparations/{pitchDeckPreparation}', [PitchDeckPreparationController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('pitch-deck-preparations.destroy');
        Route::get('pitch-deck-preparations/{pitchDeckPreparation}/deck', [PitchDeckPreparationController::class, 'downloadDeck'])
            ->name('pitch-deck-preparations.deck');
        Route::get('pitch-deck-preparations/{pitchDeckPreparation}', [PitchDeckPreparationController::class, 'show'])->name('pitch-deck-preparations.show');

        Route::get('market-linkages/dashboard', [MarketLinkageController::class, 'dashboard'])->name('market-linkages.dashboard');
        Route::get('market-linkages/export', [MarketLinkageController::class, 'export'])->name('market-linkages.export');
        Route::get('market-linkages/{market_linkage}', [MarketLinkageController::class, 'show'])->name('market-linkages.show');
        Route::get('market-linkages/{market_linkage}/partners/{partner}/document', [MarketLinkageController::class, 'downloadDocument'])
            ->name('market-linkages.document');

        Route::get('community-org-outreach/dashboard', [CommunityOrganizationOutreachController::class, 'dashboard'])->name('community-org-outreach.dashboard');
        Route::get('community-org-outreach/export', [CommunityOrganizationOutreachController::class, 'export'])->name('community-org-outreach.export');
        Route::get('community-org-outreach/{communityOrgOutreach}', [CommunityOrganizationOutreachController::class, 'show'])->name('community-org-outreach.show');
        Route::get('community-org-outreach/{communityOrgOutreach}/document', [CommunityOrganizationOutreachController::class, 'downloadDocument'])->name('community-org-outreach.document');
        Route::get('community-org-outreach/{communityOrgOutreach}/photo', [CommunityOrganizationOutreachController::class, 'downloadPhoto'])->name('community-org-outreach.photo');
        Route::delete('community-org-outreach/{communityOrgOutreach}', [CommunityOrganizationOutreachController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('community-org-outreach.destroy');

        /** Read-only batches view for state admin (all hubs/districts, filterable) */
        Route::get('batches', [BatchReadOnlyController::class, 'index'])->name('batches.index');
        Route::get('batches/legacy/{legacy_batch}', [BatchReadOnlyController::class, 'showLegacy'])
            ->whereNumber('legacy_batch')
            ->name('batches.legacy.show');
        Route::get('batches/{batch}', [BatchReadOnlyController::class, 'show'])->name('batches.show');
        Route::get('batches/{batch}/onboarding-letter', [BatchReadOnlyController::class, 'downloadOnboardingLetter'])->name('batches.onboarding-letter');
        Route::delete('batches/{batch}', [BatchReadOnlyController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('batches.destroy');

        Route::get('hub-batch-compliance', [HubBatchComplianceController::class, 'index'])->name('hub-batch-compliance.index');
        Route::get('hub-batch-compliance/requests', [HubBatchComplianceController::class, 'requests'])->name('hub-batch-compliance.requests');
        Route::post('hub-batch-compliance/extend', [HubBatchComplianceController::class, 'extend'])->name('hub-batch-compliance.extend');
        Route::post('hub-batch-compliance/waive', [HubBatchComplianceController::class, 'waive'])->name('hub-batch-compliance.waive');
        Route::post('hub-batch-compliance/approve-edit-request', [HubBatchComplianceController::class, 'approveEditRequest'])->name('hub-batch-compliance.approve-edit-request');
        Route::post('hub-batch-compliance/undo-reject', [HubBatchComplianceController::class, 'undoReject'])->name('hub-batch-compliance.undo-reject');
    });

    Route::middleware('hub_admin')->prefix('hub')->name('hub.')->group(function () {
        Route::get('deliverables', [DeliverablesReportController::class, 'index'])->name('deliverables.index');
        Route::get('deliverables/breakdown/export', [DeliverablesReportController::class, 'breakdownExport'])->name('deliverables.breakdown.export');
        Route::get('deliverables/breakdown/export/csv', [DeliverablesReportController::class, 'breakdownExportCsv'])->name('deliverables.breakdown.export.csv');
        Route::get('deliverables/breakdown/export/pdf', [DeliverablesReportController::class, 'breakdownExportPdf'])->name('deliverables.breakdown.export.pdf');
        Route::get('deliverables/breakdown', [DeliverablesReportController::class, 'breakdown'])->name('deliverables.breakdown');
        Route::get('deliverables/export', [DeliverablesReportController::class, 'export'])->name('deliverables.export');
        Route::get('applications', [HubApplicationsController::class, 'index'])->name('applications.index');
        Route::get('onboarded', [OnboardedApplicantController::class, 'index'])->name('onboarded.index');
        Route::get('onboarded/export', [OnboardedApplicantController::class, 'export'])->name('onboarded.export');
        Route::get('field-coordinator-reports', [FieldCoordinatorReportController::class, 'index'])->name('field-coordinator-reports.index');
        Route::get('field-coordinator-reports/{attendanceReport}/attachment', [FieldCoordinatorReportController::class, 'downloadAttachment'])->name('field-coordinator-reports.attachment');
        Route::get('field-coordinator-reports/{attendanceReport}/attendance-sheet', [FieldCoordinatorReportController::class, 'downloadAttendanceSheet'])->name('field-coordinator-reports.sheet');
        Route::get('applications/export', [HubApplicationsController::class, 'export'])->name('applications.export');
        Route::get('batches', [HubBatchController::class, 'index'])->name('batches.index');
        Route::get('batches/{batch}/onboarding-letter', [BatchReadOnlyController::class, 'downloadOnboardingLetter'])->name('batches.onboarding-letter');
        Route::get('staff-performance', [HubStaffPerformanceController::class, 'index'])->name('staff-performance.index');
        Route::get('pending-actions', [HubPendingActionsController::class, 'index'])->name('pending-actions.index');
        Route::get('onboarding-insight', [HubOnboardingInsightController::class, 'index'])->name('onboarding-insight.index');
        Route::get('community-org-outreach/create', [CommunityOrganizationOutreachController::class, 'create'])->name('community-org-outreach.create');
        Route::post('community-org-outreach', [CommunityOrganizationOutreachController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('community-org-outreach.store');
        Route::get('community-org-outreach/dashboard', [CommunityOrganizationOutreachController::class, 'dashboard'])->name('community-org-outreach.dashboard');
        Route::get('community-org-outreach/export', [CommunityOrganizationOutreachController::class, 'export'])->name('community-org-outreach.export');
        Route::get('community-org-outreach/{communityOrgOutreach}', [CommunityOrganizationOutreachController::class, 'show'])->name('community-org-outreach.show');
        Route::get('community-org-outreach/{communityOrgOutreach}/document', [CommunityOrganizationOutreachController::class, 'downloadDocument'])->name('community-org-outreach.document');
        Route::get('community-org-outreach/{communityOrgOutreach}/photo', [CommunityOrganizationOutreachController::class, 'downloadPhoto'])->name('community-org-outreach.photo');
        Route::delete('community-org-outreach/{communityOrgOutreach}', [CommunityOrganizationOutreachController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('community-org-outreach.destroy');
        Route::get('market-linkages/dashboard', [MarketLinkageController::class, 'dashboard'])->name('market-linkages.dashboard');
        Route::get('market-linkages/export', [MarketLinkageController::class, 'export'])->name('market-linkages.export');
        Route::get('market-linkages/{market_linkage}', [MarketLinkageController::class, 'show'])->name('market-linkages.show');
        Route::get('market-linkages/{market_linkage}/partners/{partner}/document', [MarketLinkageController::class, 'downloadDocument'])
            ->name('market-linkages.document');
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
