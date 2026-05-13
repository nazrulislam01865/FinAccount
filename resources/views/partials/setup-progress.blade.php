@php
    $steps = [
        1 => 'Company Setup',
        2 => 'Chart of Accounts',
        3 => 'Cash / Bank Setup',
        4 => 'Party / Person Setup',
        5 => 'Transaction Head Setup',
        6 => 'Ledger Mapping',
        7 => 'Opening Balance Setup',
        8 => 'Voucher Numbering',
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
        @foreach($steps as $number => $label)
            @php
                $state = $number < $current ? 'done' : ($number === $current ? 'current' : 'pending');
            @endphp

            <div class="setup-progress-step {{ $state }}">
                <div class="setup-progress-dot">
                    {{ $number < $current ? '✓' : $number }}
                </div>

                <div class="setup-progress-copy">
                    <strong>{{ $label }}</strong>
                    <span>{{ $number < $current ? 'Completed' : ($number === $current ? 'In Progress' : 'Pending') }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
