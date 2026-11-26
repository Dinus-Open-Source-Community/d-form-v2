<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferralCodeRequest;
use App\Http\Requests\UpdateReferralCodeRequest;
use App\Http\Requests\UpdateReferralSettingsRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\ReferralCode;
use App\Services\Form\ReferralCodeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Pengelolaan kode referral milik satu form; otorisasi ada di Form Request. */
final class FormReferralController extends Controller
{
    public function __construct(private readonly ReferralCodeService $referralCodes)
    {
    }

    public function updateSettings(UpdateReferralSettingsRequest $request, Event $event, Form $form): RedirectResponse
    {
        $this->referralCodes->setEnabled($form, (bool) $request->validated('enabled'));

        return $this->backWithToast('Referral settings updated.');
    }

    public function storeCode(StoreReferralCodeRequest $request, Event $event, Form $form): RedirectResponse
    {
        $this->referralCodes->create($form, (string) $request->user()?->id, $request->validated());

        return $this->backWithToast('Referral code created.');
    }

    public function updateCode(UpdateReferralCodeRequest $request, Event $event, Form $form, ReferralCode $referralCode): RedirectResponse
    {
        abort_unless($referralCode->form_id === $form->id, 404);

        $this->referralCodes->update($referralCode, $request->validated());

        return $this->backWithToast('Referral code updated.');
    }

    public function destroyCode(Event $event, Form $form, ReferralCode $referralCode): RedirectResponse
    {
        abort_unless($referralCode->form_id === $form->id, 404);
        $this->authorize('update', $event);

        $this->referralCodes->delete($referralCode);

        return $this->backWithToast('Referral code deleted.');
    }

    private function backWithToast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
