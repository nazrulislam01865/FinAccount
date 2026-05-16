@php
    use Illuminate\Support\Facades\Route;

    $steps = [
        1 => ['label' => 'Company Setup', 'route' => 'setup.company'],
        2 => ['label' => 'Chart of Accounts', 'route' => 'setup.chart-of-accounts'],
        3 => ['label' => 'Cash / Bank Setup', 'route' => 'setup.cash-bank-accounts'],
        4 => ['label' => 'Party / Person Setup', 'route' => 'setup.parties'],
        5 => ['label' => 'Transaction Head Setup', 'route' => 'setup.transaction-heads'],
        6 => ['label' => 'Ledger Mapping', 'route' => 'setup.ledger-mapping'],
        7 => ['label' => 'Opening Balance Setup', 'route' => 'setup.opening-balances'],
        8 => ['label' => 'Voucher Numbering', 'route' => 'setup.voucher-numbering'],
    ];

    $current = (int) ($current ?? 1);
    $total = count($steps);
    $current = max(1, min($current, $total));
    $percent = round(($current / $total) * 100);
@endphp

<div class="card setup-progress-horizontal" aria-label="Setup Progress">
    <div class="setup-progress-head">
        <div>
            <span class="section-label">Setup Progress</span>
            <h3>{{ $percent }}% Complete</h3>
        </div>

        <div class="setup-progress-count">
            Step {{ $current }} of {{ $total }}
        </div>
    </div>

    <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
        <div class="progress-fill" style="width: {{ $percent }}%"></div>
    </div>

    <div class="setup-progress-steps">
        @foreach($steps as $number => $step)
            @php
                $label = $step['label'];
                $route = $step['route'];
                $url = Route::has($route) ? route($route) : '#';
                $state = $number < $current ? 'done' : ($number === $current ? 'current' : 'pending');
                $status = $number < $current ? 'Completed' : ($number === $current ? 'In Progress' : 'Pending');
            @endphp

            <a
                href="{{ $url }}"
                class="setup-progress-step {{ $state }}"
                @if($number === $current) aria-current="page" @endif
                title="Go to {{ $label }}"
            >
                <div class="setup-progress-dot">
                    {{ $number < $current ? '✓' : $number }}
                </div>

                <div class="setup-progress-copy">
                    <strong>{{ $label }}</strong>
                    <span>{{ $status }}</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
