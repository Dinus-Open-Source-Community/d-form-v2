<?php

use App\Http\Controllers\Dashboard\Recruitment\RecruitmentApplicationController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentCorrectionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentInterviewSessionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentInterviewExportController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentActivityLogController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentDashboardController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentDivisionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentReportController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentPeriodController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentScreeningController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentEvaluationController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentFinalSelectionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentMyInterviewController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'recruitment.access'])
    ->prefix('admin/recruitment')
    ->name('dashboard.recruitment.')
    ->group(function (): void {
        Route::get('/', RecruitmentDashboardController::class)->name('index');

        Route::get('reports/export/funnel.csv', [RecruitmentReportController::class, 'exportFunnel'])
            ->name('reports.export.funnel');
        Route::get('reports/export/applicants.csv', [RecruitmentReportController::class, 'exportApplicants'])
            ->name('reports.export.applicants');

        Route::get('activity-logs', [RecruitmentActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('interview-sessions', RedirectController::class)
            ->defaults('destination', '/admin/recruitment')
            ->defaults('status', 302);
        Route::get('reports', RedirectController::class)
            ->defaults('destination', '/admin/recruitment')
            ->defaults('status', 302);
        Route::get('periods', RedirectController::class)
            ->defaults('destination', '/admin/recruitment')
            ->defaults('status', 302);

        Route::resource('periods', RecruitmentPeriodController::class)->except(['index', 'edit']);
        Route::get('periods/{period}/applications/{application}', [RecruitmentPeriodController::class, 'application'])
            ->name('periods.applications.show');
        Route::post('periods/{period}/open', [RecruitmentPeriodController::class, 'open'])->name('periods.open');
        Route::post('periods/{period}/close', [RecruitmentPeriodController::class, 'close'])->name('periods.close');
        Route::post('periods/{period}/send-group-link', [RecruitmentPeriodController::class, 'sendGroupLink'])->name('periods.send-group-link');
        Route::post('periods/{period}/send-qr', [RecruitmentPeriodController::class, 'sendQr'])->name('periods.send-qr');
        Route::get('periods/{period}/qr-status', [RecruitmentPeriodController::class, 'qrStatus'])->name('periods.qr-status');
        Route::get('periods/{period}/interviews/export.csv', RecruitmentInterviewExportController::class)->name('periods.interviews.export');

        Route::put('divisions/{division}', [RecruitmentDivisionController::class, 'update'])->name('divisions.update');
        Route::post('interviewers/assign', [RecruitmentDivisionController::class, 'assignInterviewer'])->name('interviewers.assign');
        Route::post('interviewers', [RecruitmentDivisionController::class, 'storeInterviewer'])->name('interviewers.store');
        Route::delete('interviewers/{assignment}', [RecruitmentDivisionController::class, 'unassignInterviewer'])->name('interviewers.unassign');

        Route::get('applications/{application}/documents/{type}', [RecruitmentApplicationController::class, 'downloadDocument'])
            ->name('applications.documents.download')
            ->where('type', 'cv|portfolio|instagram_follow');
        Route::post('applications/{application}/screening/pass', [RecruitmentScreeningController::class, 'pass'])
            ->name('applications.screening.pass');
        Route::post('applications/{application}/screening/revision', [RecruitmentScreeningController::class, 'revision'])
            ->name('applications.screening.revision');
        Route::post('applications/{application}/screening/reject', [RecruitmentScreeningController::class, 'reject'])
            ->name('applications.screening.reject');
        Route::post('applications/{application}/verify', [RecruitmentApplicationController::class, 'verify'])
            ->name('applications.verify');
        Route::post('applications/{application}/resend-tracking', [RecruitmentApplicationController::class, 'resendTracking'])
            ->name('applications.resend-tracking');
        Route::post('applications/{application}/resend-email', [RecruitmentApplicationController::class, 'resendEmail'])
            ->name('applications.resend-email');
        Route::post('applications/{application}/evaluation', [RecruitmentEvaluationController::class, 'legacyOverride'])
            ->name('applications.evaluation.override');
        Route::post('interviews/{interview}/evaluation-override', [RecruitmentEvaluationController::class, 'override'])
            ->name('interviews.evaluation.override');
        Route::post('applications/{application}/final/accept', [RecruitmentFinalSelectionController::class, 'accept'])
            ->name('applications.final.accept');
        Route::post('applications/{application}/final/reject', [RecruitmentFinalSelectionController::class, 'reject'])
            ->name('applications.final.reject');

        Route::post('corrections/{correction}/approve', [RecruitmentCorrectionController::class, 'approve'])
            ->name('corrections.approve');
        Route::post('corrections/{correction}/reject', [RecruitmentCorrectionController::class, 'reject'])
            ->name('corrections.reject');

        Route::post('interview-sessions', [RecruitmentInterviewSessionController::class, 'store'])
            ->name('interview-sessions.store');
        Route::get('interview-sessions/{session}', [RecruitmentInterviewSessionController::class, 'show'])
            ->name('interview-sessions.show');
        Route::match(['put', 'patch'], 'interview-sessions/{session}', [RecruitmentInterviewSessionController::class, 'update'])
            ->name('interview-sessions.update');
        Route::delete('interview-sessions/{session}', [RecruitmentInterviewSessionController::class, 'destroy'])
            ->name('interview-sessions.destroy');
        Route::get('attendance-scan', fn () => to_route('dashboard.scan.index'))->name('attendance-scan');

        Route::get('my-interviews', [RecruitmentMyInterviewController::class, 'index'])
            ->name('my-interviews.index');
        Route::get('my-interviews/{interview}', [RecruitmentMyInterviewController::class, 'show'])
            ->name('my-interviews.show');
        Route::post('my-interviews/{interview}/evaluate', [RecruitmentMyInterviewController::class, 'evaluate'])
            ->name('my-interviews.evaluate');
        Route::post('my-interviews/secondary-claim', [RecruitmentMyInterviewController::class, 'claimSecondary'])
            ->name('my-interviews.secondary-claim');
    });
