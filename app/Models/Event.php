<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;
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
        'registered_count',
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
            'quota' => 'integer',
            'registered_count' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->where($field ?? $this->getRouteKeyName(), $value);

        if (request()->routeIs('dashboard.events.*')) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
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
            'registered_count',
            'category',
            'session',
            'status',
            'banner',
            'deleted_at'
        ]);
    }
}
