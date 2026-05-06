<?php

namespace App\Enums;

enum FormAnswerReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}

