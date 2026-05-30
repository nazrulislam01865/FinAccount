<?php

return [
    'defaults' => [
        'meta' => [
            'title' => 'HisebGhor | সহজ হিসাব সফটওয়্যার',
            'description' => 'HisebGhor is a simple accounting system for SMEs in Bangladesh.',
            'default_lang' => 'bn',
        ],
        'theme' => [
            'green' => '#00a86b',
            'green_dark' => '#087a52',
            'green_soft' => '#e9fff5',
            'blue' => '#2563eb',
            'gold' => '#f59e0b',
            'ink' => '#101828',
            'muted' => '#667085',
            'bg' => '#f8fafc',
        ],
        'brand' => [
            'name' => 'HisebGhor',
            'logo_text' => 'হি',
            'tagline' => ['bn' => 'সহজ হিসাব সফটওয়্যার', 'en' => 'Simple Accounting Software'],
        ],
        'nav_links' => [
            ['label' => ['bn' => 'কেন', 'en' => 'Why'], 'href' => '#why'],
            ['label' => ['bn' => 'ফিচার', 'en' => 'Features'], 'href' => '#features'],
            ['label' => ['bn' => 'কার জন্য', 'en' => 'For Whom'], 'href' => '#for'],
            ['label' => ['bn' => 'প্যাকেজ', 'en' => 'Pricing'], 'href' => '#pricing'],
            ['label' => ['bn' => 'প্রশ্ন', 'en' => 'FAQ'], 'href' => '#faq'],
        ],
        'cta' => [
            'primary' => ['label' => ['bn' => 'ডেমো চাই', 'en' => 'Request Demo'], 'href' => '/login'],
            'secondary' => ['label' => ['bn' => 'লগইন', 'en' => 'Login'], 'href' => '/login'],
        ],
        'hero' => [
            'enabled' => true,
            'eyebrow' => ['bn' => '🇧🇩 বাংলাদেশের SME ব্যবসার জন্য তৈরি', 'en' => '🇧🇩 Built for SMEs in Bangladesh'],
            'title' => ['bn' => 'হিসাব রাখুন সহজে। ব্যবসা বুঝুন পরিষ্কারভাবে।', 'en' => 'Track accounts easily. Understand your business clearly.'],
            'highlight' => ['bn' => '', 'en' => ''],
            'subtitle' => [
                'bn' => 'HisebGhor হলো ছোট ও মাঝারি ব্যবসার জন্য সহজ হিসাব সফটওয়্যার। দৈনিক লেনদেন, বাকি, অগ্রিম, নগদ/ব্যাংক, ভাউচার এবং রিপোর্ট—সব এক জায়গায়। জটিল হিসাব না। সহজ কাজ। পরিষ্কার ফলাফল।',
                'en' => 'HisebGhor is a simple accounting system for small and medium businesses. Daily transactions, due, advance, cash/bank, voucher and reports—everything in one place. No complex accounting. Simple work. Clear result.',
            ],
            'buttons' => [
                ['style' => 'primary', 'label' => ['bn' => 'ফ্রি ডেমো বুক করুন', 'en' => 'Book Free Demo'], 'href' => '/login'],
                ['style' => 'outline', 'label' => ['bn' => 'ফিচার দেখুন', 'en' => 'Explore Features'], 'href' => '#features'],
            ],
            'dashboard' => [
                'title' => ['bn' => 'আজকের হিসাব', 'en' => "Today's Accounts"],
                'subtitle' => ['bn' => 'লাইভ ড্যাশবোর্ড', 'en' => 'Live dashboard'],
                'chip' => ['bn' => 'সক্রিয়', 'en' => 'Active'],
                'stats' => [
                    ['label' => ['bn' => 'নগদ', 'en' => 'Cash'], 'value' => '৳ ১,৫০,০০০'],
                    ['label' => ['bn' => 'ব্যাংক', 'en' => 'Bank'], 'value' => '৳ ৮,৫০,০০০'],
                    ['label' => ['bn' => 'বাকি', 'en' => 'Due'], 'value' => '৳ ১,২৫,০০০'],
                ],
                'rows' => [
                    ['name' => ['bn' => 'বেতন প্রদান', 'en' => 'Salary Payment'], 'debit' => '৳ ১০,০০০', 'credit' => '৳ ১০,০০০'],
                    ['name' => ['bn' => 'ভাড়া আয়', 'en' => 'Rent Income'], 'debit' => '৳ ৫০,০০০', 'credit' => '৳ ৫০,০০০'],
                    ['name' => ['bn' => 'সাপ্লায়ার বাকি', 'en' => 'Supplier Due'], 'debit' => '৳ ২৫,০০০', 'credit' => '৳ ২৫,০০০'],
                ],
            ],
        ],
        'trust_items' => [
            ['bn' => 'SME-friendly', 'en' => 'SME-friendly'],
            ['bn' => 'বাংলা + English', 'en' => 'Bangla + English'],
            ['bn' => 'সহজ রিপোর্ট', 'en' => 'Easy reports'],
        ],
        'why' => [
            'enabled' => true,
            'mini' => ['bn' => 'কেন HisebGhor', 'en' => 'Why HisebGhor'],
            'title' => ['bn' => 'অনেক SME হিসাব রাখে, কিন্তু বুঝতে পারে না আসল অবস্থা।', 'en' => 'Many SMEs keep accounts, but cannot see the real picture.'],
            'subtitle' => ['bn' => 'খাতা, Excel, WhatsApp নোট, ব্যাংক SMS—সব জায়গায় হিসাব ছড়িয়ে থাকে। HisebGhor এগুলোকে সহজভাবে এক জায়গায় আনে।', 'en' => 'Notebook, Excel, WhatsApp notes, bank SMS—business records stay scattered. HisebGhor brings them into one simple place.'],
        ],
        'why_cards' => [
            ['icon' => '৳', 'title' => ['bn' => 'সহজ লেনদেন', 'en' => 'Easy Transactions'], 'body' => ['bn' => 'শুধু হেড, ব্যক্তি/পার্টি, পরিমাণ, নগদ/ব্যাংক/বাকি/অগ্রিম নির্বাচন করুন। লেজার সিস্টেম তৈরি করবে।', 'en' => 'Enter head, party, amount, and cash/bank/due/advance. The system prepares the ledger.']],
            ['icon' => '📌', 'title' => ['bn' => 'বাকি ও অগ্রিম ট্র্যাকিং', 'en' => 'Due & Advance Tracking'], 'body' => ['bn' => 'কে টাকা পাবে, কার কাছ থেকে টাকা আসবে, কোথায় অগ্রিম আছে—পরিষ্কারভাবে দেখা যাবে।', 'en' => 'See who needs to be paid, who needs to pay you, and where advance balance exists.']],
            ['icon' => '📊', 'title' => ['bn' => 'রিপোর্ট এক ক্লিকে', 'en' => 'Reports in One Click'], 'body' => ['bn' => 'Cash/Bank book, ledger report, transaction list, voucher trail—সব রিপোর্ট সহজ ও পরিষ্কার।', 'en' => 'Cash/bank book, ledger report, transaction list, voucher trail—clear and easy reports.']],
        ],
        'features' => [
            'enabled' => true,
            'mini' => ['bn' => 'স্ক্রিনশট ও ফিচার', 'en' => 'Screenshots & Features'],
            'title' => ['bn' => 'স্ক্রিনগুলো সহজ। কাজের জায়গা পরিষ্কার।', 'en' => 'Simple screens. Clear working space.'],
            'subtitle' => ['bn' => 'ব্যবহারকারী যেন হিসাববিদ না হয়েও লেনদেন করতে পারে—এই ভাবনা থেকে UI তৈরি।', 'en' => 'The UI is designed so a user can enter transactions even without being an accountant.'],
        ],
        'screens' => [
            ['badges' => [['bn' => 'Auto Ledger Preview', 'en' => 'Auto Ledger Preview'], ['bn' => 'Cash / Bank / Due', 'en' => 'Cash / Bank / Due']], 'title' => ['bn' => 'Daily Transaction Entry', 'en' => 'Daily Transaction Entry'], 'body' => ['bn' => 'লেনদেন এন্ট্রি করার সময়ই ডেবিট-ক্রেডিট দেখা যাবে। ভুল কমবে।', 'en' => 'Debit-credit preview appears while entering transaction. It reduces mistakes.']],
            ['badges' => [['bn' => 'Due Payable', 'en' => 'Due Payable'], ['bn' => 'Due Receivable', 'en' => 'Due Receivable']], 'title' => ['bn' => 'Due Management', 'en' => 'Due Management'], 'body' => ['bn' => 'বাকি তৈরি, আংশিক পেমেন্ট, কালেকশন এবং ব্যালেন্স—সব ট্র্যাক হবে।', 'en' => 'Create due, partial payment, collection and balance tracking in one screen.']],
            ['badges' => [['bn' => 'Advance Paid', 'en' => 'Advance Paid'], ['bn' => 'Advance Received', 'en' => 'Advance Received']], 'title' => ['bn' => 'Advance Tracking', 'en' => 'Advance Tracking'], 'body' => ['bn' => 'সাপ্লায়ার, কর্মী বা কাস্টমারের অগ্রিম সহজে দেখা ও অ্যাডজাস্ট করা যাবে।', 'en' => 'Track and adjust supplier, employee or customer advances easily.']],
            ['badges' => [['bn' => 'Ledger Report', 'en' => 'Ledger Report'], ['bn' => 'Cash/Bank Book', 'en' => 'Cash/Bank Book']], 'title' => ['bn' => 'Reports', 'en' => 'Reports'], 'body' => ['bn' => 'লেনদেন, লেজার, ভাউচার ও cash/bank movement দ্রুত দেখা যাবে।', 'en' => 'View transactions, ledger, voucher and cash/bank movement quickly.']],
        ],
        'audience' => [
            'enabled' => true,
            'icon' => '🏪',
            'title' => ['bn' => 'মূলত কার জন্য?', 'en' => 'Who is this for?'],
            'body' => ['bn' => 'HisebGhor তাদের জন্য, যারা ব্যবসার হিসাব বুঝতে চান কিন্তু বড় ERP বা জটিল accounting software দিয়ে শুরু করতে চান না।', 'en' => 'HisebGhor is for businesses that want clarity without starting with a large ERP or complex accounting software.'],
        ],
        'audiences' => [
            ['title' => ['bn' => 'SME ব্যবসা', 'en' => 'SME Businesses'], 'body' => ['bn' => 'দৈনিক আয়-ব্যয়, বাকি, ব্যাংক ব্যালেন্স ট্র্যাকিং।', 'en' => 'Daily income-expense, due and bank balance tracking.']],
            ['title' => ['bn' => 'ট্রেডিং ব্যবসা', 'en' => 'Trading Business'], 'body' => ['bn' => 'সাপ্লায়ার পেমেন্ট, কাস্টমার কালেকশন, advance tracking।', 'en' => 'Supplier payment, customer collection and advance tracking.']],
            ['title' => ['bn' => 'সার্ভিস কোম্পানি', 'en' => 'Service Companies'], 'body' => ['bn' => 'ইনকাম, খরচ, salary due, client receivable।', 'en' => 'Income, expense, salary due and client receivable.']],
            ['title' => ['bn' => 'ছোট টিম', 'en' => 'Small Teams'], 'body' => ['bn' => 'Owner, accountant, manager—সবার জন্য সহজ view।', 'en' => 'Simple view for owner, accountant and manager.']],
        ],
        'pricing' => [
            'enabled' => true,
            'mini' => ['bn' => 'SME-friendly প্যাকেজ', 'en' => 'SME-friendly Packages'],
            'title' => ['bn' => 'সহজ প্যাকেজ। পরিষ্কার খরচ।', 'en' => 'Simple packages. Clear cost.'],
            'subtitle' => ['bn' => 'HisebGhor-এর প্যাকেজগুলো বাংলাদেশের SME ব্যবসার জন্য সাজানো। ১টি কোম্পানি দিয়ে শুরু করুন। দরকার হলে পরে user, report, hosting বা custom feature যোগ করা যাবে।', 'en' => 'HisebGhor packages are designed for SMEs in Bangladesh. Start with 1 company. Users, reports, hosting, or custom features can be added later as needed.'],
        ],
        'packages' => [
            ['name' => ['bn' => 'Basic Cloud', 'en' => 'Basic Cloud'], 'popular' => false, 'tag' => ['bn' => '', 'en' => ''], 'body' => ['bn' => 'খুব ছোট SME, owner-managed business, বা single accountant use-এর জন্য।', 'en' => 'For very small SMEs, owner-managed businesses, or single accountant use.'], 'price' => '৳ 2,500', 'suffix' => ['bn' => '/মাস থেকে', 'en' => '/month starting'], 'features' => [['bn' => '১টি কোম্পানি', 'en' => '1 company'], ['bn' => '১ জন user', 'en' => '1 user'], ['bn' => 'Cash/bank transactions', 'en' => 'Cash/bank transactions'], ['bn' => 'Voucher numbering', 'en' => 'Voucher numbering'], ['bn' => 'Ledger list/report', 'en' => 'Ledger list/report'], ['bn' => 'Cash/bank book', 'en' => 'Cash/bank book'], ['bn' => 'Basic setup support', 'en' => 'Basic setup support']], 'button' => ['style' => 'outline', 'label' => ['bn' => 'Basic নিয়ে কথা বলুন', 'en' => 'Talk about Basic'], 'href' => '/login']],
            ['name' => ['bn' => 'Business Cloud', 'en' => 'Business Cloud'], 'popular' => true, 'tag' => ['bn' => 'SME জনপ্রিয়', 'en' => 'SME Popular'], 'body' => ['bn' => 'যেসব SME-তে বাকি, অগ্রিম এবং financial report দরকার।', 'en' => 'For SMEs that need due, advance, and financial reports.'], 'price' => '৳ 5,000', 'suffix' => ['bn' => '/মাস', 'en' => '/month'], 'features' => [['bn' => '১টি কোম্পানি', 'en' => '1 company'], ['bn' => '৩ জন user পর্যন্ত', 'en' => 'Up to 3 users'], ['bn' => 'Basic Cloud-এর সবকিছু', 'en' => 'Everything in Basic Cloud'], ['bn' => 'Due management', 'en' => 'Due management'], ['bn' => 'Advance management', 'en' => 'Advance management'], ['bn' => 'Trial balance', 'en' => 'Trial balance'], ['bn' => 'Income statement', 'en' => 'Income statement'], ['bn' => 'Balance sheet', 'en' => 'Balance sheet']], 'button' => ['style' => 'primary', 'label' => ['bn' => 'Business ডেমো চাই', 'en' => 'Request Business Demo'], 'href' => '/login']],
            ['name' => ['bn' => 'On-Premise Basic', 'en' => 'On-Premise Basic'], 'popular' => false, 'tag' => ['bn' => '', 'en' => ''], 'body' => ['bn' => 'প্রথমে আমাদের server-এ চালু হবে। ৩ মাস পর client server-এ transfer করা যাবে।', 'en' => 'Starts on our server first. After 3 months, it can be transferred to the client server.'], 'price' => '৳ 15,000', 'suffix' => ['bn' => 'setup', 'en' => 'setup'], 'features' => [['bn' => '১টি কোম্পানি', 'en' => '1 company'], ['bn' => '৩ জন user পর্যন্ত', 'en' => 'Up to 3 users'], ['bn' => 'Basic accounting setup', 'en' => 'Basic accounting setup'], ['bn' => 'Installation ও configuration', 'en' => 'Installation and configuration'], ['bn' => 'Basic user training', 'en' => 'Basic user training'], ['bn' => 'প্রথম ৩ মাস আমাদের server-এ hosted', 'en' => 'First 3 months hosted on our server'], ['bn' => 'প্রথম ৩ মাস support/hosting: ৳8,000', 'en' => 'First 3 months support/hosting: ৳8,000'], ['bn' => '৩ মাস পর client server-এ transfer অথবা আমাদের server-এ ৳5,000/month', 'en' => 'After 3 months: transfer to client server or continue on our server at ৳5,000/month']], 'button' => ['style' => 'outline', 'label' => ['bn' => 'On-Premise নিয়ে কথা বলুন', 'en' => 'Talk about On-Premise'], 'href' => '/login']],
        ],
        'pricing_notes' => [
            ['title' => ['bn' => 'Custom / Enterprise', 'en' => 'Custom / Enterprise'], 'body' => ['bn' => 'Multi-company, multi-branch, approval workflow, audit trail, integration, custom reports বা large corporation-এর জন্য আলাদা proposal দেওয়া হবে।', 'en' => 'For multi-company, multi-branch, approval workflow, audit trail, integration, custom reports, or large corporations, a separate proposal will be provided.'], 'button' => ['label' => ['bn' => 'আলোচনা করুন', 'en' => 'Discuss Requirement'], 'href' => '/login']],
            ['title' => ['bn' => 'মূল্য সম্পর্কিত নোট', 'en' => 'Pricing Note'], 'body' => ['bn' => 'উল্লিখিত মূল্য indicative এবং VAT, Tax, VDS/AIT বা প্রযোজ্য সরকারি চার্জ ব্যতীত। Additional user, custom report, data migration, server transfer বা বিশেষ support আলাদাভাবে হিসাব হতে পারে।', 'en' => 'Prices are indicative and exclusive of VAT, Tax, VDS/AIT, or applicable government charges. Additional users, custom reports, data migration, server transfer, or special support may be charged separately.']],
        ],
        'testimonials_section' => [
            'enabled' => true,
            'mini' => ['bn' => 'কাস্টমারদের কথা', 'en' => 'Testimonials'],
            'title' => ['bn' => 'যারা সহজ হিসাব চান, তাদের জন্য।', 'en' => 'For people who want simple accounting.'],
        ],
        'testimonials' => [
            ['quote' => ['bn' => 'আমাদের আগে বাকি হিসাব Excel আর খাতায় থাকত। এখন কার কাছে কত পাওনা, সহজে দেখা যায়।', 'en' => 'Earlier our dues were split between Excel and notebooks. Now we can see who owes what easily.'], 'name' => 'Rahim Traders', 'role' => ['bn' => 'SME Owner, Dhaka', 'en' => 'SME Owner, Dhaka'], 'avatar' => 'R'],
            ['quote' => ['bn' => 'Daily cash আর bank movement দ্রুত বোঝা যায়। মালিক হিসেবে এটা খুব দরকারি।', 'en' => 'Daily cash and bank movement is easier to understand. As an owner, that is very important.'], 'name' => 'ABC Services', 'role' => ['bn' => 'Service Business', 'en' => 'Service Business'], 'avatar' => 'A'],
            ['quote' => ['bn' => 'Software বড় না। কিন্তু দরকারি কাজগুলো আছে। আমাদের team সহজে ব্যবহার করতে পারে।', 'en' => 'The software is not overloaded. It has the needed features, and our team can use it easily.'], 'name' => 'M Trading', 'role' => ['bn' => 'Trading Business', 'en' => 'Trading Business'], 'avatar' => 'M'],
        ],
        'faq_section' => [
            'enabled' => true,
            'mini' => ['bn' => 'FAQ', 'en' => 'FAQ'],
            'title' => ['bn' => 'সাধারণ প্রশ্ন', 'en' => 'Common Questions'],
        ],
        'faqs' => [
            ['question' => ['bn' => 'HisebGhor কি accounting knowledge ছাড়াও ব্যবহার করা যাবে?', 'en' => 'Can HisebGhor be used without accounting knowledge?'], 'answer' => ['bn' => 'হ্যাঁ। ব্যবহারকারী transaction head, party, amount এবং cash/bank/due/advance নির্বাচন করবে। backend ledger mapping অনুযায়ী debit-credit তৈরি করবে।', 'en' => 'Yes. The user selects transaction head, party, amount and cash/bank/due/advance. The backend creates debit-credit based on ledger mapping.'], 'open' => true],
            ['question' => ['bn' => 'এটা কি ERP?', 'en' => 'Is this an ERP?'], 'answer' => ['bn' => 'না, এটি simple accounting management system। SME ব্যবসার basic accounting, due, advance, cash/bank ও রিপোর্টের জন্য তৈরি।', 'en' => 'No, it is a simple accounting management system. It is built for SME basic accounting, due, advance, cash/bank and reports.']],
            ['question' => ['bn' => 'Bangla এবং English দুই ভাষায় হবে?', 'en' => 'Will it support Bangla and English?'], 'answer' => ['bn' => 'হ্যাঁ, UI Bangla এবং English দুই ভাষাতেই করা যাবে।', 'en' => 'Yes, the UI can be provided in both Bangla and English.']],
            ['question' => ['bn' => 'কাস্টমাইজেশন করা যাবে?', 'en' => 'Can it be customized?'], 'answer' => ['bn' => 'হ্যাঁ। আপনার business process, report format, voucher format এবং approval flow অনুযায়ী কাস্টমাইজ করা যাবে।', 'en' => 'Yes. It can be customized based on your business process, report format, voucher format and approval flow.']],
        ],
        'contact' => [
            'enabled' => true,
            'title' => ['bn' => 'ডেমো দেখতে চান?', 'en' => 'Want to see a demo?'],
            'body' => ['bn' => 'আপনার ব্যবসার ধরন বলুন। আমরা দেখাব HisebGhor কীভাবে আপনার দৈনিক হিসাব, বাকি, অগ্রিম ও রিপোর্ট সহজ করতে পারে।', 'en' => 'Tell us about your business. We will show how HisebGhor can simplify your daily accounts, due, advance and reports.'],
            'phone' => '+8801742110660',
            'email' => 'aminul@itqanconsulting.com',
            'phone_note' => ['bn' => 'কল বা WhatsApp করুন', 'en' => 'Call or WhatsApp'],
            'email_note' => ['bn' => 'ইমেইল করুন', 'en' => 'Send email'],
            'form' => [
                'name' => ['bn' => 'আপনার নাম', 'en' => 'Your name'],
                'business_name' => ['bn' => 'ব্যবসার নাম', 'en' => 'Business name'],
                'mobile' => ['bn' => 'মোবাইল নম্বর', 'en' => 'Mobile number'],
                'email' => ['bn' => 'ইমেইল (ঐচ্ছিক)', 'en' => 'Email (optional)'],
                'message' => ['bn' => 'আপনার ব্যবসার হিসাব নিয়ে কী সমস্যা হচ্ছে?', 'en' => 'What problem are you facing with your business accounts?'],
                'button' => ['bn' => 'ডেমো রিকোয়েস্ট পাঠান', 'en' => 'Send Demo Request'],
                'success' => ['bn' => 'ধন্যবাদ! ডেমো রিকোয়েস্ট গ্রহণ করা হয়েছে।', 'en' => 'Thank you! Demo request received.'],
            ],
        ],
        'footer' => [
            'text' => ['bn' => 'HisebGhor হলো ITQAN Consulting-এর একটি উদ্যোগ।', 'en' => 'HisebGhor is an initiative of ITQAN Consulting.'],
        ],
    ],
];
