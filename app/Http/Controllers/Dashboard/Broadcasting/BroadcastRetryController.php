<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastDispatchService;
use Illuminate\Http\RedirectResponse;

class BroadcastRetryController extends Controller
{
    public function __invoke(EmailBroadcast $broadcast, BroadcastDispatchService $dispatchService): RedirectResponse
    {
        $this->authorize('retry', $broadcast);

        $count = $dispatchService->retryFailed($broadcast);

        return redirect()->back()->with('toast', [
            'message' => $count > 0 ? $count.' recipient gagal di-retry.' : 'Tidak ada recipient gagal.',
            'type' => 'success',
        ]);
    }
}
