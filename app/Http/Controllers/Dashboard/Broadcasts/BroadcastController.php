<?php

namespace App\Http\Controllers\Dashboard\Broadcasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcast\BroadcastCreateRequest;
use App\Http\Requests\Broadcast\StoreBroadcastRequest;
use App\Http\Requests\Broadcast\UpdateBroadcastRequest;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Policies\BroadcastPolicy;
use App\Services\Broadcast\BroadcastBodyText;
use App\Services\Broadcast\BroadcastDispatchService;
use App\Services\Broadcast\BroadcastSnapshotBuilder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastPolicy $broadcastPolicy,
        private readonly BroadcastSnapshotBuilder $snapshotBuilder,
        private readonly BroadcastBodyText $broadcastBodyText,
        private readonly BroadcastDispatchService $dispatchService,
    ) {
    }

    /** Hub create: prefill konteks terkunci dari query + daftar konteks yang boleh ditarget. */
    public function create(BroadcastCreateRequest $request): Response
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validated();
        $user = $request->user();

        $event = $this->resolvePrefillEvent($validated);
        $period = $this->resolvePrefillPeriod($validated);

        return Inertia::render('Dashboard/Broadcasts/Create', [
            'prefill' => [
                'event_id' => $event?->id,
                'period_id' => $period?->id,
                'event_title' => $event?->title,
                'period_name' => $period?->name,
                'locked_event' => $event !== null,
                'locked_period' => $period !== null,
            ],
            'sources' => Broadcast::SOURCES,
            'allowed_events' => $this->allowedEvents($user),
            'allowed_periods' => $this->allowedPeriods($user),
        ]);
    }

    /** Simpan broadcast + bekukan snapshot penerima sebagai source of truth. */
    public function store(StoreBroadcastRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $this->authorizeBroadcastTarget($user, $validated);

        $snapshot = $this->snapshotBuilder->build($validated);

        $broadcast = Broadcast::query()->create([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? null,
            'body_html' => $validated['body_html'] ?? null,
            'body_text' => $this->broadcastBodyText->fromHtml($validated['body_html'] ?? null),
            'source' => $validated['source'],
            'event_id' => $validated['event_id'] ?? null,
            'period_id' => $validated['period_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'send_delay_seconds' => $validated['send_delay_seconds'] ?? 0,
            'status' => isset($validated['scheduled_at']) ? Broadcast::STATUS_SCHEDULED : Broadcast::STATUS_DRAFT,
            'recipient_snapshot' => $snapshot,
            'recipient_count' => $snapshot['total'],
            'created_by' => $user->id,
        ]);

        return redirect()->route('dashboard.broadcasts.show', $broadcast);
    }

    /** Ubah konten draft/scheduled; snapshot penerima tidak tersentuh. */
    public function update(UpdateBroadcastRequest $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorize('update', $broadcast);

        $validated = $request->validated();

        $broadcast->update([
            'subject' => $validated['subject'] ?? $broadcast->subject,
            'body_html' => $validated['body_html'] ?? $broadcast->body_html,
            'body_text' => array_key_exists('body_html', $validated)
                ? $this->broadcastBodyText->fromHtml($validated['body_html'])
                : $broadcast->body_text,
            'scheduled_at' => $validated['scheduled_at'] ?? $broadcast->scheduled_at,
            'send_delay_seconds' => $validated['send_delay_seconds'] ?? $broadcast->send_delay_seconds,
        ]);

        return redirect()->route('dashboard.broadcasts.show', $broadcast);
    }

    /** Kirim-sekarang: otorisasi konteks + kunci processing + antre job per penerima. */
    public function send(Broadcast $broadcast): RedirectResponse
    {
        $this->authorize('send', $broadcast);

        $this->dispatchService->sendNow($broadcast->fresh() ?? $broadcast);

        return redirect()->route('dashboard.broadcasts.show', $broadcast);
    }

    /** Tampilkan broadcast beserta snapshot penerima (read-only source of truth). */
    public function show(Broadcast $broadcast): Response
    {
        $this->authorize('view', $broadcast);

        $broadcast->load(['event:id,title', 'period:id,name']);

        return Inertia::render('Dashboard/Broadcasts/Show', [
            'broadcast' => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'subject' => $broadcast->subject,
                'body_html' => $broadcast->body_html,
                'body_text' => $broadcast->body_text,
                'source' => $broadcast->source,
                'event_id' => $broadcast->event_id,
                'period_id' => $broadcast->period_id,
                'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                'send_delay_seconds' => $broadcast->send_delay_seconds,
                'status' => $broadcast->status,
                'recipient_count' => $broadcast->recipient_count,
                'created_at' => $broadcast->created_at?->toIso8601String(),
            ],
            'snapshot' => $broadcast->recipient_snapshot,
            'context' => [
                'event_title' => $broadcast->event?->title,
                'period_name' => $broadcast->period?->name,
            ],
        ]);
    }

    /** Direct call (bukan Gate): dispatch Gate me-resolve policy dari target class sehingga ability cross-model tak cocok — correctness dulu. */
    private function authorizeBroadcastTarget(User $user, array $validated): void
    {
        if (($validated['source'] ?? null) === Broadcast::SOURCE_RECRUITMENT_APPLICANTS) {
            $period = RecruitmentPeriod::query()->findOrFail($validated['period_id']);

            abort_unless($this->broadcastPolicy->sendToPeriod($user, $period), 403);

            return;
        }

        $event = Event::query()->findOrFail($validated['event_id']);

        abort_unless($this->broadcastPolicy->sendToEvent($user, $event), 403);
    }

    /** Ambil event prefill yang lolos validasi exists. */
    private function resolvePrefillEvent(array $validated): ?Event
    {
        $eventId = $validated['event_id'] ?? null;

        return is_string($eventId) && $eventId !== '' ? Event::query()->find($eventId) : null;
    }

    /** Ambil periode prefill yang lolos validasi exists. */
    private function resolvePrefillPeriod(array $validated): ?RecruitmentPeriod
    {
        $periodId = $validated['period_id'] ?? null;

        return is_string($periodId) && $periodId !== '' ? RecruitmentPeriod::query()->find($periodId) : null;
    }

    /** Direct call (lihat authorizeBroadcastTarget): filter listing wajib signature (User, Event) yang tak bisa lewat Gate. */
    private function allowedEvents(User $user): array
    {
        return Event::query()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->filter(fn (Event $event): bool => $this->broadcastPolicy->sendToEvent($user, $event))
            ->map(fn (Event $event): array => ['id' => $event->id, 'title' => $event->title])
            ->values()
            ->all();
    }

    /** Direct call (lihat authorizeBroadcastTarget): filter listing wajib signature (User, Period) yang tak bisa lewat Gate. */
    private function allowedPeriods(User $user): array
    {
        return RecruitmentPeriod::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (RecruitmentPeriod $period): bool => $this->broadcastPolicy->sendToPeriod($user, $period))
            ->map(fn (RecruitmentPeriod $period): array => ['id' => $period->id, 'name' => $period->name])
            ->values()
            ->all();
    }
}
