<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\UpdateBroadcastScheduleRequest;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastDispatchService;
use Illuminate\Http\RedirectResponse;

class BroadcastScheduleController extends Controller
{
    public function schedule(
        EmailBroadcast $broadcast,
        BroadcastDispatchService $dispatchService,
    ): RedirectResponse {
        $this->authorize('schedule', $broadcast);

        $dispatchService->schedule($broadcast);

        return redirect()->back()->with('toast', ['message' => 'Broadcast dijadwalkan.', 'type' => 'success']);
    }

    public function update(
        UpdateBroadcastScheduleRequest $request,
        EmailBroadcast $broadcast,
    ): RedirectResponse {
        $this->authorize('schedule', $broadcast);

        abort_unless($broadcast->status === EmailBroadcastStatus::Scheduled, 422, 'Broadcast scheduled hanya dapat mengubah schedule.');

        $broadcast->forceFill(['scheduled_at' => $request->scheduledAt()])->save();

        return redirect()->back()->with('toast', ['message' => 'Jadwal diperbarui.', 'type' => 'success']);
    }
}
