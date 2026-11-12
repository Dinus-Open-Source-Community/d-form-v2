<?php

namespace App\Services\Registration;

use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Support\Collection;

final class BundleSubmissionRules
{
    /**
     * @param  Collection<int, FormField>  $fields
     * @return list<string>
     */
    public static function duplicatableFieldNames(Collection $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (self::fieldIsDisplayOnly($field)) {
                continue;
            }

            $meta = $field->metadata;
            if ($meta instanceof \Illuminate\Support\Collection) {
                $dup = $meta->get('duplicatable');
            } else {
                $md = (array) $meta;
                $dup = $md['duplicatable'] ?? false;
            }

            if (filter_var($dup, FILTER_VALIDATE_BOOLEAN)) {
                $out[] = $field->name;
            }
        }

        return $out;
    }

    public static function slotInputName(string $fieldName, int $slotIndex): string
    {
        return 'bundle__'.$fieldName.'__'.$slotIndex;
    }

    public static function fieldIsDisplayOnly(FormField $field): bool
    {
        return RulesBuilder::isDisplayOnlyField($field);
    }

    /**
     * Rules for bundle fill: base field names (leader) plus one validated input per duplicatable
     * field for each extra participant slot.
     *
     * @param  Collection<int, FormField>  $fields
     * @return array<string, array<int, string|\App\Rules\SafeUploadFile>>
     */
    public static function buildForFill(Collection $fields, int $extraMemberSlots, bool $requireScalarString = true): array
    {
        $raw = RulesBuilder::extractRulesFromFields($fields);
        $laravel = RulesBuilder::build($raw, $requireScalarString);

        if ($extraMemberSlots < 1) {
            return $laravel;
        }

        $duplicatable = self::duplicatableFieldNames($fields);

        foreach ($duplicatable as $name) {
            if (! isset($raw[$name])) {
                continue;
            }

            for ($i = 0; $i < $extraMemberSlots; $i++) {
                $slotKey = self::slotInputName($name, $i);
                $slotBuilt = RulesBuilder::build([$slotKey => $raw[$name]], $requireScalarString);
                foreach ($slotBuilt as $k => $rules) {
                    $laravel[$k] = $rules;
                }
            }
        }

        return $laravel;
    }
}
