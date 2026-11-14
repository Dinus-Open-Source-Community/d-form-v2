<?php

namespace App\Console\Commands\FormAnswers;

use App\Models\FormAnswer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Backfill the portable active marker without deleting or changing answer data. */
final class BackfillActiveMarkerCommand extends Command
{
    protected $signature = 'form-answers:backfill-active-marker
        {--chunk=1000 : Number of form_answers rows to scan per batch}
        {--after-id= : Resume after this form_answers UUID}';

    protected $description = 'Backfill is_active for active form answer submissions.';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize < 1) {
            $this->error('The --chunk option must be a positive integer.');

            return self::INVALID;
        }

        $afterId = $this->option('after-id');
        $afterId = is_string($afterId) && $afterId !== '' ? $afterId : null;
        $scanned = 0;
        $changed = 0;
        $lastId = $afterId;

        $query = FormAnswer::query()
            ->select([
                'id',
                'is_active',
                'review_status',
                'registration_role',
                'member_confirmation_status',
            ])
            ->when($afterId !== null, function (Builder $builder) use ($afterId): void {
                $builder->where('id', '>', $afterId);
            })
            ->orderBy('id');

        $query->chunkById($chunkSize, function (Collection $answers) use (&$scanned, &$changed, &$lastId): void {
            $activeIds = [];
            $inactiveIds = [];

            foreach ($answers as $answer) {
                if (! $answer instanceof FormAnswer) {
                    continue;
                }

                if ($answer->isActiveSubmission()) {
                    $activeIds[] = (string) $answer->id;
                } else {
                    $inactiveIds[] = (string) $answer->id;
                }
            }

            $changed += $this->setMarker($activeIds, 1);
            $changed += $this->setMarker($inactiveIds, null);
            $scanned += $answers->count();

            $lastAnswer = $answers->last();
            if ($lastAnswer instanceof FormAnswer) {
                $lastId = (string) $lastAnswer->id;
            }
        });

        $this->line('Rows scanned: '.$scanned);
        $this->line('Rows changed: '.$changed);
        $this->line('Last ID: '.($lastId ?? 'none'));

        return self::SUCCESS;
    }

    /**
     *
     * Update only the marker column for one batch; all other form answer data is untouched.
     *
     * @param  list<string>  $answerIds
     *
     */
    private function setMarker(array $answerIds, ?int $marker): int
    {
        if ($answerIds === []) {
            return 0;
        }

        $query = DB::table('form_answers')->whereIn('id', $answerIds);

        if ($marker === 1) {
            $query->where(function (QueryBuilder $builder): void {
                $builder->whereNull('is_active')->orWhere('is_active', '!=', 1);
            });
        } else {
            $query->whereNotNull('is_active');
        }

        return $query->update(['is_active' => $marker]);
    }
}
