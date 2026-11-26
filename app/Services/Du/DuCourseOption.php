<?php

namespace App\Services\Du;

use InvalidArgumentException;

final readonly class DuCourseOption
{
    public function __construct(
        public string $uid,
        public string $title,
        public string $status,
    ) {
        if ($uid === '' || $title === '' || $status === '') {
            throw new InvalidArgumentException('DU course fields cannot be empty.');
        }
    }
}
