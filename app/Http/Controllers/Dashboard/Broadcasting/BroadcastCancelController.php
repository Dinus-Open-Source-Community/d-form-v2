<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastDispatchService;
use Illuminate\Http\RedirectResponse;

class BroadcastCancelController extends Controller
{
    public function __invoke(EmailBroadcast $broadcast, BroadcastDispatchService $dispatchService): RedirectResponse
    {
        $this->authorize('cancel', $broadcast);

        $dispatchService->cancel($broadcast);

        return redirect()->back()->with('toast', ['message' => 'Broadcast dibatalkan.', 'type' => 'success']);
    }
}
