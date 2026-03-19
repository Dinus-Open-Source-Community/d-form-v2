<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(EventObserver::class)]
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

    #[Scope]
    public function forListPage(Builder $query): void
    {
        $query->select([
            'id',
            'title',
            'description',
            'price',
            'start_date',
            'end_date',
            'quota',
            'category',
            'session',
            'status',
            'banner',
            'deleted_at'
        ]);
    }
}
