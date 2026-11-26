<?php

namespace App\Services\Form;

use App\Enums\ReferralDiscountType;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\ReferralCode;
use Illuminate\Support\Collection;

/** Satu-satunya sumber hitungan harga form dan diskon referral. */
final class FormPriceService
{
    /** Harga dasar form: field pertama berurutan yang punya `metadata.price` > 0. */
    public function basePrice(Form $form): ?int
    {
        foreach ($form->formFields()->orderBy('order')->get() as $field) {
            $price = $this->priceOf($field);

            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * @return array{base:int,discount_type:string|null,discount_value:int|null,discount:int,final:int}|null
     */
    public function quote(?int $base, ?ReferralCode $code): ?array
    {
        if ($base === null) {
            return null;
        }

        $type = $code?->discount_type;
        $value = $code?->discount_value;
        $discount = $type instanceof ReferralDiscountType && $value !== null && $value > 0
            ? $this->discount($type, $base, $value)
            : 0;

        return [
            'base' => $base,
            'discount_type' => $type?->value,
            'discount_value' => $value,
            'discount' => $discount,
            'final' => max(0, $base - $discount),
        ];
    }

    /**
     * Tulis snapshot yang sama ke baris leader dan setiap member satu submission.
     *
     * @param  iterable<FormAnswer>  $members
     * @param  array{base:int,discount_type:string|null,discount_value:int|null,discount:int,final:int}|null  $quote
     */
    public function snapshot(FormAnswer $leader, iterable $members, ?array $quote, ?ReferralCode $code): void
    {
        if ($quote === null) {
            return;
        }

        $pricing = [
            ...$quote,
            'referral_code_id' => $code?->id,
            'referral_code' => $code?->code,
        ];

        $leader->pricing = $pricing;
        $leader->save();

        foreach ($members as $member) {
            $member->pricing = $pricing;
            $member->save();
        }
    }

    private function discount(ReferralDiscountType $type, int $base, int $value): int
    {
        $raw = $type === ReferralDiscountType::Percentage
            ? intdiv($base * $value, 100)
            : $value;

        return max(0, min($raw, $base));
    }

    private function priceOf(FormField $field): ?int
    {
        $metadata = $field->metadata;
        $builderType = $metadata instanceof Collection ? $metadata->get('builderType') : ($metadata['builderType'] ?? null);

        // Harga hanya milik tipe `price`; metadata harga pada tipe lain diabaikan.
        if ($builderType !== 'price') {
            return null;
        }

        $raw = $metadata instanceof Collection ? $metadata->get('price') : ($metadata['price'] ?? null);

        if (is_string($raw) && ctype_digit($raw)) {
            $raw = (int) $raw;
        }

        return is_int($raw) && $raw > 0 ? $raw : null;
    }
}
