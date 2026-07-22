<?php

namespace App\Models;

use App\Enums\EventFormVisibility;
use App\Enums\FormPurpose;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use HasFactory;
    use SoftDeletes;
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'success_content',
        'closed_at',
        'visible_for',
        'event_id',
        'banner_url',
        'banner_caption',
        'metadata',
    ];

    public function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'visible_for' => AsEnumCollection::of(EventFormVisibility::class),
            'metadata' => 'array',
        ];
    }

    /**
     * Forms without an explicit purpose are treated as registration (backward compatible).
     */
    public function purpose(): FormPurpose
    {
        $raw = is_array($this->metadata) ? ($this->metadata['purpose'] ?? null) : null;

        return $raw === FormPurpose::Other->value
            ? FormPurpose::Other
            : FormPurpose::Registration;
    }

    public function isRegistrationForm(): bool
    {
        return $this->purpose() === FormPurpose::Registration;
    }

    public function requiresFormId(): ?string
    {
        $raw = is_array($this->metadata) ? ($this->metadata['requires_form_id'] ?? null) : null;

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function formFields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FormField::class, 'form_id');
    }

    /**
     * @return HasMany<FormAnswer, $this>
     */
    public function formAnswers(): HasMany
    {
        return $this->hasMany(FormAnswer::class, 'form_id');
    }
}
