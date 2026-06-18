<x-layouts::accounting title="Other Master Data">
    <div class="hg-page-header">
        <div>
            <div class="hg-page-kicker">Configuration</div>
            <h1>Other Master Data</h1>
        </div>
    </div>

    @if(!empty($companyMasterCards))
        <div class="hg-section-heading">
            <div><h2>Company Setup Masters</h2></div>
        </div>
        <div class="hg-grid hg-grid-2 hg-master-card-grid">
            @foreach($companyMasterCards as $card)
                @php $canViewCard = auth()->user()?->canAccounting($card['permissions'][0]) ?? false; @endphp
                <section class="hg-card hg-master-card">
                    <div class="hg-master-card-icon">{{ $card['icon'] }}</div>
                    <div class="hg-master-card-copy">
                        <div class="hg-master-card-title-row">
                            <h2 class="hg-card-title">{{ $card['title'] }}</h2>
                            <span class="hg-badge off">{{ number_format($card['count']) }}</span>
                        </div>
                        <p>{{ $card['description'] }}</p>
                        <a class="hg-btn hg-btn-primary" href="{{ route($card['route'], $canViewCard ? [] : ['action' => 'add']) }}">
                            {{ $canViewCard ? 'Open' : 'Add' }} {{ $card['title'] }}
                        </a>
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    @if(!empty($configurations) || $canOpenVoucherNumbering)
        <div class="hg-section-heading">
            <div><h2>Accounting Masters</h2></div>
        </div>
        <div class="hg-grid hg-grid-2 hg-master-card-grid">
            @foreach ($configurations as $section => $configuration)
                @php
                    $permissionPrefix = match ($section) {
                        'party-types' => 'party_types',
                        'money-account-types' => 'money_account_types',
                        default => 'transaction_categories',
                    };
                    $canViewSection = auth()->user()?->canAccounting($permissionPrefix.'.view') ?? false;
                @endphp
                <section class="hg-card hg-master-card">
                    <div class="hg-master-card-icon">📁</div>
                    <div class="hg-master-card-copy">
                        <h2 class="hg-card-title">{{ $configuration['title'] }}</h2>
                        <p>{{ $configuration['description'] }}</p>
                        <a class="hg-btn hg-btn-primary" href="{{ route('master.index', ['section' => $section] + ($canViewSection ? [] : ['action' => 'add'])) }}">
                            {{ $canViewSection ? 'Open' : 'Add' }} {{ $configuration['title'] }}
                        </a>
                    </div>
                </section>
            @endforeach

            @if($canOpenVoucherNumbering)
            <section class="hg-card hg-master-card">
                <div class="hg-master-card-icon">🔢</div>
                <div class="hg-master-card-copy">
                    <h2 class="hg-card-title">Voucher Numbering</h2>

                    <a class="hg-btn hg-btn-primary" href="{{ route('master.voucher-sequences.index', auth()->user()?->canAccounting('voucher_numbering.view') ? [] : ['action' => 'add']) }}">
                        {{ auth()->user()?->canAccounting('voucher_numbering.view') ? 'Open' : 'Add' }} Voucher Numbering
                    </a>
                </div>
            </section>
            @endif
        </div>
    @endif
</x-layouts::accounting>
