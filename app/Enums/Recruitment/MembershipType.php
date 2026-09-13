<?php

namespace App\Enums\Recruitment;

enum MembershipType: string
{
    case Aa = 'aa';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Aa => 'AA',
            self::Member => 'Member',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
