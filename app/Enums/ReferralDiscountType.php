<?php

namespace App\Enums;

/** Jenis potongan harga pada satu kode referral. */
enum ReferralDiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
