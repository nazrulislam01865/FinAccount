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
        height: 48px;
        min-height: 48px;
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
        height: 48px;
        min-height: 48px;
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
