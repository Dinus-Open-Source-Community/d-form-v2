<?php

namespace App\Support;

use App\Models\FormField;
use App\Services\Registration\BundleSubmissionRules;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/** Remove password keys before flashing because manual `withInput()` bypasses the handler's `dontFlash` list. */
final class SecretAnswerInput
{
    /** @var list<string> */
    private const BASE_KEYS = ['current_password', 'password', 'password_confirmation'];

    /**
     *
     * @param  Collection<int, FormField>  $fields  field yang ikut divalidasi pada request ini
     * @param  int  $extraMemberSlots  bundle participant slots beyond the leader
     * @return array<string, mixed>
     *
     */
    public static function flashable(Request $request, Collection $fields, int $extraMemberSlots = 0): array
    {
        $input = $request->input();

        Arr::forget($input, self::secretKeys($fields, $extraMemberSlots));

        return $input;
    }

    /**
     *
     * @param  Collection<int, FormField>  $fields
     * @return list<string>
     *
     */
    private static function secretKeys(Collection $fields, int $extraMemberSlots): array
    {
        $keys = self::BASE_KEYS;

        foreach ($fields as $field) {
            $metadata = $field->metadata;
            $subtype = $metadata instanceof Collection
                ? $metadata->get('type')
                : (is_array($metadata) ? ($metadata['type'] ?? null) : null);

            if ($subtype !== 'password') {
                continue;
            }

            $keys[] = $field->name;
            $keys[] = $field->name.'_confirmation';

            for ($index = 0; $index < $extraMemberSlots; $index++) {
                $keys[] = BundleSubmissionRules::slotInputName($field->name, $index);
            }
        }

        return $keys;
    }
}
