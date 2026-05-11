<?php

namespace Database\Seeders;

use App\Models\SettlementType;
use Illuminate\Database\Seeder;

class SettlementTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Cash', 'CASH'], ['Bank', 'BANK'], ['Due', 'DUE'],
            ['Advance Paid', 'ADVANCE_PAID'], ['Advance Received', 'ADVANCE_RECEIVED'], ['Advance Adjustment', 'ADVANCE_ADJUSTMENT'],
        ] as $i => [$name, $code]) {
            SettlementType::updateOrCreate(['code' => $code], ['name' => $name, 'status' => 'Active', 'sort_order' => $i + 1]);
        }
    }
}
