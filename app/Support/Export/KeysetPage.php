<?php

namespace App\Support\Export;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ordering contract for {@see KeysetPageIterator}.
 *
 * `sortColumn` is also used as the SQLite/MySQL sort key, `identifierColumn` must
 * be unique so `(sortColumn, identifierColumn)` is a total order. Qualified
 * column names (`form_answers.created_at`) keep joins unambiguous in SQL while
 * `sortAttribute`/`identifierAttribute` are the unqualified model attributes.
 *
 * Passing the same unique column for both fields is supported: the tie predicate
 * then collapses into the plain range predicate.
 *
 * NULL sort values are outside the order, so the iterator filters them out
 * (`whereNotNull`); a caller that must export such legacy rows streams them in a
 * separate pass.
 */
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
