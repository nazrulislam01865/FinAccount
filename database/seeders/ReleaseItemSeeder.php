<?php

namespace Database\Seeders;

use App\Models\ReleaseItem;
use Illuminate\Database\Seeder;

class ReleaseItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['release_date' => '2026-05-20', 'module' => 'Accounting Setup', 'ui_function' => 'UI + Function', 'item_type' => 'New Feature', 'task' => 'Accounting Rule Setup screen added', 'note' => 'Transaction Head setup with posting nature, settlement type, party requirement, and live Dr/Cr preview.', 'user_impact' => 'Admin can configure smart transaction rules without manually setting debit and credit for every transaction.', 'released_by' => 'Aminul / Frontend Team', 'release_version' => 'Major', 'status' => 'Released'],
            ['release_date' => '2026-05-20', 'module' => 'Transactions', 'ui_function' => 'Function', 'item_type' => 'Enhancement', 'task' => 'Auto journal generation from Transaction Head', 'note' => 'Backend-ready logic supported for Money In, Money Out, Credit Sale, Credit Purchase, Collection, Payment, and Transfer.', 'user_impact' => 'User enters simple business data; system prepares ledger debit and credit automatically.', 'released_by' => 'Backend Team', 'release_version' => 'Major', 'status' => 'Released'],
            ['release_date' => '2026-05-20', 'module' => 'Reports', 'ui_function' => 'UI', 'item_type' => 'Report', 'task' => 'Trial Balance report page designed', 'note' => 'Added filter panel, debit/credit summary cards, grouped ledger table, and balance validation.', 'user_impact' => 'Accountant can quickly verify total debit and credit position before preparing statements.', 'released_by' => 'Frontend Team', 'release_version' => 'Minor', 'status' => 'Released'],
            ['release_date' => '2026-05-20', 'module' => 'Reports', 'ui_function' => 'UI', 'item_type' => 'Report', 'task' => 'Income Statement report page designed', 'note' => 'Added revenue, cost of sales, expenses, gross profit, net profit, and YTD columns.', 'user_impact' => 'Business owner can see monthly and year-to-date business performance from posted entries.', 'released_by' => 'Frontend Team', 'release_version' => 'Minor', 'status' => 'Released'],
            ['release_date' => '2026-05-20', 'module' => 'Cash & Bank', 'ui_function' => 'UI + Function', 'item_type' => 'Report', 'task' => 'Cash / Bank Book report revised', 'note' => 'Added account-wise filter, opening balance, inflow, outflow, closing balance, and running balance table.', 'user_impact' => 'Management can check real cash and bank movement from one clear report.', 'released_by' => 'Frontend Team', 'release_version' => 'Minor', 'status' => 'Released'],
            ['release_date' => '2026-05-19', 'module' => 'Transactions', 'ui_function' => 'UI', 'item_type' => 'Enhancement', 'task' => 'Transaction entry page improved', 'note' => 'Added transaction summary, generated ledger preview, party effect, and cash/bank effect cards.', 'user_impact' => 'User can understand the accounting impact before posting the transaction.', 'released_by' => 'Frontend Team', 'release_version' => 'Minor', 'status' => 'Released'],
            ['release_date' => '2026-05-19', 'module' => 'System', 'ui_function' => 'Function', 'item_type' => 'Bug Fix', 'task' => 'Negative due payable display corrected', 'note' => 'Adjusted summary card calculation and label behavior for payable and receivable balances.', 'user_impact' => 'Dashboard balance numbers are easier to understand and less confusing for normal users.', 'released_by' => 'Backend Team', 'release_version' => 'Hotfix', 'status' => 'Released'],
            ['release_date' => '2026-05-18', 'module' => 'Accounting Setup', 'ui_function' => 'Function', 'item_type' => 'Configuration', 'task' => 'Voucher numbering setup added', 'note' => 'Payment, receipt, journal, contra, purchase, and sales voucher prefixes are now configurable.', 'user_impact' => 'Admin can maintain clean voucher numbering based on business need.', 'released_by' => 'Aminul / Product', 'release_version' => 'Minor', 'status' => 'Released'],
            ['release_date' => '2026-05-18', 'module' => 'Reports', 'ui_function' => 'UI', 'item_type' => 'Enhancement', 'task' => 'Report export buttons added', 'note' => 'Print, PDF, and CSV action buttons added to report page headers.', 'user_impact' => 'Users can share and store reports more easily.', 'released_by' => 'Frontend Team', 'release_version' => 'Minor', 'status' => 'Released'],
            ['release_date' => '2026-05-17', 'module' => 'Cash & Bank', 'ui_function' => 'Function', 'item_type' => 'Bug Fix', 'task' => 'Internal transfer duplicate effect corrected', 'note' => 'Cash-to-bank transfer now creates one debit and one credit without inflating total movement.', 'user_impact' => 'Cash and bank book becomes more reliable for internal fund transfer tracking.', 'released_by' => 'Backend Team', 'release_version' => 'Hotfix', 'status' => 'Released'],
        ];

        foreach ($items as $item) {
            ReleaseItem::updateOrCreate(
                [
                    'release_date' => $item['release_date'],
                    'task' => $item['task'],
                ],
                $item
            );
        }
    }
}
