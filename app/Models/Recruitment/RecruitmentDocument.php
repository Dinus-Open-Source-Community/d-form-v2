<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentDocument extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recruitment_application_id',
        'cv_path',
        'cv_original_name',
        'cv_mime',
        'cv_size_bytes',
        'portfolio_type',
        'portfolio_url',
        'portfolio_path',
        'portfolio_original_name',
        'portfolio_mime',
        'portfolio_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'cv_size_bytes' => 'integer',
            'portfolio_size_bytes' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }
}
