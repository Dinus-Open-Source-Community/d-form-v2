<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $keyType = 'string';

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'registration_start',
        'registration_end',
        'location',
        'quota',
        'banner',
        'price',
        'session',
        'status',
        'category'
    ];

    protected function casts(): array
    {
        return [
            'session' => EventSession::class,
            'status' => EventStatus::class,
            'category' => EventCategory::class,
            'price' => 'decimal:2',
            'quota' => 'integer'
        ];
    }
}
