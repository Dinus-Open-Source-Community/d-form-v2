<?php

namespace App\Models;

use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    /** @use HasFactory<\Database\Factories\BroadcastFactory> */
    use HasFactory;
    use HasUuids;

    public const SOURCE_EVENT_PARTICIPANTS = 'event_participants';

    public const SOURCE_RECRUITMENT_APPLICANTS = 'recruitment_applicants';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_EVENT_PARTICIPANTS,
        self::SOURCE_RECRUITMENT_APPLICANTS,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'subject',
        'body_html',
        'body_text',
        'source',
        'event_id',
        'period_id',
        'scheduled_at',
        'send_delay_seconds',
        'status',
        'recipient_snapshot',
        'recipient_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'send_delay_seconds' => 'integer',
            'recipient_snapshot' => 'array',
            'recipient_count' => 'integer',
        ];
    }

    /** Relasi ke event target broadcast peserta. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Relasi ke periode target broadcast pelamar. */
    public function period(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPeriod::class, 'period_id');
    }

    /** Relasi ke pembuat broadcast. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lampiran file broadcast (maks 3, ikut terhapus via cascade). */
    public function attachments(): HasMany
    {
        return $this->hasMany(BroadcastAttachment::class);
    }
}
