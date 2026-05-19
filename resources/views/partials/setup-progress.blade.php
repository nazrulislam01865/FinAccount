@php
    use Illuminate\Support\Facades\Route;

    $currentUser = auth()->user();
    $allSteps = [
        1 => ['label' => 'Company Setup', 'route' => 'setup.company'],
        2 => ['label' => 'Chart of Accounts', 'route' => 'setup.chart-of-accounts'],
        3 => ['label' => 'Cash / Bank Setup', 'route' => 'setup.cash-bank-accounts'],
        4 => ['label' => 'Party / Person Setup', 'route' => 'setup.parties'],
        5 => ['label' => 'Transaction Head Setup', 'route' => 'setup.transaction-heads'],
        6 => ['label' => 'Ledger Mapping', 'route' => 'setup.ledger-mapping'],
        7 => ['label' => 'Opening Balance Setup', 'route' => 'setup.opening-balances'],
        8 => ['label' => 'Voucher Numbering', 'route' => 'setup.voucher-numbering'],
    ];

    $steps = collect($allSteps)
        ->filter(fn ($step) => Route::has($step['route']) && ($currentUser?->canViewRoute($step['route']) ?? false))
        ->all();

    $current = (int) ($current ?? 1);
    $visibleNumbers = array_keys($steps);
    $total = max(1, count($steps));
    $position = array_search($current, $visibleNumbers, true);
    $visibleCurrent = $position === false ? 1 : $position + 1;
    $percent = round(($visibleCurrent / $total) * 100);
@endphp

@if(count($steps) > 0)
<div class="card setup-progress-horizontal" aria-label="Setup Progress">
    <div class="setup-progress-head">
        <div>
            <span class="section-label">Setup Progress</span>
            <h3>{{ $percent }}% Complete</h3>
        </div>

        <div class="setup-progress-count">
            Step {{ $visibleCurrent }} of {{ $total }}
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
                $url = route($route);
                $stepPosition = array_search($number, $visibleNumbers, true) + 1;
                $state = $stepPosition < $visibleCurrent ? 'done' : ($stepPosition === $visibleCurrent ? 'current' : 'pending');
                $status = $stepPosition < $visibleCurrent ? 'Completed' : ($stepPosition === $visibleCurrent ? 'In Progress' : 'Pending');
            @endphp

            <a
                href="{{ $url }}"
                class="setup-progress-step {{ $state }}"
                @if($stepPosition === $visibleCurrent) aria-current="page" @endif
                title="Go to {{ $label }}"
            >
                <div class="setup-progress-dot">
                    {{ $stepPosition < $visibleCurrent ? '✓' : $stepPosition }}
                </div>

                <div class="setup-progress-copy">
                    <strong>{{ $label }}</strong>
                    <span>{{ $status }}</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endif
