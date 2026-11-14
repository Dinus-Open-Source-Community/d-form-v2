<?php

namespace App\Support\Export;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** Ordering contract for {@see KeysetPageIterator}. */
final readonly class KeysetPage
{
    public readonly string $sortAttribute;

    public readonly string $identifierAttribute;

    public function __construct(
        public string $sortColumn,
        public string $identifierColumn,
        public bool $descending = false,
        public int $pageSize = 500,
    ) {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Keyset page size must be at least 1.');
        }

        if (trim($sortColumn) === '' || trim($identifierColumn) === '') {
            throw new InvalidArgumentException('Keyset sort and identifier columns must not be empty.');
        }

        $this->sortAttribute = Str::afterLast($sortColumn, '.');
        $this->identifierAttribute = Str::afterLast($identifierColumn, '.');
    }
}
