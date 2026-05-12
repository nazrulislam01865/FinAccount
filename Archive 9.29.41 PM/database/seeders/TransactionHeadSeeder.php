<?php

namespace Database\Seeders;

use App\Models\PartyType;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Database\Seeder;

class TransactionHeadSeeder extends Seeder
{
    public function run(): void
    {
        $party = fn ($name) => PartyType::where('name', $name)->value('id');
        $settlementIds = fn ($names) => SettlementType::whereIn('name', $names)->pluck('id')->all();
        $heads = [
            ['Salary Payment', 'Payment', 'Employee', ['Cash', 'Bank']],
            ['Fuel Expense', 'Payment', 'Supplier', ['Cash', 'Bank', 'Due']],
            ['Vehicle Maintenance', 'Payment', 'Supplier', ['Cash', 'Bank', 'Due']],
            ['Rent Income', 'Receipt', 'Customer', ['Cash', 'Bank', 'Due']],
            ['Customer Payment Received', 'Receipt', 'Customer', ['Cash', 'Bank', 'Advance Received']],
            ['Supplier Payment', 'Payment', 'Supplier', ['Cash', 'Bank', 'Advance Paid']],
            ['Salary Due Entry', 'Due', 'Employee', ['Due']],
            ['Salary Due Payment', 'Payment', 'Employee', ['Cash', 'Bank']],
            ['Advance Received', 'Advance', 'Customer', ['Cash', 'Bank', 'Advance Received']],
            ['Advance Paid', 'Advance', 'Supplier', ['Cash', 'Bank', 'Advance Paid']],
        ];

        foreach ($heads as [$name, $nature, $partyType, $settlements]) {
            $head = TransactionHead::updateOrCreate(
                ['name' => $name],
                [
                    'nature' => $nature,
                    'default_party_type_id' => $party($partyType),
                    'requires_party' => true,
                    'requires_reference' => $name === 'Salary Due Payment',
                    'status' => 'Active',
                ]
            );
            $head->settlementTypes()->sync($settlementIds($settlements));
        }
    }
}
