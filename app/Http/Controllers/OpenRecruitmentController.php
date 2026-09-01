<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller dummy untuk halaman publik OpenRecruitment (frontend mockup M1–3).
 *
 * Data periode/divisi bersifat statis sementara. Saat backend recruitment
 * tersedia, ganti isi method dengan query DB dan props dari service.
 */
class OpenRecruitmentController extends Controller
{
    /** @var array<string, mixed> */
    private array $period = [
        'id' => 'period-2026',
        'name' => 'OpRec 2026',
        'status' => 'open',
        'registrationStart' => '2026-09-01T00:00:00+07:00',
        'registrationEnd' => '2026-09-30T23:59:59+07:00',
        'interviewStart' => '2026-10-05T08:00:00+07:00',
        'interviewEnd' => '2026-10-09T17:00:00+07:00',
        'finalizationEnd' => '2026-10-15T23:59:59+07:00',
        'divisions' => ['programming', 'creative_media', 'network', 'data'],
    ];

    /** @return list<array{id: string, label: string, description: string}> */
    private function divisions(): array
    {
        return [
            ['id' => 'programming', 'label' => 'Pemrograman', 'description' => 'Pengembangan software, web, dan mobile.'],
            ['id' => 'creative_media', 'label' => 'Creative Media', 'description' => 'Desain grafis, video, dan konten kreatif.'],
            ['id' => 'network', 'label' => 'Jaringan', 'description' => 'Infrastruktur jaringan dan server.'],
            ['id' => 'data', 'label' => 'Data', 'description' => 'Data analysis, engineering, dan machine learning.'],
        ];
    }

    public function index(): InertiaResponse
    {
        return Inertia::render('OpenRecruitment', [
            'period' => $this->period,
            'divisions' => $this->divisions(),
            'isOpen' => ($this->period['status'] ?? '') === 'open',
            'registrationUrl' => route('open-recruitment.apply'),
        ]);
    }

    public function apply(): InertiaResponse
    {
        return Inertia::render('OpenRecruitmentForm', [
            'period' => $this->period,
            'divisions' => $this->period['divisions'],
            'submitUrl' => route('open-recruitment.store'),
            'alreadySubmitted' => false,
        ]);
    }

    public function submitted(string $registrationNumber): InertiaResponse
    {
        return Inertia::render('OpenRecruitmentSubmitted', [
            'registrationNumber' => $registrationNumber,
            'confirmationMessage' => 'Pendaftaran berhasil dikirim. Email konfirmasi telah dikirim (dummy).',
        ]);
    }

    public function tracking(): InertiaResponse
    {
        return Inertia::render('ApplicationTracking');
    }

    public function trackingShow(string $registrationNumber): InertiaResponse
    {
        return Inertia::render('ApplicationTracking', [
            'registrationNumber' => $registrationNumber,
        ]);
    }
}
