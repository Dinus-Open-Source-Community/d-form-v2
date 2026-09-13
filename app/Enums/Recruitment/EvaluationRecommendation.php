<?php

namespace App\Enums\Recruitment;

enum EvaluationRecommendation: string
{
    case Recommended = 'recommended';
    case NotRecommended = 'not_recommended';

    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Recommended',
            self::NotRecommended => 'Not Recommended',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
