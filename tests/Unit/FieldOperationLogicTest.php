<?php

namespace Tests\Unit;

use Tests\TestCase;

class FieldOperationLogicTest extends TestCase
{
    public function test_logic()
    {
        $dataset = collect([
            [
                'id' => '0001',
                'title' => 'DForm',
                'description' => 'alamak'
            ],
            [
                'id' => '0010',
                'title' => 'OOTS',
                'description' => 'alamak oots'
            ],
            [
                'id' => '0011',
                'title' => 'DU',
                'description' => 'kelas dulu'
            ],
            [
                'id' => '0100',
                'title' => 'RP',
                'description' => 'izin'
            ],
        ])->keyBy('id');

        $newFields = $dataset
            ->except('0010')
            ->replace([
                '0011' => [
                    'id' => '0011',
                    'title' => 'DU',
                    'description' => 'bolos dulu ga sih'
                ]
            ])
            ->values()
            ->push([
                'id' => '0101',
                'title' => 'recruitment',
                'description' => "welcome member baru"
            ])
            ->keyBy('id');

        // delete scenario
        $idsToDelete = $dataset->diffKeys($newFields)->keys();
        $this->assertEquals(['0010'], $idsToDelete->toArray()); // -> true

        // upsert scenario
        $idsToIgnore = [];
        $idsToUpdate = [];
        $idsToInsert = [];
        $newFields->each(function ($field, $id) use ($dataset, &$idsToInsert, &$idsToUpdate, &$idsToIgnore) {
            if ($dataset->has($id)) {
                if ($dataset->get($id) === $field) {
                    return $idsToIgnore[] = $id;
                }

                $idsToUpdate[] = $id; // some db transaction
            } else {
                $idsToInsert[] = $id; // some db transation
            }
        });

        $this->assertEquals(['0001', '0100'], $idsToIgnore); // -> true
        $this->assertEquals(['0011'], $idsToUpdate); // -> true
        $this->assertEquals(['0101'], $idsToInsert); // -> true
    }
}
