<?php

use App\Http\Controllers\Dashboard\Recruitment\RecruitmentApplicationController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentCorrectionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentInterviewController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentInterviewSessionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentActivityLogController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentDashboardController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentDivisionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentEmailTemplateController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentReportController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentPeriodController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentScreeningController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentEvaluationController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentFinalSelectionController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentMyInterviewController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentAttendanceScanController;
use App\Http\Controllers\Dashboard\Recruitment\RecruitmentQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'recruitment.access'])
    ->prefix('admin/recruitment')
    ->name('dashboard.recruitment.')
    ->group(function (): void {
        Route::get('/', RecruitmentDashboardController::class)->name('index');

        Route::get('reports', [RecruitmentReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/funnel.csv', [RecruitmentReportController::class, 'exportFunnel'])
            ->name('reports.export.funnel');
        Route::get('reports/export/applicants.csv', [RecruitmentReportController::class, 'exportApplicants'])
            ->name('reports.export.applicants');

        Route::get('activity-logs', [RecruitmentActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('email-templates', [RecruitmentEmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{template}/edit', [RecruitmentEmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{template}', [RecruitmentEmailTemplateController::class, 'update'])->name('email-templates.update');

        Route::resource('periods', RecruitmentPeriodController::class);
        Route::post('periods/{period}/open', [RecruitmentPeriodController::class, 'open'])->name('periods.open');
        Route::post('periods/{period}/close', [RecruitmentPeriodController::class, 'close'])->name('periods.close');

        Route::get('divisions', [RecruitmentDivisionController::class, 'index'])->name('divisions.index');
        Route::put('divisions/{division}', [RecruitmentDivisionController::class, 'update'])->name('divisions.update');
        Route::post('interviewers/assign', [RecruitmentDivisionController::class, 'assignInterviewer'])->name('interviewers.assign');
        Route::delete('interviewers/{assignment}', [RecruitmentDivisionController::class, 'unassignInterviewer'])->name('interviewers.unassign');

        Route::get('applications', [RecruitmentApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [RecruitmentApplicationController::class, 'show'])->name('applications.show');
        Route::get('applications/{application}/documents/{type}', [RecruitmentApplicationController::class, 'downloadDocument'])
            ->name('applications.documents.download')
            ->where('type', 'cv|portfolio');
        Route::post('applications/{application}/screening/pass', [RecruitmentScreeningController::class, 'pass'])
            ->name('applications.screening.pass');
        Route::post('applications/{application}/screening/revision', [RecruitmentScreeningController::class, 'revision'])
            ->name('applications.screening.revision');
        Route::post('applications/{application}/screening/reject', [RecruitmentScreeningController::class, 'reject'])
            ->name('applications.screening.reject');
        Route::post('applications/{application}/verify', [RecruitmentApplicationController::class, 'verify'])
            ->name('applications.verify');
        Route::post('applications/{application}/evaluation', [RecruitmentEvaluationController::class, 'override'])
            ->name('applications.evaluation.override');
        Route::post('applications/{application}/final/accept', [RecruitmentFinalSelectionController::class, 'accept'])
            ->name('applications.final.accept');
        Route::post('applications/{application}/final/reject', [RecruitmentFinalSelectionController::class, 'reject'])
            ->name('applications.final.reject');

        Route::post('corrections/{correction}/approve', [RecruitmentCorrectionController::class, 'approve'])
            ->name('corrections.approve');
        Route::post('corrections/{correction}/reject', [RecruitmentCorrectionController::class, 'reject'])
            ->name('corrections.reject');

        Route::get('interview-sessions', [RecruitmentInterviewSessionController::class, 'index'])
            ->name('interview-sessions.index');
        Route::post('interview-sessions', [RecruitmentInterviewSessionController::class, 'store'])
            ->name('interview-sessions.store');
        Route::get('interview-sessions/{session}', [RecruitmentInterviewSessionController::class, 'show'])
            ->name('interview-sessions.show');
        Route::post('interview-sessions/{session}/schedule', [RecruitmentInterviewSessionController::class, 'schedule'])
            ->name('interview-sessions.schedule');
        Route::post('interviews/{interview}/reschedule', [RecruitmentInterviewController::class, 'reschedule'])
            ->name('interviews.reschedule');
        Route::post('interviews/{interview}/reassign', [RecruitmentInterviewController::class, 'reassign'])
            ->name('interviews.reassign');

        Route::get('queue/{session}', [RecruitmentQueueController::class, 'show'])
            ->name('queue.show');
        Route::get('queue/{session}/poll', [RecruitmentQueueController::class, 'poll'])
            ->name('queue.poll');
        Route::post('queue/{session}/call-next', [RecruitmentQueueController::class, 'callNext'])
            ->name('queue.call-next');
        Route::post('queue/{session}/no-show', [RecruitmentQueueController::class, 'markNoShow'])
            ->name('queue.no-show');
        Route::post('queue/{entry}/complete', [RecruitmentQueueController::class, 'complete'])
            ->name('queue.complete');

        Route::get('attendance-scan', [RecruitmentAttendanceScanController::class, 'show'])
            ->name('attendance-scan');
        Route::post('attendance-scan', [RecruitmentAttendanceScanController::class, 'store'])
            ->name('attendance-scan.store');

        Route::get('my-interviews', [RecruitmentMyInterviewController::class, 'index'])
            ->name('my-interviews.index');
        Route::get('my-interviews/queue/{session}', [RecruitmentMyInterviewController::class, 'queue'])
            ->name('my-interviews.queue');
        Route::get('my-interviews/{application}', [RecruitmentMyInterviewController::class, 'show'])
            ->name('my-interviews.show');
        Route::post('my-interviews/{application}/evaluate', [RecruitmentMyInterviewController::class, 'evaluate'])
            ->name('my-interviews.evaluate');
    });
