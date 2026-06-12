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

        foreach ($heads as $index => [$code, $name, $nature, $partyType, $settlements]) {
            $category = TransactionHead::normaliseCategory(null, $name, $nature);

            $head = TransactionHead::updateOrCreate(
                ['head_code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'nature' => TransactionHead::natureFromCategory($category),
                    // Retained only for legacy rule compatibility. New V2
                    // Accounting Rules carry their own Party Type.
                    'default_party_type_id' => $party($partyType),
                    'requires_party' => false,
                    'requires_reference' => false,
                    'payment_method_required' => false,
                    'party_required_mode' => 'No',
                    'is_system_default' => true,
                    'is_user_selectable' => true,
                    'sort_order' => ($index + 1) * 10,
                    'status' => 'Active',
                ]
            );

            $head->settlementTypes()->sync($settlementIds($settlements));
        }
    }
}
