<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\UpdateRecruitmentEmailTemplateRequest;
use App\Models\Recruitment\RecruitmentEmailTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentEmailTemplateController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RecruitmentEmailTemplate::class);

        $templates = RecruitmentEmailTemplate::query()
            ->orderBy('event_type')
            ->get()
            ->map(fn (RecruitmentEmailTemplate $template): array => [
                'id' => $template->id,
                'event_type' => $template->event_type,
                'subject' => $template->subject,
                'is_active' => $template->is_active,
                'available_variables' => $template->available_variables ?? [],
            ]);

        return Inertia::render('Dashboard/Recruitment/EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function edit(RecruitmentEmailTemplate $template): Response
    {
        $this->authorize('update', $template);

        return Inertia::render('Dashboard/Recruitment/EmailTemplates/Edit', [
            'template' => [
                'id' => $template->id,
                'event_type' => $template->event_type,
                'subject' => $template->subject,
                'body_html' => $template->body_html,
                'body_text' => $template->body_text,
                'is_active' => $template->is_active,
                'available_variables' => $template->available_variables ?? [],
            ],
        ]);
    }

    public function update(UpdateRecruitmentEmailTemplateRequest $request, RecruitmentEmailTemplate $template): RedirectResponse
    {
        $validated = $request->validated();

        $template->update([
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'body_text' => $validated['body_text'] ?? strip_tags($validated['body_html']),
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('dashboard.recruitment.email-templates.index')
            ->with('message', 'Template email berhasil diperbarui.');
    }
}
