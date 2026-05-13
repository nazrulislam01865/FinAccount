<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\BusinessType;
use App\Models\Currency;
use App\Models\PartyType;
use App\Models\TimeZone;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Fleet Management', 'FLEET'], ['Trading Business', 'TRADING'], ['Service Business', 'SERVICE'],
            ['Rental Business', 'RENTAL'], ['Manufacturing', 'MFG'], ['Construction', 'CONSTRUCTION'], ['Other', 'OTHER'],
        ] as $i => [$name, $code]) {
            BusinessType::updateOrCreate(['code' => $code], ['name' => $name, 'status' => 'Active', 'sort_order' => $i + 1, 'is_default' => $code === 'FLEET']);
        }

        foreach ([
            ['BDT', 'Bangladeshi Taka', '৳'], ['USD', 'United States Dollar', '$'], ['EUR', 'Euro', '€'],
            ['GBP', 'British Pound', '£'], ['INR', 'Indian Rupee', '₹'],
        ] as $i => [$code, $name, $symbol]) {
            Currency::updateOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol, 'decimal_places' => 2, 'status' => 'Active', 'sort_order' => $i + 1, 'is_default' => $code === 'BDT']);
        }

        foreach ([
            ['Dhaka, Bangladesh', 'GMT+06:00', 'Asia/Dhaka'], ['Dubai, UAE', 'GMT+04:00', 'Asia/Dubai'],
            ['London, UK', 'GMT+00:00', 'Europe/London'], ['New York, USA', 'GMT-05:00', 'America/New_York'],
            ['Singapore', 'GMT+08:00', 'Asia/Singapore'],
        ] as $i => [$name, $offset, $php]) {
            TimeZone::updateOrCreate(['php_timezone' => $php], ['name' => $name, 'utc_offset' => $offset, 'status' => 'Active', 'sort_order' => $i + 1, 'is_default' => $php === 'Asia/Dhaka']);
        }

        foreach ([
            ['Asset', 'ASSET', 'Debit'], ['Liability', 'LIABILITY', 'Credit'], ['Equity', 'EQUITY', 'Credit'],
            ['Income', 'INCOME', 'Credit'], ['Expense', 'EXPENSE', 'Debit'],
        ] as $i => [$name, $code, $normal]) {
            AccountType::updateOrCreate(['code' => $code], ['name' => $name, 'normal_balance' => $normal, 'status' => 'Active', 'sort_order' => $i + 1]);
        }

        foreach ([
            ['Employee', 'EMP'], ['Supplier', 'SUP'], ['Customer', 'CUS'], ['Vendor', 'VENDOR'], ['Landlord', 'LANDLORD'],
            ['Driver', 'DRIVER'], ['Owner', 'OWNER'], ['Tenant', 'TENANT'], ['Other', 'OTHER'],
        ] as $i => [$name, $code]) {
            PartyType::updateOrCreate(['code' => $code], ['name' => $name, 'status' => 'Active', 'sort_order' => $i + 1]);
        }

        foreach ([
            ['BRAC Bank', 'BRAC'], ['Dutch-Bangla Bank', 'DBBL'], ['City Bank', 'CITY'], ['Eastern Bank', 'EBL'],
            ['Islami Bank Bangladesh', 'IBBL'], ['Sonali Bank', 'SONALI'], ['Janata Bank', 'JANATA'], ['Agrani Bank', 'AGRANI'],
            ['bKash', 'BKASH'], ['Nagad', 'NAGAD'], ['Rocket', 'ROCKET'], ['Other', 'OTHER'],
        ] as $i => [$name, $short]) {
            Bank::updateOrCreate(['bank_name' => $name], ['short_name' => $short, 'country' => 'Bangladesh', 'status' => 'Active', 'sort_order' => $i + 1]);
        }
    }
}
