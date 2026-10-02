<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\StoreBroadcastRecipientRequest;
use App\Http\Requests\Broadcasting\UpdateBroadcastRecipientRequest;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Services\Broadcasting\BroadcastSnapshotService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastRecipientController extends Controller
{
    public function index(EmailBroadcast $broadcast): Response
    {
        $this->authorize('view', $broadcast);

        $recipients = $broadcast->recipients()
            ->orderBy('created_at')
            ->paginate(25)
            ->through(fn (EmailBroadcastRecipient $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'status' => $r->status->value,
                'attempts' => $r->attempts,
                'sent_at' => $r->sent_at?->toDateTimeString(),
                'failed_at' => $r->failed_at?->toDateTimeString(),
                'error_message' => $r->error_message,
            ]);

        $summary = app(BroadcastSnapshotService::class)->duplicateSummary($broadcast);

        // Inertia partial reload: kembalikan JSON bila diminta via X-Inertia-Partial-Data.
        if (request()->header('X-Inertia-Partial-Data')) {
            return Inertia::render('Dashboard/Broadcasts/Show', [
                'recipients' => $recipients,
                'duplicateSummary' => $summary,
            ]);
        }

        return Inertia::render('Dashboard/Broadcasts/Show', [
            'recipients' => $recipients,
            'duplicateSummary' => $summary,
        ]);
    }

    public function store(
        StoreBroadcastRecipientRequest $request,
        EmailBroadcast $broadcast,
        BroadcastSnapshotService $snapshots,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);
        $snapshots->assertEditable($broadcast);

        $broadcast->recipients()->create([
            'name' => $request->input('name'),
            'email' => strtolower(trim($request->string('email')->toString())),
            'status' => 'pending',
        ]);

        $snapshots->refreshCounters($broadcast);

        return redirect()->back()->with('toast', ['message' => 'Recipient ditambahkan.', 'type' => 'success']);
    }

    public function update(
        UpdateBroadcastRecipientRequest $request,
        EmailBroadcast $broadcast,
        EmailBroadcastRecipient $recipient,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);
        abort_unless($recipient->broadcast_id === $broadcast->id, 404);
        app(BroadcastSnapshotService::class)->assertEditable($broadcast);

        $recipient->forceFill([
            'name' => $request->input('name'),
            'email' => strtolower(trim($request->string('email')->toString())),
        ])->save();

        return redirect()->back()->with('toast', ['message' => 'Recipient diperbarui.', 'type' => 'success']);
    }

    public function destroy(
        EmailBroadcast $broadcast,
        EmailBroadcastRecipient $recipient,
        BroadcastSnapshotService $snapshots,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);
        abort_unless($recipient->broadcast_id === $broadcast->id, 404);
        $snapshots->assertEditable($broadcast);

        $recipient->delete();
        $snapshots->refreshCounters($broadcast);

        return redirect()->back()->with('toast', ['message' => 'Recipient dihapus.', 'type' => 'success']);
    }
}
