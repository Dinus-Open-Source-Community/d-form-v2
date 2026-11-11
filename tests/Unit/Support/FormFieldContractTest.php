<?php

namespace Tests\Unit\Support;

use App\Services\Form\RulesBuilder;
use App\Support\FormFieldTypeMapping;
use Tests\TestCase;

/**
 * Shared client/server contract matrix for builder field types. The same
 * fixture is asserted by `resources/js/lib/__tests__/formFieldContract.test.ts`
 * so neither side can drift silently.
 */
class FormFieldContractTest extends TestCase
{
    /**
     * @return array{
     *     builderTypes: array<string, array{displayOnly: bool, apiType: string}>,
     *     apiTypes: list<string>
     * }
     */
    private function contract(): array
    {
        $contents = file_get_contents(base_path('tests/fixtures/form-field-contract.json'));
        $this->assertIsString($contents);

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function test_server_display_only_types_match_the_shared_contract(): void
    {
        $contract = $this->contract();

        $expected = array_keys(array_filter(
            $contract['builderTypes'],
            static fn (array $row): bool => $row['displayOnly'],
        ));
        $actual = RulesBuilder::DISPLAY_ONLY_BUILDER_TYPES;

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_server_api_types_match_the_shared_contract(): void
    {
        $contract = $this->contract();

        $expected = $contract['apiTypes'];
        $actual = FormFieldTypeMapping::API_TYPES;

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);

        $usedByBuilderTypes = array_values(array_unique(array_column($contract['builderTypes'], 'apiType')));
        sort($usedByBuilderTypes);

        $this->assertSame($expected, $usedByBuilderTypes);
    }

    public function test_every_display_only_builder_type_is_declared_in_the_contract(): void
    {
        $contract = $this->contract();

        foreach (RulesBuilder::DISPLAY_ONLY_BUILDER_TYPES as $builderType) {
            $this->assertArrayHasKey($builderType, $contract['builderTypes']);
            $this->assertTrue($contract['builderTypes'][$builderType]['displayOnly']);
        }
    }
}
