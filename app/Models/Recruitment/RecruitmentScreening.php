<?php

namespace App\Models\Recruitment;

use App\Enums\Recruitment\ScreeningDecision;
use App\Enums\Recruitment\ScreeningReason;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentScreening extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    /**
     * @var array<string, string>
     */
    public const REVISION_SECTIONS = [
        'data_diri' => 'Data diri',
        'divisi' => 'Divisi',
        'cv' => 'CV',
        'portfolio' => 'Portofolio',
    ];

    protected $fillable = [
        'recruitment_application_id',
        'decision',
        'reason',
        'notes',
        'sections',
        'acted_by',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ScreeningDecision::class,
            'reason' => ScreeningReason::class,
            'sections' => 'array',
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @param  list<string>|null  $sections
     * @return list<string>
     */
    public static function revisionSectionLabels(?array $sections): array
    {
        if ($sections === null || $sections === []) {
            return [];
        }

        $labels = [];

        foreach ($sections as $section) {
            $key = (string) $section;

            if (isset(self::REVISION_SECTIONS[$key])) {
                $labels[] = self::REVISION_SECTIONS[$key];
            }
        }

        return $labels;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
