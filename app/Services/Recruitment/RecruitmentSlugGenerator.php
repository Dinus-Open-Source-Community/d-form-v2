<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Str;

final class RecruitmentSlugGenerator
{
    public function generateForName(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'oprec-period';
        }

        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?string $ignoreId): bool
    {
        $query = \App\Models\Recruitment\RecruitmentPeriod::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
