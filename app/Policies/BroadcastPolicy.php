<?php

namespace App\Policies;

use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;

class BroadcastPolicy
{
    /** Akses halaman hub: izin global atau admin salah satu konteks. */
    public function create(User $user): bool
    {
        return $this->hasGlobalSend($user)
            || $user->can('events.view')
            || $user->can('recruitment.periods.view');
    }

    /** Izin kirim ke event: global lintas-event atau admin event tersebut. */
    public function sendToEvent(User $user, Event $event): bool
    {
        return $this->hasGlobalSend($user) || $user->can('view', $event);
    }

    /** Izin kirim ke periode: global lintas-event atau admin periode tersebut. */
    public function sendToPeriod(User $user, RecruitmentPeriod $period): bool
    {
        return $this->hasGlobalSend($user) || $user->can('view', $period);
    }

    /** Ubah konten: izin konteks + hanya saat draft/scheduled. */
    public function update(User $user, Broadcast $broadcast): bool
    {
        return $this->view($user, $broadcast)
            && in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true);
    }

    /** Kirim: izin konteks + hanya saat draft/scheduled. */
    public function send(User $user, Broadcast $broadcast): bool
    {
        return $this->view($user, $broadcast)
            && in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true);
    }

    /** Lihat daftar per periode: lolos view periode itu (owner-scoped). */
    public function viewAnyForPeriod(User $user, RecruitmentPeriod $period): bool
    {
        return $user->can('view', $period);
    }

    /** Lihat broadcast: mengikuti izin konteks yang tersimpan di snapshot target. */
    public function view(User $user, Broadcast $broadcast): bool
    {
        if ($broadcast->event_id !== null && $broadcast->event !== null) {
            return $this->sendToEvent($user, $broadcast->event);
        }

        if ($broadcast->period_id !== null && $broadcast->period !== null) {
            return $this->sendToPeriod($user, $broadcast->period);
        }

        return $this->hasGlobalSend($user);
    }

    /** Gate lintas-event terpisah: hanya pemegang izin global. */
    private function hasGlobalSend(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->can('email-broadcast.send');
    }
}
