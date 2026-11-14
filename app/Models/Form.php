<?php

namespace App\Models;

use App\Enums\EventFormVisibility;
use App\Enums\FormPurpose;
use App\Enums\FormStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use HasFactory;
    use SoftDeletes;
    use HasUuids;
    /** Kolom saat form di-resolve dari route (`{form}`) — eksplisit, bukan `select *`. */
    public const ROUTE_BINDING_COLUMNS = [
        'id', 'title', 'description', 'success_content', 'visible_for', 'closed_at',
        'banner_url', 'banner_caption', 'metadata', 'event_id', 'status', 'created_at',
        'updated_at', 'deleted_at', 'created_by', 'share_slug',
    ];

    /** Resolve route binding `{form}` dengan daftar kolom eksplisit. */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->select(self::ROUTE_BINDING_COLUMNS);
    }

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
        'status',
        'created_by',
        'share_slug',
    ];

    public function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'visible_for' => AsEnumCollection::of(EventFormVisibility::class),
            'metadata' => 'array',
            'status' => FormStatus::class,
        ];
    }

    /** Draft adalah form yang masih disusun penyelenggara: tidak pernah masuk daftar publik/peserta dan hanya boleh dibuka pembuat event. */
    public function isDraft(): bool
    {
        return $this->status === FormStatus::Draft;
    }

    /**
     *
     * Hanya baris published yang boleh dibaca peserta/publik/pelaporan.
     *
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     *
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FormStatus::Published->value);
    }

    /**
     *
     * Daftar form level-admin: published selalu ikut; draft hanya untuk pembuat event.
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     *
     */
    public function scopeVisibleToEventCreator(Builder $query, Event $event, ?User $viewer): Builder
    {
        $viewerId = $viewer?->id;

        return $query->where(function (Builder $scopeQuery) use ($event, $viewerId): void {
            $scopeQuery->where('status', FormStatus::Published->value);

            if (! self::viewerOwnsEvent($event, $viewerId)) {
                return;
            }

            $scopeQuery->orWhere(function (Builder $draftQuery) use ($event, $viewerId): void {
                $draftQuery->where('status', FormStatus::Draft->value);

                if ($event->created_by === null) {
                    $draftQuery->where('created_by', $viewerId);
                }
            });
        });
    }

    /** Boleh tidaknya viewer membuka draft ini (404 di level controller). */
    public function isDraftVisibleTo(Event $event, ?User $viewer): bool
    {
        if (! $this->isDraft()) {
            return true;
        }

        $viewerId = $viewer?->id;

        if (! self::viewerOwnsEvent($event, $viewerId)) {
            return false;
        }

        return $event->created_by !== null || $this->created_by === $viewerId;
    }

    /** Shared ownership predicate for admin lists, draft access, and draft creation. */
    public static function viewerOwnsEvent(Event $event, int|string|null $viewerId): bool
    {
        if ($viewerId === null) {
            return false;
        }

        return $event->created_by === null || $event->created_by === $viewerId;
    }

    /** Forms without an explicit purpose remain registration forms for compatibility. */
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

    /**
     *
     * Form oprec tidak lagi wajib terikat ke Event (event_id nullable).
     *
     * @return BelongsTo<Event, $this>
     *
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     *
     * Form Open Recruitment yang ditandai lewat metadata.oprec.
     *
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     *
     */
    public function scopeOprec(Builder $query): Builder
    {
        return $query->where('metadata->oprec', true);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_id');
    }

    /**
     *
     * @return HasMany<FormAnswer, $this>
     *
     */
    public function formAnswers(): HasMany
    {
        return $this->hasMany(FormAnswer::class, 'form_id');
    }
}
