<?php

namespace App\Enums;

enum EmailLogStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
