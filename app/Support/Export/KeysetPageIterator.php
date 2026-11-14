<?php

namespace App\Support\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/** Keyset pagination requires stable sort values and a unique identifier tie-breaker instead of OFFSET.
 * NULL sort rows are excluded; a non-advancing cursor throws rather than looping forever.
 * @template TModel of Model
 */
final class KeysetPageIterator
{
    /**
     *
     * @param  Builder<TModel>  $query
     *
     * @throws InvalidArgumentException when the base query already has ordering or paging
     *
     */
    public function __construct(private readonly Builder $query)
    {
        $base = $query->getQuery();

        if (! empty($base->orders) || $base->limit !== null || $base->offset !== null) {
            throw new InvalidArgumentException(
                'KeysetPageIterator owns ORDER BY/LIMIT/OFFSET: pass a base query without '
                .'orderBy(), limit() or offset(), otherwise the keyset order and the emitted '
                .'order disagree and pages silently skip or duplicate rows.'
            );
        }
    }

    /**
     *
     * Yield every matching model exactly once, in the configured order.
     * @return \Generator<int, TModel>
     *
     */
    public function iterate(KeysetPage $page): \Generator
    {
        foreach ($this->pages($page) as $rows) {
            foreach ($rows as $row) {
                yield $row;
            }
        }
    }

    /** Yield pages and stop on a short page; a non-advancing full page throws instead of looping.
     * @return \Generator<int, Collection<int, TModel>>
     * @throws RuntimeException when the cursor does not advance
     */
    public function pages(KeysetPage $page): \Generator
    {
        $cursor = null;

        while (true) {
            $rows = $this->fetch($page, $cursor);

            yield $rows;

            if ($rows->count() < $page->pageSize) {
                return;
            }

            $nextCursor = $this->nextCursor($rows->last(), $page);

            if ($nextCursor === $cursor) {
                throw new RuntimeException(sprintf(
                    'Keyset pagination made no progress after a full page of %d rows on %s/%s: '
                    .'the cursor did not advance, so the export would loop forever. Either the sort '
                    .'value loses precision between the database value and the query binding, or the '
                    .'identifier column is not unique.',
                    $page->pageSize,
                    $page->sortColumn,
                    $page->identifierColumn,
                ));
            }

            $cursor = $nextCursor;
        }
    }

    /**
     *
     * @return Collection<int, TModel>
     *
     */
    private function fetch(KeysetPage $page, ?array $cursor): Collection
    {
        $query = $this->query->clone();

        if ($cursor !== null) {
            $this->applyCursor($query, $page, $cursor[0], $cursor[1]);
        }

        return $query
            ->whereNotNull($page->sortColumn)
            ->orderBy($page->sortColumn, $page->descending ? 'desc' : 'asc')
            ->orderBy($page->identifierColumn, $page->descending ? 'desc' : 'asc')
            ->limit($page->pageSize)
            ->get();
    }

    /**
     *
     * @param  Builder<TModel>  $query
     *
     */
    private function applyCursor(Builder $query, KeysetPage $page, string $sortValue, string $identifier): void
    {
        $operator = $page->descending ? '<' : '>';

        $query->where(function (Builder $boundary) use ($page, $sortValue, $identifier, $operator): void {
            $boundary->where($page->sortColumn, $operator, $sortValue)
                ->orWhere(function (Builder $tie) use ($page, $sortValue, $identifier, $operator): void {
                    $tie->where($page->sortColumn, $sortValue)
                        ->where($page->identifierColumn, $operator, $identifier);
                });
        });
    }

    /** Build the next cursor and fail before the next query if required columns are missing.
     * @param  TModel  $last
     * @return array{0: string, 1: string}
     * @throws LogicException when `sortColumn`/`identifierColumn` is not selected
     */
    private function nextCursor(Model $last, KeysetPage $page): array
    {
        $sortValue = $last->getRawOriginal($page->sortAttribute);
        $identifier = $last->getRawOriginal($page->identifierAttribute);

        if ($sortValue === null || $identifier === null || (string) $identifier === '') {
            throw new LogicException(sprintf(
                'Keyset pagination cannot continue: the page query must select %s and %s '
                .'(sort value and unique tie-breaker) so the next cursor can be built.',
                $page->sortColumn,
                $page->identifierColumn,
            ));
        }

        return [(string) $sortValue, (string) $identifier];
    }
}
