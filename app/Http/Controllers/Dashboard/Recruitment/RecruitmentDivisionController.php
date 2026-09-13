<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\AssignRecruitmentInterviewerRequest;
use App\Http\Requests\Recruitment\UpdateRecruitmentDivisionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use App\Services\Recruitment\RecruitmentDivisionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentDivisionController extends Controller
{
    public function __construct(
        private readonly RecruitmentDivisionService $divisionService,
    ) {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', RecruitmentDivision::class);

        $divisions = $this->divisionService->listAllOrdered()
            ->map(fn (RecruitmentDivision $d) => $this->divisionService->toInertiaArray($d));

        $assignments = RecruitmentInterviewerDivision::query()
            ->with(['user:id,name,email', 'division:id,name,code'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (RecruitmentInterviewerDivision $a) => [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'user_name' => $a->user?->name,
                'user_email' => $a->user?->email,
                'division_id' => $a->recruitment_division_id,
                'division_name' => $a->division?->name,
                'division_code' => $a->division?->code,
            ])
            ->values()
            ->all();

        $interviewerCandidates = User::query()
            ->role(['recruitment-interviewer', 'recruitment-staff', 'admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->values()
            ->all();

        return Inertia::render('Dashboard/Recruitment/Divisions/Index', [
            'divisions' => $divisions,
            'assignments' => $assignments,
            'interviewerCandidates' => $interviewerCandidates,
        ]);
    }

    public function update(UpdateRecruitmentDivisionRequest $request, RecruitmentDivision $division): RedirectResponse
    {
        $this->authorize('update', $division);

        $this->divisionService->update($division, $request->validated());

        return redirect()
            ->back()
            ->with('message', 'Divisi berhasil diperbarui.');
    }

    public function assignInterviewer(AssignRecruitmentInterviewerRequest $request): RedirectResponse
    {
        $this->authorize('assignInterviewer', RecruitmentDivision::class);

        $validated = $request->validated();
        $division = RecruitmentDivision::query()->findOrFail($validated['recruitment_division_id']);
        $user = User::query()->findOrFail($validated['user_id']);

        $this->divisionService->assignInterviewer($division, $user);

        return redirect()
            ->back()
            ->with('message', 'Interviewer berhasil ditugaskan ke divisi.');
    }

    public function unassignInterviewer(RecruitmentInterviewerDivision $assignment): RedirectResponse
    {
        $this->authorize('assignInterviewer', RecruitmentDivision::class);

        $this->divisionService->unassignInterviewer($assignment);

        return redirect()
            ->back()
            ->with('message', 'Penugasan interviewer dihapus.');
    }
}
