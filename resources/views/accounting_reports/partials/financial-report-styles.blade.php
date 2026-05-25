<style>
    .financial-report-page .report-page-header,
    .financial-report-page .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .financial-report-page .report-page-header {
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .financial-report-page .report-summary-grid {
        display: grid;
        gap: 16px;
        margin-bottom: 18px;
    }

    .financial-report-page .report-summary-grid-six {
        grid-template-columns: repeat(4, minmax(150px, 1fr)) repeat(2, minmax(260px, 1.35fr));
    }

    .financial-report-page .stat-card {
        padding: 18px;
    }

    .financial-report-page .stat-card small,
    .financial-report-page .stat-card span {
        display: block;
        color: var(--muted);
        font-size: 13px;
    }

    .financial-report-page .stat-card small {
        margin-bottom: 8px;
    }

    .financial-report-page .stat-card strong {
        display: block;
        font-size: 24px;
        letter-spacing: -.03em;
    }

    .financial-report-page .stat-card span {
        margin-top: 6px;
    }

    .financial-report-page .report-tone-primary { color: var(--primary); }
    .financial-report-page .report-tone-success { color: #067647; }
    .financial-report-page .report-tone-danger { color: #dc2626; }
    .financial-report-page .report-tone-warning { color: #b54708; }
    .financial-report-page .report-tone-muted { color: var(--muted); }

    .financial-report-page .report-info-card {
        padding: 18px;
    }

    .financial-report-page .report-info-title {
        font-size: 13px;
        font-weight: 900;
        color: #101828;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 12px;
    }

    .financial-report-page .compact-ratio-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        column-gap: 14px;
        row-gap: 9px;
        align-items: center;
    }

    .financial-report-page .compact-ratio-grid span {
        color: var(--muted);
        font-size: 13px;
    }

    .financial-report-page .compact-ratio-grid strong {
        color: #101828;
        font-size: 13px;
        text-align: right;
    }

    .financial-report-page .report-toolbar {
        display: grid;
        gap: 14px;
        padding: 18px;
        align-items: end;
        margin-bottom: 18px;
    }

    .financial-report-page .report-toolbar-seven,
    .financial-report-page .accounting-filter-sequence {
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    }

    .financial-report-page .accounting-filter-sequence > * {
        min-width: 0;
    }

    .financial-report-page .report-toolbar > div:not(.filter-actions),
    .financial-report-page .report-toolbar .field {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        min-width: 0;
    }

    .financial-report-page .date-range-field {
        grid-column: span 2;
        min-width: 0;
    }

    .financial-report-page .date-range-inputs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        min-width: 0;
    }

    .financial-report-page .report-toolbar input,
    .financial-report-page .report-toolbar select,
    .financial-report-page .report-toolbar button,
    .financial-report-page .report-toolbar .button {
        width: 100%;
        height: var(--filter-control-height, 56px);
        min-height: var(--filter-control-height, 56px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .financial-report-page .report-toolbar > .filter-actions {
        display: contents;
    }

    .financial-report-page .filter-actions .button,
    .financial-report-page .filter-actions button {
        width: 100%;
        height: var(--filter-control-height, 56px);
        min-height: var(--filter-control-height, 56px);
        padding: 0 14px;
        white-space: nowrap;
    }

    .financial-report-page .search-field span {
        top: 38px;
    }

    .financial-report-page .report-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 22px;
        align-items: start;
    }

    .financial-report-page .report-grid-full {
        grid-template-columns: minmax(0, 1fr);
    }

    .financial-report-page .table-card {
        overflow: hidden;
    }

    .financial-report-page .table-wrap {
        overflow-x: scroll;
        scrollbar-gutter: stable both-edges;
    }

    .financial-report-page table,
    .financial-report-page .financial-table {
        min-width: 1040px;
    }

    .financial-report-page .income-table {
        min-width: 960px;
    }

    .financial-report-page .amount {
        text-align: right;
        font-weight: 850;
        color: #1d2939;
    }

    .financial-report-page .group-row td {
        background: #f8fafc;
        color: #101828;
        font-weight: 900;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: .045em;
    }

    .financial-report-page .total-row td {
        background: #fbfcfd;
        font-weight: 900;
        color: #101828;
    }

    .financial-report-page .grand-row td {
        background: #eef4ff;
        font-weight: 950;
        color: #101828;
        border-top: 2px solid #dbeafe;
    }

    .financial-report-page .profit-row td {
        background: var(--success-soft);
        font-weight: 950;
        color: #067647;
    }

    .financial-report-page .loss-row td {
        background: var(--danger-soft);
        font-weight: 950;
        color: #b42318;
    }

    .financial-report-page .gross-row td {
        background: var(--primary-soft);
        font-weight: 900;
        color: var(--primary);
    }

    .financial-report-page .report-note {
        margin: 18px 0 0;
        padding: 14px 16px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid var(--line);
        color: var(--muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .financial-report-page .section-label,
    .financial-report-page .strong {
        font-weight: 900;
        color: #101828;
    }

    .financial-report-page .negative { color: #b42318; }
    .financial-report-page .positive { color: #067647; }
    .financial-report-page .print-note { display: none; }

    /* Backward-compatible classes for existing report pages. */
    .financial-report-page .income-summary-grid,
    .financial-report-page .trial-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr)) repeat(2, minmax(260px, 1.35fr));
        gap: 16px;
        margin-bottom: 18px;
    }

    .financial-report-page .income-info-card {
        padding: 18px;
    }

    .financial-report-page .income-info-title {
        font-size: 13px;
        font-weight: 900;
        color: #101828;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 12px;
    }

    .financial-report-page .income-table-full,
    .financial-report-page .trial-table-full {
        grid-template-columns: minmax(0, 1fr);
    }

    @media (max-width: 1320px) {
        .financial-report-page .report-grid {
            grid-template-columns: 1fr;
        }

        .financial-report-page .report-summary-grid-six,
        .financial-report-page .income-summary-grid,
        .financial-report-page .trial-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 880px) {
        .financial-report-page .report-toolbar,
        .financial-report-page .report-toolbar-seven,
        .financial-report-page .date-range-inputs,
        .financial-report-page .report-summary-grid-six,
        .financial-report-page .income-summary-grid,
        .financial-report-page .trial-summary-grid {
            grid-template-columns: 1fr;
        }

        .financial-report-page .date-range-field {
            grid-column: span 1;
        }

        .financial-report-page .report-toolbar > .filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .financial-report-page .quick-actions,
        .financial-report-page .report-page-header {
            display: grid;
        }
    }

    @media print {
        .sidebar,
        .topbar,
        .financial-report-page .report-toolbar,
        .financial-report-page .quick-actions,
        .financial-report-page .right-stack {
            display: none !important;
        }

        .content {
            padding: 0;
        }

        .financial-report-page .card {
            box-shadow: none;
        }

        .financial-report-page .report-grid {
            display: block;
        }

        .financial-report-page .table-wrap {
            overflow: visible;
        }

        .financial-report-page table {
            min-width: 0;
        }

        .financial-report-page .print-note {
            display: block;
            margin-bottom: 14px;
            color: #667085;
        }

        .financial-report-page .page-title {
            border-bottom: 1px solid #ddd;
            padding-bottom: 12px;
        }
    }
</style>
<style>
    .financial-report-page .checkbox-inline {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 12px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: #fff;
        font-weight: 750;
        color: #344054;
    }

    .financial-report-page .checkbox-inline input {
        width: 16px;
        height: 16px;
        min-height: 16px;
        flex: 0 0 auto;
    }

    @media (max-width: 1024px) {
        .financial-report-page .report-summary-grid-six {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .financial-report-page .report-summary-grid-six {
            grid-template-columns: 1fr;
        }
    }
</style>
<style>
    /* Income Statement template alignment */
    .financial-report-page.income-statement-page .income-template-stats {
        grid-template-columns: repeat(4, minmax(170px, 1fr));
    }

    .financial-report-page.income-statement-page .income-template-layout {
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 18px;
    }

    .financial-report-page.income-statement-page .income-template-card {
        overflow: hidden;
    }

    .financial-report-page.income-statement-page .income-statement-template-table {
        min-width: 860px;
    }

    .financial-report-page.income-statement-page .section-row td {
        background: #f8fafc;
        color: #1d2939;
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .financial-report-page.income-statement-page .empty-row td {
        color: var(--muted);
        font-size: 13px;
        font-style: italic;
        background: #fff;
    }

    .financial-report-page.income-statement-page .report-side-stack {
        display: grid;
        gap: 18px;
        align-items: start;
    }

    .financial-report-page.income-statement-page .side-card {
        padding: 20px;
    }

    .financial-report-page.income-statement-page .side-card h3 {
        margin: 0 0 15px;
        font-size: 17px;
        letter-spacing: -.01em;
    }

    .financial-report-page.income-statement-page .ratio-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 0;
        border-bottom: 1px solid #edf0f3;
    }

    .financial-report-page.income-statement-page .ratio-row:first-of-type {
        padding-top: 0;
    }

    .financial-report-page.income-statement-page .ratio-row span:first-child {
        color: var(--muted);
    }

    .financial-report-page.income-statement-page .ratio-row strong {
        font-weight: 900;
        text-align: right;
    }

    .financial-report-page.income-statement-page .mini-chart {
        display: grid;
        gap: 12px;
        margin-top: 14px;
    }

    .financial-report-page.income-statement-page .bar-item span {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 13px;
        margin-bottom: 7px;
        color: #344054;
    }

    .financial-report-page.income-statement-page .bar-item em {
        color: var(--muted);
        font-style: normal;
        white-space: nowrap;
    }

    .financial-report-page.income-statement-page .bar {
        height: 10px;
        background: #eef2f6;
        border-radius: 999px;
        overflow: hidden;
    }

    .financial-report-page.income-statement-page .bar-fill {
        height: 100%;
        background: var(--primary);
        border-radius: 999px;
    }

    .financial-report-page.income-statement-page .bar-fill.green-bg {
        background: #12b76a;
    }

    .financial-report-page.income-statement-page .bar-fill.orange-bg {
        background: #f79009;
    }

    .financial-report-page.income-statement-page .guardrail-list {
        display: grid;
        gap: 12px;
    }

    .financial-report-page.income-statement-page .guardrail-item {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 10px;
        align-items: flex-start;
        padding: 12px;
        border-radius: 14px;
        border: 1px solid var(--line);
        background: #fff;
    }

    .financial-report-page.income-statement-page .guardrail-item span {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: var(--success-soft);
        color: #067647;
        font-weight: 900;
    }

    .financial-report-page.income-statement-page .guardrail-item p {
        margin: 0;
        color: #475467;
        font-size: 13px;
        line-height: 1.45;
    }

    .financial-report-page.income-statement-page .insight {
        display: flex;
        gap: 12px;
        padding: 16px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid var(--line);
        margin-top: 14px;
    }

    .financial-report-page.income-statement-page .insight:first-of-type {
        margin-top: 0;
    }

    .financial-report-page.income-statement-page .insight-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--primary-soft);
        color: var(--primary);
        display: grid;
        place-items: center;
        font-size: 22px;
        flex: 0 0 auto;
    }

    .financial-report-page.income-statement-page .insight strong {
        display: block;
        margin-bottom: 5px;
    }

    .financial-report-page.income-statement-page .insight p {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.45;
    }

    @media (max-width: 1320px) {
        .financial-report-page.income-statement-page .income-template-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1024px) {
        .financial-report-page.income-statement-page .income-template-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .financial-report-page.income-statement-page .income-template-stats {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        .financial-report-page.income-statement-page .report-side-stack {
            display: none !important;
        }
    }
</style>
