<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\StoreBroadcastRequest;
use App\Models\EmailBroadcast;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', EmailBroadcast::class);

        $broadcasts = EmailBroadcast::query()
            ->with(['event:id,title'])
            ->withCount('recipients')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (EmailBroadcast $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'status' => $b->status->value,
                'scheduled_at' => $b->scheduled_at?->toDateTimeString(),
                'total_recipients' => $b->total_recipients,
                'total_sent' => $b->total_sent,
                'total_failed' => $b->total_failed,
                'event_title' => $b->event?->title,
                'created_at' => $b->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Dashboard/Broadcasts/Index', [
            'broadcasts' => $broadcasts,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', EmailBroadcast::class);

        return Inertia::render('Dashboard/Broadcasts/Create', [
            'events' => Event::query()->orderByDesc('start_date')->limit(100)->get(['id', 'title']),
        ]);
    }

    public function store(StoreBroadcastRequest $request): RedirectResponse
    {
        $this->authorize('create', EmailBroadcast::class);

        $broadcast = EmailBroadcast::query()->create([
            'name' => $request->string('name')->toString(),
            'scheduled_at' => $request->scheduledAt(),
            'delay_min' => $request->integer('delay_min'),
            'delay_max' => $request->integer('delay_max'),
            'event_id' => $request->input('event_id'),
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['message' => 'Broadcast dibuat. Lanjutkan ke dataset.', 'type' => 'success']);

        return redirect()->route('dashboard.broadcasts.show', $broadcast);
    }

    public function show(EmailBroadcast $broadcast): Response
    {
        $this->authorize('view', $broadcast);

        $broadcast->load(['event:id,title', 'attachments']);
        $broadcast->loadCount([
            'recipients',
            'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'recipients as failed_count' => fn ($q) => $q->where('status', 'failed'),
            'recipients as pending_count' => fn ($q) => $q->where('status', 'pending'),
            'recipients as processing_count' => fn ($q) => $q->where('status', 'processing'),
            'recipients as cancelled_count' => fn ($q) => $q->where('status', 'cancelled'),
        ]);

        return Inertia::render('Dashboard/Broadcasts/Show', [
            'broadcast' => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'status' => $broadcast->status->value,
                'scheduled_at' => $broadcast->scheduled_at?->toDateTimeString(),
                'schedule_date' => $broadcast->scheduled_at?->format('Y-m-d'),
                'schedule_time' => $broadcast->scheduled_at?->format('H:i'),
                'delay_min' => $broadcast->delay_min,
                'delay_max' => $broadcast->delay_max,
                'event_id' => $broadcast->event_id,
                'event_title' => $broadcast->event?->title,
                'subject' => $broadcast->subject,
                'content' => $broadcast->content,
                'datasets' => $broadcast->datasets ?? [],
                'total_recipients' => $broadcast->total_recipients,
                'total_sent' => $broadcast->total_sent,
                'total_failed' => $broadcast->total_failed,
                'sent_count' => $broadcast->sent_count,
                'failed_count' => $broadcast->failed_count,
                'pending_count' => $broadcast->pending_count,
                'processing_count' => $broadcast->processing_count,
                'cancelled_count' => $broadcast->cancelled_count,
                'recipients_count' => $broadcast->recipients_count,
                'started_at' => $broadcast->started_at?->toDateTimeString(),
                'completed_at' => $broadcast->completed_at?->toDateTimeString(),
                'cancelled_at' => $broadcast->cancelled_at?->toDateTimeString(),
                'attachments' => $broadcast->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'file_name' => $a->file_name,
                    'mime_type' => $a->mime_type,
                    'file_size' => $a->file_size,
                ])->values()->all(),
            ],
            'events' => Event::query()->orderByDesc('start_date')->limit(100)->get(['id', 'title']),
        ]);
    }

    public function destroy(EmailBroadcast $broadcast): RedirectResponse
    {
        $this->authorize('delete', $broadcast);

        app(\App\Services\Broadcasting\BroadcastAttachmentService::class)->deleteAllFor($broadcast);
        $broadcast->delete();

        Inertia::flash('toast', ['message' => 'Broadcast dihapus.', 'type' => 'success']);

        return redirect()->route('dashboard.broadcasts.index');
    }
}
