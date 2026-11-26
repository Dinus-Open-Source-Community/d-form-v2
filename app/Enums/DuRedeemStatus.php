<?php

namespace App\Enums;

enum DuRedeemStatus: string
{
    case Pending = 'pending';
    case Issued = 'issued';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
