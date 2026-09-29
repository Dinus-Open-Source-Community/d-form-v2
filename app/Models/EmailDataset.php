<?php

namespace App\Models;

use App\Enums\EmailDatasetSourceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailDataset extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'source_type',
        'source_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => EmailDatasetSourceType::class,
        ];
    }

    /** @return HasMany<EmailDatasetRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailDatasetRecipient::class, 'dataset_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
