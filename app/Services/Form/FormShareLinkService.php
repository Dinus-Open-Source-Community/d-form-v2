<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormShareLinkService
{
    private const GENERATED_ALIAS_LENGTH = 12;

    private const MAX_GENERATION_ATTEMPTS = 32;

    private const DUPLICATE_ALIAS_MESSAGE = 'This share alias is already in use.';

    /**
     * Persist a requested alias or generate an available one atomically.
     *
     * Replacing an alias updates the form once, which releases the previous
     * value for another form as part of the same transaction.
     *
     * @throws ValidationException when a requested alias is already reserved
     *                             by another form or a uniqueness race occurs
     */
    public function upsert(Form $form, ?string $requestedAlias): string
    {
        $alias = $this->normalizeRequestedAlias($requestedAlias);

        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $candidate = $alias ?? $this->generateAlias();

            try {
                $persistedAlias = DB::transaction(function () use ($candidate, $form, $alias): ?string {
                    $alreadyUsed = Form::query()
                        ->where('share_slug', $candidate)
                        ->where('id', '<>', $form->getKey())
                        ->exists();

                    if ($alreadyUsed) {
                        if ($alias !== null) {
                            throw ValidationException::withMessages([
                                'alias' => self::DUPLICATE_ALIAS_MESSAGE,
                            ]);
                        }

                        return null;
                    }

                    $form->update(['share_slug' => $candidate]);

                    return $candidate;
                });
            } catch (QueryException $exception) {
                if (! UniqueConstraintViolation::isViolation($exception)) {
                    throw $exception;
                }

                if ($alias !== null) {
                    throw ValidationException::withMessages([
                        'alias' => self::DUPLICATE_ALIAS_MESSAGE,
                    ]);
                }

                continue;
            }

            if ($persistedAlias !== null) {
                return $persistedAlias;
            }
        }

        throw ValidationException::withMessages([
            'alias' => 'Unable to generate an available share alias.',
        ]);
    }

    private function normalizeRequestedAlias(?string $requestedAlias): ?string
    {
        if ($requestedAlias === null || trim($requestedAlias) === '') {
            return null;
        }

        return $requestedAlias;
    }

    private function generateAlias(): string
    {
        return Str::lower(Str::random(self::GENERATED_ALIAS_LENGTH));
    }
}
