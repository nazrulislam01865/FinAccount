@php
    $current = $current ?? 1;
    $total = 5;
    $percent = round(($current / $total) * 100);
    $steps = [
        1 => 'Company Setup',
        2 => 'Chart of Accounts',
        3 => 'Cash / Bank Setup',
        4 => 'Party / Person Setup',
        5 => 'Transaction Head Setup',
    ];
@endphp
<div class="card progress-card">
    <h3>Setup Progress</h3>
    <div class="progress-main">
        <div class="ring" style="--progress: {{ $percent }}%"><div class="ring-inner">{{ $current }}<span>of {{ $total }}</span></div></div>
        <div class="percent">{{ $percent }}%<span>Complete</span></div>
    </div>
    <div class="step-list">
        @foreach($steps as $number => $label)
            <div class="step-row">
                <div class="nav-icon {{ $number < $current ? 'done-dot' : '' }}" style="{{ $number === $current ? 'background:var(--primary);color:#fff' : '' }}">
                    {{ $number < $current ? '✓' : $number }}
                </div>
                <div>
                    <strong>{{ $label }}</strong>
                    <small>{{ $number < $current ? 'Completed' : ($number === $current ? 'In Progress' : 'Pending') }}</small>
                </div>
            </div>
        @endforeach
    </div>
</div>
