<?php

namespace App\Services\Du;

use InvalidArgumentException;

final readonly class DuCourseUid
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('DU course UID cannot be empty.');
        }
    }
}
