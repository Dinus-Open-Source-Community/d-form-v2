<?php

namespace App\Models\Recruitment;

use App\Models\User;
use App\Policies\Recruitment\RecruitmentDivisionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(RecruitmentDivisionPolicy::class)]
class RecruitmentDivision extends Model
{
    /** @use HasFactory<\Database\Factories\Recruitment\RecruitmentDivisionFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function applicationsAsPrimary(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class, 'primary_division_id');
    }

    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recruitment_interviewer_divisions')
            ->withTimestamps();
    }

    public function interviewerAssignments(): HasMany
    {
        return $this->hasMany(RecruitmentInterviewerDivision::class);
    }
}
