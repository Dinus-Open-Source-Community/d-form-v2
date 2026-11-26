<?php

namespace App\Services\Du;

final class DuCourseSyncService
{
    public function __construct(
        private readonly DuCourseCatalog $catalog,
        private readonly DuCourseRepository $repository,
    ) {
    }

    /**
     * Reads the upstream catalog and stores it as the local cache.
     *
     * @return list<DuCourseOption>
     */
    public function sync(): array
    {
        $courses = $this->catalog->activeCourses();
        $this->repository->replace($courses);

        return $courses;
    }

    /**
     * Syncs only when the cache has no active row; returns null when it is already populated.
     *
     * @return list<DuCourseOption>|null
     */
    public function syncIfEmpty(): ?array
    {
        return $this->repository->hasActive() ? null : $this->sync();
    }
}
