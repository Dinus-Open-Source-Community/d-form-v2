<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDatasetRecipient extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'dataset_id',
        'name',
        'email',
    ];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(EmailDataset::class, 'dataset_id');
    }
}
