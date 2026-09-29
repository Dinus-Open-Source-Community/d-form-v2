<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\StoreBroadcastAttachmentRequest;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastAttachment;
use App\Services\Broadcasting\BroadcastAttachmentService;
use Illuminate\Http\RedirectResponse;

class BroadcastAttachmentController extends Controller
{
    public function store(
        StoreBroadcastAttachmentRequest $request,
        EmailBroadcast $broadcast,
        BroadcastAttachmentService $service,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);
        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Attachment hanya dapat diubah saat draft.');

        $attachment = $service->store($broadcast, $request->file('file'));

        return redirect()->back()->with('toast', ['message' => 'Attachment '.$attachment->file_name.' ditambahkan.', 'type' => 'success']);
    }

    public function destroy(
        EmailBroadcast $broadcast,
        EmailBroadcastAttachment $attachment,
        BroadcastAttachmentService $service,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);
        abort_unless($attachment->broadcast_id === $broadcast->id, 404);
        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Attachment hanya dapat diubah saat draft.');

        $service->delete($attachment);

        return redirect()->back()->with('toast', ['message' => 'Attachment dihapus.', 'type' => 'success']);
    }
}
