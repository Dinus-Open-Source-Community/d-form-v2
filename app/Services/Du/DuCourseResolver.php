<?php

namespace App\Services\Du;

use App\Models\DuCourse;

/** Reads the cached DU catalog to resolve submission and issuance course values without HTTP. */
final class DuCourseResolver
{
    public function __construct(private readonly DuCourseRepository $courses)
    {
    }

    /** Active course uid for a submitted uid or title; null when the value is missing or ambiguous. */
    public function activeUidFor(string $value): ?string
    {
        return $this->activeUidsFor([$value])[$value] ?? null;
    }

    /**
     * Batch form of {@see activeUidFor()} for every submitted slot: one cache query,
     * keyed by the submitted value, null where the value is missing or ambiguous.
     *
     * @param  list<string>  $values
     * @return array<string, ?string>
     */
    public function activeUidsFor(array $values): array
    {
        return array_map(
            static fn (?DuCourse $course): ?string => $course?->uid,
            $this->courses->findUniqueForValues($values, true),
        );
    }

    /** Course uid for a stored uid or title; inactive rows still resolve so old answers can be issued. */
    public function uidForIssuance(string $value): ?string
    {
        return $this->courses->findUniqueByValue($value, false)?->uid;
    }
}
