<?php

namespace App\Http\Controllers\Dashboard\Broadcasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcast\BroadcastCreateRequest;
use App\Http\Requests\Broadcast\BroadcastIndexRequest;
use App\Http\Requests\Broadcast\StoreBroadcastAttachmentRequest;
use App\Http\Requests\Broadcast\StoreBroadcastRequest;
use App\Http\Requests\Broadcast\UpdateBroadcastRequest;
use App\Mail\BroadcastMail;
use App\Models\Broadcast;
use App\Models\BroadcastAttachment;
use App\Models\Event;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Policies\BroadcastPolicy;
use App\Services\Broadcast\BroadcastBodyText;
use App\Services\Broadcast\BroadcastDispatchService;
use App\Services\Broadcast\BroadcastSnapshotBuilder;
use App\Services\Broadcast\BroadcastTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastPolicy $broadcastPolicy,
        private readonly BroadcastSnapshotBuilder $snapshotBuilder,
        private readonly BroadcastBodyText $broadcastBodyText,
        private readonly BroadcastDispatchService $dispatchService,
        private readonly BroadcastTrackingService $trackingService,
    ) {
    }

    /** Daftar broadcast satu periode untuk tab Period (satu query, tanpa N+1). */
    public function index(BroadcastIndexRequest $request): Response
    {
        $period = RecruitmentPeriod::query()->findOrFail($request->validated('period_id'));

        /** @var User $user */
        $user = $request->user();

        abort_unless($this->broadcastPolicy->viewAnyForPeriod($user, $period), 403);

        $broadcasts = Broadcast::query()
            ->where('period_id', $period->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'subject', 'status', 'scheduled_at', 'recipient_count', 'created_at']);

        return Inertia::render('Dashboard/Broadcasts/Index', [
            'broadcasts' => $broadcasts
                ->map(fn (Broadcast $broadcast): array => [
                    'id' => $broadcast->id,
                    'name' => $broadcast->name,
                    'subject' => $broadcast->subject,
                    'status' => $broadcast->status,
                    'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                    'recipient_count' => $broadcast->recipient_count,
                    'created_at' => $broadcast->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
            ],
        ]);
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

        $updates = [
            'subject' => $validated['subject'] ?? $broadcast->subject,
            'body_html' => $validated['body_html'] ?? $broadcast->body_html,
            'body_text' => array_key_exists('body_html', $validated)
                ? $this->broadcastBodyText->fromHtml($validated['body_html'])
                : $broadcast->body_text,
            'scheduled_at' => $validated['scheduled_at'] ?? $broadcast->scheduled_at,
            'send_delay_seconds' => $validated['send_delay_seconds'] ?? $broadcast->send_delay_seconds,
        ];

        if ($broadcast->status === Broadcast::STATUS_DRAFT && ! empty($validated['scheduled_at'])) {
            $updates['status'] = Broadcast::STATUS_SCHEDULED;
        }

        $broadcast->update($updates);

        return redirect()->route('dashboard.broadcasts.show', $broadcast);
    }

    /** Kirim-sekarang: otorisasi konteks + kunci processing + antre job per penerima. */
    public function send(Broadcast $broadcast): RedirectResponse
    {
        $this->authorize('send', $broadcast);

        $this->dispatchService->sendNow($broadcast->fresh() ?? $broadcast);

        return redirect()->route('dashboard.broadcasts.show', $broadcast);
    }

    /** Pratinjau HTML subject+body dengan nama sample; draft/scheduled milik konteks. */
    public function preview(Broadcast $broadcast): HttpResponse
    {
        $this->authorize('view', $broadcast);

        abort_unless(
            in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true),
            403,
            'Pratinjau hanya tersedia untuk draft/terjadwal.'
        );

        $sample = $this->previewSampleName($broadcast);
        $subject = str_replace('{{nama}}', $sample, (string) ($broadcast->subject ?? $broadcast->name));
        $body = str_replace('{{nama}}', e($sample), (string) $broadcast->body_html);

        return response(
            '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>'.e($subject).'</title></head>'
                .'<body><h1>'.e($subject).'</h1><div>'.$body.'</div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /** Kirim email uji ke satu alamat; tak menulis EmailLog/tracking. */
    public function testSend(Request $request, Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        Mail::to($validated['email'])->send(new BroadcastMail($broadcast->fresh() ?? $broadcast, $validated['email']));

        return response()->json(['sent' => true, 'email' => $validated['email']]);
    }

    /** Simpan satu lampiran (pdf/jpg/png ≤5MB, maks 3 per broadcast). */
    public function storeAttachment(StoreBroadcastAttachmentRequest $request, Broadcast $broadcast): JsonResponse
    {
        $this->authorize('update', $broadcast);

        if ($broadcast->attachments()->count() >= BroadcastAttachment::MAX_PER_BROADCAST) {
            return response()->json(['message' => 'Maksimal 3 file per broadcast.'], 422);
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $path = $file->store("broadcast-attachments/{$broadcast->id}");

        $attachment = $broadcast->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        return response()->json(['attachment' => [
            'id' => $attachment->id,
            'path' => $attachment->path,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
        ]], 201);
    }

    /** Hapus lampiran + berkasnya; 404 bila bukan milik broadcast. */
    public function destroyAttachment(Broadcast $broadcast, BroadcastAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $broadcast);

        abort_unless($attachment->broadcast_id === $broadcast->id, 404);

        Storage::delete($attachment->path);
        $attachment->delete();

        return response()->json(['deleted' => true]);
    }

    /** Kirim-ulang hanya penerima gagal; 422 bila processing/sent. */
    public function retry(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        if (in_array($broadcast->status, [Broadcast::STATUS_PROCESSING, Broadcast::STATUS_SENT], true)) {
            return response()->json(['message' => 'Broadcast yang sedang diproses/sudah terkirim tidak bisa retry.', 'retried' => 0], 422);
        }

        $retried = $this->dispatchService->retryFailed($broadcast->fresh() ?? $broadcast);

        return response()->json(['retried' => $retried]);
    }

    /** Batalkan jadwal: scheduled -> draft (re-schedulable); 422 bila bukan scheduled. */
    public function cancel(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        if ($broadcast->status !== Broadcast::STATUS_SCHEDULED) {
            return response()->json(['message' => 'Hanya broadcast terjadwal yang bisa dibatalkan.'], 422);
        }

        $broadcast->update(['status' => Broadcast::STATUS_DRAFT, 'scheduled_at' => null]);

        return response()->json(['status' => Broadcast::STATUS_DRAFT]);
    }

    /** Baris per-penerima + ringkasan dari snapshot + EmailLog existing. */
    public function tracking(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        return response()->json($this->trackingService->for($broadcast->fresh() ?? $broadcast));
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

    /** Nama sample pratinjau: penerima pertama snapshot (fallback 'Peserta'). */
    private function previewSampleName(Broadcast $broadcast): string
    {
        foreach ($broadcast->recipient_snapshot['recipients'] ?? [] as $recipient) {
            $name = $recipient['name'] ?? null;

            if (is_string($name) && trim($name) !== '') {
                return $name;
            }
        }

        return 'Peserta';
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
