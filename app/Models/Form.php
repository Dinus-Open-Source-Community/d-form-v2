<?php

namespace App\Models;

use App\Enums\EventFormVisibility;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'closed_at',
        'visible_for',
        'event_id'
    ];

    public function casts(): array
    {
        return [
            'visible_for' => AsEnumCollection::of(EventFormVisibility::class)
        ];
    }
}
