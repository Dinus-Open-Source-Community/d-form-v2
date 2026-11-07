<?php

namespace App\Enums;

/**
 * Registration mode that drives form submission validation.
 */
enum FormSubmissionMode: string
{
    case Single = 'single';
    case Team = 'team';
    case Bundle = 'bundle';
    case Other = 'other';
}
