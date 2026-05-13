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
            ['TH-001', 'Salary Payment', 'Payment', 'Employee', ['Cash', 'Bank']],
            ['TH-002', 'Salary Due Entry', 'Journal', 'Employee', ['Due']],
            ['TH-003', 'Salary Due Payment', 'Payment', 'Employee', ['Cash', 'Bank']],
            ['TH-004', 'Fuel Expense', 'Expense', 'Supplier', ['Cash', 'Bank', 'Due']],
            ['TH-005', 'Supplier Payment', 'Payment', 'Supplier', ['Cash', 'Bank']],
            ['TH-006', 'Vehicle Rent Income', 'Receipt', 'Customer', ['Cash', 'Bank', 'Due']],
            ['TH-007', 'Customer Payment Received', 'Receipt', 'Customer', ['Cash', 'Bank']],
            ['TH-008', 'Advance Paid', 'Payment', 'Supplier', ['Cash', 'Bank']],
            ['TH-009', 'Advance Received', 'Receipt', 'Customer', ['Cash', 'Bank']],
            ['TH-010', 'Advance Paid Adjustment', 'Journal', 'Supplier', ['Adjustment']],
            ['TH-011', 'Advance Received Adjustment', 'Journal', 'Customer', ['Adjustment']],
            ['TH-012', 'Office Rent Expense', 'Expense', 'Vendor', ['Cash', 'Bank', 'Due']],
        ];

        foreach ($heads as [$code, $name, $nature, $partyType, $settlements]) {
            $head = TransactionHead::updateOrCreate(
                ['head_code' => $code],
                [
                    'name' => $name,
                    'nature' => $nature,
                    'default_party_type_id' => $party($partyType),
                    'requires_party' => true,
                    'requires_reference' => false,
                    'status' => 'Active',
                ]
            );

            $head->settlementTypes()->sync($settlementIds($settlements));
        }
    }
}
