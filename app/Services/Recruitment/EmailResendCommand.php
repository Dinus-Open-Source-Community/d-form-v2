<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use Illuminate\Http\Request;

final class EmailResendCommand
{
    public function __construct(
        public User $actor,
        public RecruitmentApplication $application,
        public string $type,
        public ?Request $request = null,
    ) {
    }
}
