@php
    $company = \App\Support\PrintedDocumentBrand::company((array) ($company ?? []));
    $resolvedLogoUrl = \App\Support\PrintedDocumentBrand::logoDataUri($company['logo_path'] ?? null)
        ?: ($logoUrl ?? null);

    $documentLines = collect($documentLines ?? []);
    $lineCount = $documentLines->count();
    $densityClass = $lineCount > 10 ? 'ba-u-doc--compact' : ($lineCount > 6 ? 'ba-u-doc--dense' : '');
    $titleLength = function_exists('mb_strlen') ? mb_strlen((string) $documentTitle) : strlen((string) $documentTitle);
    $titleClass = $titleLength > 22 ? 'ba-u-title--xlong' : ($titleLength > 16 ? 'ba-u-title--long' : '');
    $preparedPosition = trim((string) ($preparedByPosition ?? ''));
@endphp

@include('accounting.partials.unified-document-styles')

<section class="ba-u-doc {{ $densityClass }}" aria-label="{{ $documentTitle }}">
    <header class="ba-u-header">
        <div class="ba-u-company">
            <div class="ba-u-logo">
                @if(!empty($resolvedLogoUrl))
                    <img src="{{ $resolvedLogoUrl }}" alt="{{ $company['name'] ?? 'Company' }} logo">
                @else
                    <span>{{ strtoupper(substr((string) ($company['short_name'] ?? $company['name'] ?? 'BA'), 0, 2)) }}</span>
                @endif
            </div>
            <div class="ba-u-company-divider" aria-hidden="true"></div>
            <div class="ba-u-company-copy">
                <h2>{{ $company['name'] ?? 'BASHIR AGRO' }}</h2>
                @if(!empty($company['address']))<p>{{ $company['address'] }}</p>@endif
                @if(!empty($company['phone']))<p>Phone: {{ $company['phone'] }}</p>@endif
                @if(!empty($company['website']))<p>{{ $company['website'] }}</p>@endif
            </div>
        </div>

        <div class="ba-u-meta">
            <h1 class="{{ $titleClass }}">{{ $documentTitle }}</h1>
            @foreach($metaRows as $metaRow)
                <div class="ba-u-meta-row">
                    <span>{{ $metaRow['label'] }}</span><b>:</b><strong>{{ $metaRow['value'] ?: '-' }}</strong>
                </div>
            @endforeach
        </div>
    </header>

    <div class="ba-u-rule"></div>

    <section class="ba-u-party">
        <div class="ba-u-party-title">
            <span class="ba-u-person" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.25"></circle><path d="M6.75 18.2c.8-3.05 2.55-4.55 5.25-4.55s4.45 1.5 5.25 4.55"></path></svg>
            </span>
            <h3>{{ $partySectionTitle }}</h3>
        </div>
        <div class="ba-u-party-grid">
            <span>Name</span><b>:</b><p>{{ $party['name'] ?? '-' }}</p>
            <span>Address</span><b>:</b><p>{{ $party['address'] ?? '-' }}</p>
            <span>Phone / Email</span><b>:</b><p>{{ $partyPhoneEmail ?: '-' }}</p>
            <span>Purpose / Against</span><b>:</b><p>{{ $purpose ?: '-' }}</p>
        </div>
    </section>

    <table class="ba-u-table">
        <thead>
            <tr>
                <th>DESCRIPTION</th>
                <th>REMARKS</th>
                <th>AMOUNT ({{ $currencyLabel }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documentLines as $line)
                @php
                    $rawRemarkLines = $line['remarks_lines'] ?? null;
                    if (!is_array($rawRemarkLines)) {
                        $remarksText = str_replace(["\r\n", "\r"], "\n", trim((string) ($line['remarks'] ?? '-')));
                        $rawRemarkLines = preg_split('/\n+|\s*\|\s*/', $remarksText) ?: [];
                    }
                    $remarksLines = collect($rawRemarkLines)
                        ->flatten()
                        ->map(static fn ($value) => trim((string) $value))
                        ->filter(static fn ($value) => $value !== '')
                        ->values();
                    if ($remarksLines->isEmpty()) {
                        $remarksLines = collect(['-']);
                    }
                @endphp
                <tr>
                    <td>{{ $line['description'] ?? '-' }}</td>
                    <td class="ba-u-remarks">
                        @foreach($remarksLines as $remarkLine)
                            <div class="ba-u-remark-line">{{ $remarkLine }}</div>
                        @endforeach
                    </td>
                    <td class="ba-u-right">{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="ba-u-lower">
        <div class="ba-u-left">
            <h3>AMOUNT IN WORDS</h3>
            <div class="ba-u-words">{{ $amountInWords }}</div>

            <h3>NOTES</h3>
            <div class="ba-u-notes">{{ $notes ?? 'Thank you for your business.' }}</div>
        </div>

        <div class="ba-u-right-stack">
            <table class="ba-u-summary">
                @foreach($summaryRows as $summaryRow)
                    <tr class="{{ !empty($summaryRow['total']) ? 'ba-u-total' : '' }}">
                        <td>{{ $summaryRow['label'] }}</td>
                        <td>{{ $summaryRow['display'] ?? number_format((float) ($summaryRow['amount'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </table>

            <div class="ba-u-prepared">
                <h3>PREPARED BY</h3>
                <div class="ba-u-prep-row"><span>Name</span><b>:</b><p>{{ $preparedByName }}</p></div>
                @if($preparedPosition !== '')
                    <div class="ba-u-prep-row"><span>Position</span><b>:</b><p>{{ $preparedPosition }}</p></div>
                @endif
                <div class="ba-u-prep-row"><span>Date</span><b>:</b><p>{{ $preparedDate }}</p></div>
                <div class="ba-u-sign-area">
                    <span class="ba-u-sign-name">{{ $preparedByName }}</span>
                    <div class="ba-u-sign-line"></div>
                    <strong>DIGITAL SIGNATURE</strong>
                </div>
            </div>
        </div>
    </section>

    <footer class="ba-u-footer">
        <span class="ba-u-check" aria-hidden="true">✓</span>
        <em>This document is electronically generated and may not require a physical signature.</em>
    </footer>
</section>
