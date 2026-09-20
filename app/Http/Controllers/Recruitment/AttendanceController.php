<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        return Inertia::render('OpenRecruitment/Attendance', [
            'trackingLoginUrl' => route('recruitment.track.login'),
        ]);
    }
}
