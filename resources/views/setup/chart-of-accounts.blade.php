@extends('layouts.app')

@section('title', 'Chart of Accounts Setup | HisebGhor')

@push('styles')
<style>
    .coa-template-page{
        --coa-card:#fff;
        --coa-ink:var(--text);
        --coa-muted:var(--muted);
        --coa-line:#dce7ef;
        --coa-soft:var(--primary-soft);
        --coa-shadow:0 16px 40px rgba(16,24,40,.10);
        --coa-radius:18px;
        color:var(--coa-ink);
    }

    .coa-hero{
        display:flex;
        justify-content:space-between;
        gap:18px;
        align-items:flex-start;
        margin-bottom:22px;
        padding:22px;
        border-radius:24px;
        background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 62%,#0891b2 100%);
        color:#fff;
        box-shadow:var(--coa-shadow);
    }

    .coa-hero-brand{display:flex;gap:14px;align-items:center;min-width:0;}
    .coa-hero-logo{width:54px;height:54px;border-radius:18px;background:rgba(255,255,255,.16);display:grid;place-items:center;font-weight:900;border:1px solid rgba(255,255,255,.25);flex:0 0 auto;}
    .coa-hero h1{margin:0;font-size:clamp(26px,4vw,40px);letter-spacing:-.03em;line-height:1.08;}
    .coa-hero p{margin:6px 0 0;color:rgba(255,255,255,.82);font-size:15px;}
    .coa-quick-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
    .coa-btn-light{background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.22);box-shadow:none;}
    .coa-btn-light:hover{background:rgba(255,255,255,.24);transform:translateY(-1px);}
    .coa-import-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end;}
    .coa-import-form input[type=file]{max-width:230px;min-height:42px;padding:7px;background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.28);color:#fff;}
    .coa-import-form input[type=file]::file-selector-button{border:0;border-radius:10px;padding:7px 10px;margin-right:8px;font-weight:800;color:#1d2939;}
    .coa-import-progress{display:none;flex:1 1 100%;min-width:260px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.14);border-radius:16px;padding:11px 12px;margin-top:2px;box-shadow:inset 0 1px 0 rgba(255,255,255,.12);}
    .coa-import-progress.is-active{display:block;}
    .coa-import-progress-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;color:#fff;font-size:13px;font-weight:850;}
    .coa-import-progress-text{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .coa-import-progress-percent{font-variant-numeric:tabular-nums;}
    .coa-import-progress-track{height:10px;border-radius:999px;background:rgba(255,255,255,.24);overflow:hidden;}
    .coa-import-progress-bar{height:100%;width:0%;border-radius:999px;background:#fff;transition:width .22s ease;}
    .coa-import-progress-detail{margin-top:7px;color:rgba(255,255,255,.78);font-size:12px;font-weight:700;line-height:1.35;}
    .coa-import-form.is-importing input[type=file],.coa-import-form.is-importing button[type=submit]{opacity:.68;pointer-events:none;}
    .coa-alert{margin-bottom:16px;padding:12px 14px;border-radius:14px;border:1px solid #bbf7d0;background:#f0fdf4;color:#067647;font-weight:750;}
    .coa-alert.error{border-color:#fecaca;background:#fef2f2;color:#991b1b;}

    .coa-import-review-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.46);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding:34px 18px;overflow:auto;}
    .coa-import-review-backdrop.coa-hidden{display:none!important;}
    .coa-import-review-modal{width:min(1180px,100%);background:#fff;border-radius:24px;border:1px solid var(--coa-line);box-shadow:0 24px 80px rgba(15,23,42,.28);overflow:hidden;}
    .coa-import-review-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:22px 24px;border-bottom:1px solid var(--coa-line);background:linear-gradient(180deg,#f8fbff,#fff);}
    .coa-import-review-head h2{margin:0;color:#1d2939;font-size:24px;letter-spacing:-.03em;}
    .coa-import-review-head p{margin:6px 0 0;color:var(--coa-muted);font-size:14px;line-height:1.45;}
    .coa-review-head-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
    .coa-review-head-actions form{margin:0;}
    .coa-review-close{width:38px;height:38px;min-height:38px;border-radius:999px;background:#f2f4f7;color:#344054;border:1px solid var(--coa-line);font-size:20px;padding:0;}
    .coa-review-discard{background:#fff;color:#b42318;border:1px solid #fecaca;border-radius:12px;min-height:38px;padding:0 12px;font-weight:850;font-size:13px;}
    .coa-review-discard:hover,.coa-review-close:hover{background:#fef2f2;color:#991b1b;}
    .coa-import-review-summary{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;}
    .coa-review-pill{display:inline-flex;align-items:center;border-radius:999px;padding:7px 12px;background:#eef4ff;color:#175cd3;font-weight:850;font-size:12px;}
    .coa-import-review-body{padding:20px 24px 24px;display:grid;gap:16px;max-height:72vh;overflow:auto;}
    .coa-import-issue{border:1px solid var(--coa-line);border-radius:20px;background:#fff;overflow:hidden;}
    .coa-import-issue summary{cursor:pointer;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 18px;background:#fbfcfd;list-style:none;}
    .coa-import-issue summary::-webkit-details-marker{display:none;}
    .coa-issue-title{font-weight:900;color:#101828;font-size:16px;}
    .coa-issue-subtitle{margin-top:4px;color:#667085;font-size:13px;}
    .coa-issue-tag{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:900;white-space:nowrap;}
    .coa-issue-tag.conflict{background:#fff7ed;color:#c2410c;}
    .coa-issue-tag.rule_violation{background:#fef2f2;color:#b42318;}
    .coa-issue-content{padding:18px;display:grid;gap:16px;}
    .coa-issue-reasons{margin:0;padding-left:18px;color:#7a271a;font-weight:700;line-height:1.5;}
    .coa-existing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
    .coa-existing-card{border:1px solid #fed7aa;background:#fff7ed;border-radius:16px;padding:12px;color:#7c2d12;}
    .coa-existing-card strong{display:block;color:#9a3412;margin-bottom:5px;}
    .coa-resolve-form{display:grid;gap:14px;border-top:1px dashed var(--coa-line);padding-top:16px;}
    .coa-resolve-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}
    .coa-resolve-grid .span-2{grid-column:span 2;}
    .coa-resolve-grid .span-4{grid-column:1/-1;}
    .coa-resolve-form label{font-size:12px;margin-bottom:6px;color:#344054;font-weight:850;}
    .coa-resolve-form input,.coa-resolve-form select,.coa-resolve-form textarea{min-height:42px;border-radius:12px;font-size:14px;}
    .coa-resolve-errors{border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:14px;padding:10px 12px;font-weight:750;}
    .coa-resolve-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;}
    .coa-resolve-actions .danger{background:#fff;color:#b42318;border:1px solid #fecaca;}
    .coa-resolve-actions .success{background:#16a34a;color:#fff;}
    @media(max-width:940px){.coa-resolve-grid,.coa-existing-grid{grid-template-columns:1fr 1fr;}.coa-resolve-grid .span-2,.coa-resolve-grid .span-4{grid-column:1/-1;}}
    @media(max-width:640px){.coa-import-review-head{flex-direction:column;}.coa-resolve-grid,.coa-existing-grid{grid-template-columns:1fr;}.coa-import-review-body{max-height:none;}}

    .coa-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:18px;}
    .coa-stat{background:#fff;border:1px solid var(--coa-line);border-radius:16px;padding:14px;box-shadow:0 8px 24px rgba(16,24,40,.06);min-width:0;}
    .coa-stat span{display:block;color:var(--coa-muted);font-size:12px;margin-bottom:4px;font-weight:750;}
    .coa-stat strong{font-size:22px;letter-spacing:-.04em;line-height:1;color:#1d2939;}

    .coa-main-grid{display:grid;grid-template-columns:1fr;gap:18px;align-items:start;}
    .coa-main-grid > .coa-card{width:100%;}
    .coa-card{background:var(--coa-card);border:1px solid var(--coa-line);border-radius:var(--coa-radius);box-shadow:var(--coa-shadow);overflow:hidden;}
    .coa-card-header{padding:18px 20px;border-bottom:1px solid var(--coa-line);display:flex;align-items:flex-start;justify-content:space-between;gap:14px;}
    .coa-card-title{margin:0;font-size:19px;letter-spacing:-.02em;line-height:1.25;}
    .coa-card-subtitle{margin:4px 0 0;color:var(--coa-muted);font-size:13px;}
    .coa-card-body{padding:20px;}

    .coa-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;}
    .coa-field{display:flex;flex-direction:column;gap:7px;}
    .coa-field.full{grid-column:1/-1;}
    .coa-field label{margin:0;font-weight:750;color:#2d3b4d;font-size:13px;}
    .coa-hint{color:var(--coa-muted);font-size:12px;margin-top:-2px;}
    .coa-template-page input,
    .coa-template-page select,
    .coa-template-page textarea{border-radius:14px;min-height:46px;padding:12px 13px;font-size:15px;}
    .coa-template-page textarea{min-height:76px;resize:vertical;}
    .coa-hidden{display:none!important;}
    .coa-advanced{border-top:1px dashed var(--coa-line);margin-top:16px;padding-top:16px;}
    .coa-guidance{background:var(--coa-soft);border:1px solid #dbeafe;border-radius:18px;padding:16px;display:flex;gap:12px;margin-bottom:16px;}
    .coa-guidance-icon{width:38px;height:38px;border-radius:14px;background:#fff;display:grid;place-items:center;flex:0 0 auto;font-size:20px;}
    .coa-guidance strong{display:block;margin-bottom:4px;}
    .coa-guidance p{margin:0;color:#425266;font-size:14px;}

    .coa-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:850;background:#e7f5ef;color:#166043;margin:2px;white-space:nowrap;}
    .coa-badge.group{background:#edf2f7;color:#344054;}
    .coa-badge.posting{background:#e8f3ff;color:#135b93;}
    .coa-badge.cash{background:#ecfdf3;color:#05603a;}
    .coa-badge.bank{background:#e7f5ff;color:#175cd3;}
    .coa-badge.party{background:#fff4e5;color:#a15c05;}
    .coa-badge.system{background:#f4f3ff;color:#5925dc;}
    .coa-badge.inactive{background:#fff1f0;color:#b42318;}

    .coa-derived-flags{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px;}
    .coa-message-list{margin:12px 0 0;display:grid;gap:8px;}
    .coa-message{padding:10px 12px;border-radius:12px;font-size:13px;font-weight:650;}
    .coa-message.error{background:#fff1f0;color:#b42318;border:1px solid #ffd0cc;}
    .coa-actions-row{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:18px;}
    .coa-section{margin-top:18px;}

    .coa-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
    .coa-tab{background:#eef6f8;color:var(--primary);box-shadow:none;}
    .coa-tab.active{background:var(--primary);color:#fff;}
    .coa-bulk-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;border:1px solid #bfdbfe;border-radius:16px;background:#f7fbff;}
    .coa-bulk-left,.coa-bulk-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .coa-select-control{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:800;color:#344054;cursor:pointer;white-space:nowrap;}
    .coa-select-control input{width:18px;height:18px;min-height:18px;padding:0;margin:0;accent-color:var(--primary);}
    .coa-selected-count{font-size:13px;font-weight:850;color:#1d2939;}
    .coa-bulk-delete:disabled,.coa-bulk-clear:disabled{opacity:.48;cursor:not-allowed;transform:none;}
    .coa-select-cell{width:46px;text-align:center;vertical-align:middle!important;}
    .coa-filter-grid{display:grid;grid-template-columns:2fr repeat(4,1fr);gap:10px;margin-bottom:14px;}
    .coa-tree{display:grid;gap:6px;}
    .coa-tree-node{padding:10px 12px;border:1px solid var(--coa-line);border-radius:14px;background:#fff;cursor:pointer;transition:.16s ease;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:10px;}
    .coa-tree-content{min-width:0;}
    .coa-tree-actions{flex-wrap:wrap;}
    .coa-tree-node:hover{border-color:#bfdbfe;transform:translateY(-1px);}
    .coa-tree-name{font-weight:850;color:#1d2939;}
    .coa-tree-meta{color:var(--coa-muted);font-size:12px;margin:2px 0 4px;}
    .coa-lvl1{margin-left:0;background:#f7fbff;}
    .coa-lvl2{margin-left:18px;}
    .coa-lvl3{margin-left:36px;}
    .coa-lvl4{margin-left:54px;background:#fbfffd;}

    .coa-table-wrap{overflow-x:scroll;border:1px solid var(--coa-line);border-radius:18px;background:#fff;scrollbar-gutter:stable both-edges;}
    .coa-table-wrap::-webkit-scrollbar{height:12px;}
    .coa-table-wrap::-webkit-scrollbar-track{background:#f2f4f7;border-radius:999px;}
    .coa-table-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px;border:3px solid #f2f4f7;}
    .coa-template-page table{width:100%;border-collapse:collapse;min-width:1050px;}
    .coa-template-page th{background:#143e5c;color:#fff;text-align:left;padding:13px;font-size:13px;white-space:nowrap;border-bottom:0;}
    .coa-template-page td{padding:13px;border-bottom:1px solid var(--coa-line);font-size:14px;vertical-align:top;color:#344054;}
    .coa-template-page tr:nth-child(even) td{background:#f6fbfd;}
    .coa-template-page tr:hover td{background:#f1f7ff;}
    .coa-table-empty{padding:18px;color:var(--coa-muted);text-align:center;}

    .coa-mobile-list{display:none;}
    .coa-mobile-item{border:1px solid var(--coa-line);border-radius:16px;padding:14px;background:#fff;margin-bottom:12px;box-shadow:0 8px 22px rgba(16,24,40,.06);}
    .coa-mobile-top{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;}
    .coa-mobile-title{font-weight:900;color:#1d2939;}
    .coa-mobile-meta{color:var(--coa-muted);font-size:13px;}
    .coa-mobile-lines{display:grid;gap:6px;font-size:13px;margin-top:10px;}
    .coa-action-stack{display:flex;gap:8px;align-items:center;justify-content:flex-end;}
    .coa-action-stack form{display:inline;}
    .coa-small-btn{min-height:34px;padding:7px 10px;border-radius:10px;font-size:12px;}

    @media(max-width:1320px){
        .coa-main-grid{grid-template-columns:1fr;}
        .coa-stats{grid-template-columns:repeat(3,minmax(0,1fr));}
        .coa-hero{flex-direction:column;}
        .coa-quick-actions{justify-content:flex-start;}
    }
    @media(max-width:880px){
        .coa-form-grid,.coa-filter-grid{grid-template-columns:1fr;}
        .coa-stats{grid-template-columns:1fr 1fr;}
        .coa-actions-row button,.coa-quick-actions button{width:100%;}
        .coa-lvl1,.coa-lvl2,.coa-lvl3,.coa-lvl4{margin-left:0;}
        .coa-tree-node{border-left:5px solid #bfdbfe;}
    }
    @media(max-width:1080px){
        .coa-view-toolbar.coa-bulk-toolbar{align-items:flex-start;}
        .coa-view-toolbar.coa-bulk-toolbar .coa-load-group{margin-left:auto;border-top:0;padding-top:0;}
    }
    @media(max-width:720px){
        .coa-hero{padding:18px;border-radius:20px;}
        .coa-hero-logo{width:46px;height:46px;}
        .coa-table-wrap{display:none;}
        .coa-mobile-list{display:block;}
    }
    @media(max-width:560px){
        .coa-stats{grid-template-columns:1fr;}
    }




    /* CoA header and list rendering fixes */
    .content .coa-template-page .coa-hero{
        flex-direction:column!important;
        align-items:flex-start!important;
        justify-content:flex-start!important;
        gap:22px!important;
        padding:34px 36px!important;
    }
    .content .coa-template-page .coa-hero .coa-hero-brand{
        width:100%!important;
        max-width:720px!important;
        flex:0 1 auto!important;
    }
    .content .coa-template-page .coa-hero h1{
        max-width:720px!important;
        margin:0 0 10px!important;
        font-size:clamp(28px,2.7vw,44px)!important;
        line-height:1.12!important;
        overflow-wrap:anywhere!important;
    }
    .content .coa-template-page .coa-hero p{
        max-width:760px!important;
        font-size:17px!important;
        line-height:1.45!important;
    }
    .content .coa-template-page .coa-quick-actions{
        width:100%!important;
        display:flex!important;
        align-items:center!important;
        justify-content:flex-start!important;
        gap:10px!important;
        flex-wrap:wrap!important;
    }
    .content .coa-template-page .coa-hero :is(.coa-btn-light,.button,button){
        min-height:46px!important;
        height:46px!important;
        padding:0 18px!important;
        border-radius:14px!important;
        font-size:14px!important;
        font-weight:800!important;
        line-height:1!important;
    }
    .content .coa-template-page .coa-import-form{
        display:flex!important;
        align-items:center!important;
        gap:8px!important;
        flex:1 1 420px!important;
        min-width:min(100%,360px)!important;
        max-width:640px!important;
        margin:0!important;
    }
    .content .coa-template-page .coa-import-form input[type=file]{
        flex:1 1 220px!important;
        min-width:200px!important;
        max-width:100%!important;
        height:46px!important;
        min-height:46px!important;
        padding:7px 10px!important;
        border-radius:14px!important;
        font-size:14px!important;
    }
    .coa-toolbar-slot{min-height:0;}
    .coa-toolbar-slot:empty{display:none;}
    .coa-view-toolbar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
        margin:0 0 10px;
        padding:10px 12px;
        border:1px solid var(--coa-line);
        border-radius:14px;
        background:#f8fbff;
    }
    .coa-bulk-selection{display:flex;align-items:center;gap:10px;min-width:0;flex-wrap:wrap;}
    .coa-bulk-selection .coa-selected-count{white-space:nowrap;}
    .coa-load-group{display:flex;align-items:center;justify-content:flex-end;gap:10px;min-width:0;margin-left:auto;}
    .coa-load-control{display:flex;align-items:center;gap:7px;flex:0 0 auto;}
    .coa-load-control label{margin:0;font-size:12px;font-weight:850;color:#344054;white-space:nowrap;}
    .coa-load-control select{width:88px;min-height:38px;height:38px;padding:7px 30px 7px 10px;border-radius:10px;font-size:13px;}
    .coa-list-summary{min-width:0;color:var(--coa-muted);font-size:12px;font-weight:750;line-height:1.4;text-align:right;}
    .coa-bulk-actions{display:flex;align-items:center;justify-content:flex-start;gap:8px;flex-wrap:wrap;}
    .coa-bulk-actions button{white-space:nowrap;min-height:38px;padding:8px 12px;border-radius:10px;font-size:12px;}
    .coa-tree{
        max-height:560px;
        overflow-y:auto;
        overflow-x:hidden;
        padding-right:6px;
        scrollbar-gutter:stable;
    }
    .coa-tree::-webkit-scrollbar,
    .coa-table-wrap::-webkit-scrollbar{width:12px;height:12px;}
    .coa-tree::-webkit-scrollbar-track,
    .coa-table-wrap::-webkit-scrollbar-track{background:#f2f4f7;border-radius:999px;}
    .coa-tree::-webkit-scrollbar-thumb,
    .coa-table-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px;border:3px solid #f2f4f7;}
    .coa-tree-node{padding:9px 11px;border-radius:13px;}
    .coa-tree-name{font-size:15px;font-weight:700;line-height:1.35;letter-spacing:-.01em;}
    .coa-tree-meta{font-size:12px;line-height:1.45;font-weight:500;}
    .coa-tree .coa-badge{font-size:10px;font-weight:700;padding:4px 8px;}
    .coa-table-wrap{max-height:580px;overflow:auto;}
    .coa-template-page thead th{position:sticky;top:0;z-index:2;}
    @media(max-width:720px){
        .content .coa-template-page .coa-import-form{flex:1 1 100%;}
        .content .coa-template-page .coa-hero :is(.coa-btn-light,.button,button){width:100%!important;}
        .content .coa-template-page .coa-import-form input[type=file]{width:100%!important;}
        .coa-view-toolbar{align-items:stretch;padding:10px;}
        .coa-bulk-selection,.coa-load-group,.coa-bulk-actions{width:100%;}
        .coa-bulk-selection{align-items:flex-start;}
        .coa-bulk-actions{order:3;}
        .coa-bulk-actions button{flex:1 1 140px;}
        .coa-load-group{margin-left:0;justify-content:space-between;flex-wrap:wrap;border-top:1px solid var(--coa-line);padding-top:10px;}
        .coa-list-summary{text-align:left;flex:1 1 180px;}
        .coa-load-control{margin-left:auto;}
        .coa-load-control select{width:92px;}
        .coa-tree-node{grid-template-columns:auto minmax(0,1fr);}
        .coa-tree-actions{grid-column:1/-1;justify-content:flex-start;padding-left:28px;}
    }

    /* Unified blue hero heading is controlled globally from resources/css/app.css.
       This page keeps its logic and form/table behavior unchanged. */
</style>
@endpush

@section('content')
@php
    $accountRows = $accounts->map(function ($account) use ($coaLevels) {
        $effectiveLevel = (int) ($account->coa_level ?: (($account->account_level ?? 'Ledger') === 'Ledger' ? 4 : 1));
        $levelName = $coaLevels[$effectiveLevel] ?? ($account->account_level ?? 'Ledger');
        $ledgerType = $account->ledger_type ?: ($account->is_cash_bank ? 'Bank' : (($account->account_level ?? 'Ledger') === 'Group' ? 'Group' : ($account->accountType?->name ?? 'Asset')));
        $normalBalance = $account->normal_balance ?: $account->accountType?->normal_balance;
        $accountNature = $account->account_nature ?: $account->accountType?->name;
        $isCashBank = (bool) $account->is_cash_bank;
        $isPartyControl = (bool) $account->is_party_control;
        $isPosting = (bool) $account->posting_allowed;
        $isSystem = (bool) $account->is_system_ledger;
        $isUserSelectable = (bool) $account->is_user_selectable;

        return [
            'id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'display_name' => $account->display_name,
            'coa_level' => $effectiveLevel,
            'level_name' => $levelName,
            'account_level' => $effectiveLevel === 4 ? 'Ledger' : 'Group',
            'parent_id' => $account->parent_id,
            'parent_name' => $account->parent?->account_name,
            'parent_display_name' => $account->parent?->display_name,
            'account_type_id' => $account->account_type_id,
            'account_class' => $account->accountType?->name,
            'account_group' => $account->account_group,
            'account_sub_group' => $account->account_sub_group,
            'account_nature' => $accountNature,
            'normal_balance' => $normalBalance,
            'ledger_type' => $ledgerType,
            'posting_allowed' => $isPosting,
            'is_cash_bank' => $isCashBank,
            'is_party_control' => $isPartyControl,
            'party_type_id' => $account->party_type_id,
            'party_type_name' => $account->partyType?->name,
            'is_system_ledger' => $isSystem,
            'is_user_selectable' => $isUserSelectable,
            'description' => $account->description,
            'example_usage' => $account->example_usage,
            'status' => $account->status,
            'update_url' => route('api.chart-of-accounts.update', $account),
            'delete_url' => route('setup.chart-of-accounts.destroy', $account),
        ];
    });
    $canManageCoa = auth()->user()?->hasPermission('chart-of-accounts.manage') ?? false;
@endphp

<div class="coa-template-page">
    <header class="coa-hero">
        <div class="coa-hero-brand">
            <div class="coa-hero-logo">HG</div>
            <div>
                <h1>Chart of Accounts Setup</h1>
                <p>Build your accounting structure in a simple guided way.</p>
            </div>
        </div>
        <div class="coa-quick-actions">
            <button class="coa-btn-light" type="button" id="newAccountHeroBtn">+ Add New Account</button>
            <a class="coa-btn-light button" href="{{ route('setup.chart-of-accounts.export') }}">Export Excel</a>
            <a class="coa-btn-light button" href="{{ route('setup.chart-of-accounts.import-template') }}">Download Import Template</a>
            <button class="coa-btn-light" type="button" data-coa-tab-button="tree">View CoA Tree</button>
            <button class="coa-btn-light" type="button" data-coa-tab-button="posting">Posting Ledgers</button>
            <form class="coa-import-form" method="POST" action="{{ route('setup.chart-of-accounts.import') }}" enctype="multipart/form-data" data-coa-import-form data-success-url="{{ route('setup.chart-of-accounts') }}">
                @csrf
                <input type="file" name="coa_file" accept=".xlsx,.xlsm,.csv,.txt" required data-coa-import-file>
                <button class="coa-btn-light" type="submit" data-coa-import-submit>Import Excel</button>
                <div class="coa-import-progress" data-coa-import-progress aria-live="polite" aria-hidden="true">
                    <div class="coa-import-progress-top">
                        <span class="coa-import-progress-text" data-coa-import-status>Select a file to import.</span>
                        <span class="coa-import-progress-percent" data-coa-import-percent>0%</span>
                    </div>
                    <div class="coa-import-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-coa-import-track>
                        <div class="coa-import-progress-bar" data-coa-import-bar></div>
                    </div>
                    <div class="coa-import-progress-detail" data-coa-import-detail>Waiting to start.</div>
                </div>
            </form>
        </div>
    </header>

    @if(session('status'))
        <div class="coa-alert">{{ session('status') }}</div>
    @endif

    @if(session('import_errors'))
        <div class="coa-alert error">
            <strong>Some rows were skipped:</strong>
            <ul style="margin:8px 0 0 18px">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="coa-alert error">{{ session('error') }}</div>
    @endif

    @php
        $coaImportReview = session('coa_import_review');
    @endphp
    @if(!empty($coaImportReview['issues']))
        <div class="coa-import-review-backdrop" id="coaImportReviewModal" role="dialog" aria-modal="true" aria-labelledby="coaImportReviewTitle">
            <div class="coa-import-review-modal">
                <div class="coa-import-review-head">
                    <div>
                        <h2 id="coaImportReviewTitle">Review Chart of Accounts Import</h2>
                        <p>Existing accounts are no longer updated automatically. Resolve each conflict or accounting-rule issue before it is saved.</p>
                        <div class="coa-import-review-summary">
                            <span class="coa-review-pill">Created: {{ $coaImportReview['summary']['created'] ?? 0 }}</span>
                            <span class="coa-review-pill">Conflicts: {{ $coaImportReview['summary']['conflicts'] ?? 0 }}</span>
                            <span class="coa-review-pill">Rule Issues: {{ $coaImportReview['summary']['violations'] ?? 0 }}</span>
                            <span class="coa-review-pill">Needs Review: {{ count($coaImportReview['issues'] ?? []) }}</span>
                        </div>
                    </div>
                    <div class="coa-review-head-actions">
                        <form method="POST" action="{{ route('setup.chart-of-accounts.import.discard') }}" data-discard-import-review>
                            @csrf
                            <button type="submit" class="coa-review-discard">Discard review</button>
                        </form>
                        <form method="POST" action="{{ route('setup.chart-of-accounts.import.discard') }}" data-discard-import-review>
                            @csrf
                            <button type="submit" class="coa-review-close" aria-label="Discard import review">×</button>
                        </form>
                    </div>
                </div>
                <div class="coa-import-review-body">
                    <div class="coa-alert error" style="margin-bottom:0">
                        These rows are only pending review; they have not been imported. Click <strong>Discard review</strong> to permanently clear this popup from your session.
                    </div>
                    @foreach($coaImportReview['issues'] as $issueIndex => $issue)
                        @php
                            $payload = $issue['payload'] ?? [];
                            $issueType = $issue['type'] ?? 'rule_violation';
                            $firstExisting = $issue['existing'][0] ?? null;
                        @endphp
                        <details class="coa-import-issue" @if($issueIndex === 0 || !empty($issue['resolve_errors'])) open @endif>
                            <summary>
                                <div>
                                    <div class="coa-issue-title">
                                        Row {{ $issue['line_number'] ?? '—' }}: {{ $issue['account_code'] ?? ($payload['account_code'] ?? 'New Account') }} — {{ $issue['account_name'] ?? ($payload['account_name'] ?? '') }}
                                    </div>
                                    <div class="coa-issue-subtitle">
                                        {{ $issueType === 'conflict' ? 'This row matches an existing account. Choose update, edit as new, or skip.' : 'This row does not follow the required CoA/accounting setup rules. Correct it before adding.' }}
                                    </div>
                                </div>
                                <span class="coa-issue-tag {{ $issueType }}">{{ $issueType === 'conflict' ? 'Conflict' : 'Rule Issue' }}</span>
                            </summary>
                            <div class="coa-issue-content">
                                @if(!empty($issue['reasons']))
                                    <ul class="coa-issue-reasons">
                                        @foreach($issue['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if(!empty($issue['resolve_errors']))
                                    <div class="coa-resolve-errors">
                                        <strong>Still needs correction:</strong>
                                        <ul style="margin:6px 0 0 18px">
                                            @foreach($issue['resolve_errors'] as $resolveError)
                                                <li>{{ $resolveError }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if(!empty($issue['existing']))
                                    <div class="coa-existing-grid">
                                        @foreach($issue['existing'] as $existing)
                                            <div class="coa-existing-card">
                                                <strong>Existing account</strong>
                                                <div>{{ $existing['account_code'] ?? '—' }} — {{ $existing['account_name'] ?? '—' }}</div>
                                                <div>Level: {{ $existing['coa_level'] ?? '—' }} | Nature: {{ $existing['account_type_name'] ?? '—' }}</div>
                                                <div>Ledger Type: {{ $existing['ledger_type'] ?? '—' }} | Status: {{ $existing['status'] ?? '—' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('setup.chart-of-accounts.import.resolve') }}" class="coa-resolve-form">
                                    @csrf
                                    <input type="hidden" name="import_issue_id" value="{{ $issue['id'] }}">

                                    @if(!empty($issue['existing']))
                                        <div class="coa-resolve-grid">
                                            <div class="span-2">
                                                <label>Existing account to update</label>
                                                <select name="existing_account_id">
                                                    @foreach($issue['existing'] as $existing)
                                                        <option value="{{ $existing['id'] }}" @selected(($firstExisting['id'] ?? null) === ($existing['id'] ?? null))>
                                                            {{ $existing['account_code'] ?? '' }} — {{ $existing['account_name'] ?? '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="coa-resolve-grid">
                                        <div>
                                            <label>Account code</label>
                                            <input name="account_code" value="{{ $payload['account_code'] ?? '' }}" required>
                                        </div>
                                        <div>
                                            <label>Account name</label>
                                            <input name="account_name" value="{{ $payload['account_name'] ?? '' }}" required>
                                        </div>
                                        <div>
                                            <label>CoA level</label>
                                            <select name="coa_level" required>
                                                @foreach($coaLevels as $level => $label)
                                                    <option value="{{ $level }}" @selected((int)($payload['coa_level'] ?? 4) === (int)$level)>{{ $level }} — {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label>Status</label>
                                            <select name="status" required>
                                                <option value="Active" @selected(($payload['status'] ?? 'Active') === 'Active')>Active</option>
                                                <option value="Inactive" @selected(($payload['status'] ?? 'Active') === 'Inactive')>Inactive</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label>Account nature</label>
                                            <select name="account_type_id" required>
                                                <option value="">Select nature</option>
                                                @foreach($accountTypes as $accountTypeOption)
                                                    <option value="{{ $accountTypeOption->id }}" @selected((int)($payload['account_type_id'] ?? 0) === (int)$accountTypeOption->id)>
                                                        {{ $accountTypeOption->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label>Parent account</label>
                                            <select name="parent_id">
                                                <option value="">No parent / Level 1</option>
                                                @foreach($parentAccountOptions as $parentOption)
                                                    <option value="{{ $parentOption->id }}" @selected((int)($payload['parent_id'] ?? 0) === (int)$parentOption->id)>
                                                        L{{ $parentOption->coa_level }} — {{ $parentOption->account_code }} — {{ $parentOption->account_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label>Normal balance</label>
                                            <select name="normal_balance" required>
                                                <option value="Debit" @selected(($payload['normal_balance'] ?? 'Debit') === 'Debit')>Debit</option>
                                                <option value="Credit" @selected(($payload['normal_balance'] ?? 'Debit') === 'Credit')>Credit</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label>Ledger type</label>
                                            <select name="ledger_type" required>
                                                @foreach($ledgerTypes as $type)
                                                    <option value="{{ $type }}" @selected(($payload['ledger_type'] ?? 'Asset') === $type)>{{ $type }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label>Party type</label>
                                            <select name="party_type_id">
                                                <option value="">Not applicable</option>
                                                @foreach($partyTypes as $partyTypeOption)
                                                    <option value="{{ $partyTypeOption->id }}" @selected((int)($payload['party_type_id'] ?? 0) === (int)$partyTypeOption->id)>{{ $partyTypeOption->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label>User selectable?</label>
                                            <select name="is_user_selectable">
                                                <option value="1" @selected((bool)($payload['is_user_selectable'] ?? false))>Yes</option>
                                                <option value="0" @selected(! (bool)($payload['is_user_selectable'] ?? false))>No</option>
                                            </select>
                                        </div>
                                        <input type="hidden" name="is_system_ledger" value="{{ (bool)($payload['is_system_ledger'] ?? false) ? 1 : 0 }}">
                                        <div class="span-2">
                                            <label>Description</label>
                                            <input name="description" value="{{ $payload['description'] ?? '' }}">
                                        </div>
                                        <div class="span-4">
                                            <label>Example usage</label>
                                            <textarea name="example_usage">{{ $payload['example_usage'] ?? '' }}</textarea>
                                        </div>
                                    </div>

                                    <div class="coa-resolve-actions">
                                        <button class="btn-soft danger" type="submit" name="import_issue_action" value="skip">Skip this row</button>
                                        <button class="btn-primary success" type="submit" name="import_issue_action" value="create">Add / Save corrected as new</button>
                                        @if($issueType === 'conflict' && !empty($issue['existing']))
                                            <button class="btn-primary" type="submit" name="import_issue_action" value="update">Update selected existing account</button>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <section class="coa-stats" aria-label="Chart of Accounts statistics">
        <div class="coa-stat"><span>Total Accounts</span><strong data-coa-stat="total">{{ $stats['total'] ?? $accountRows->count() }}</strong></div>
        <div class="coa-stat"><span>Posting Ledgers</span><strong data-coa-stat="posting">{{ $stats['posting'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Group Accounts</span><strong data-coa-stat="groups">{{ $stats['groups'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Cash/Bank Ledgers</span><strong data-coa-stat="cash_bank">{{ $stats['cash_bank'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Party Control</span><strong data-coa-stat="party_control">{{ $stats['party_control'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Active Accounts</span><strong data-coa-stat="active">{{ $stats['active'] ?? 0 }}</strong></div>
    </section>

    <section class="coa-main-grid">
        <div class="coa-card" id="formCard">
            <div class="coa-card-header">
                <div>
                    <h2 class="coa-card-title" id="accountFormTitle">Guided Account Setup</h2>
                    <p class="coa-card-subtitle">Create groups for reports and ledger heads for posting.</p>
                </div>
                <span class="coa-badge posting">4-Level CoA</span>
            </div>
            <div class="coa-card-body">
                <form
                    id="accountForm"
                    data-frontend-form
                    data-action="{{ route('api.chart-of-accounts.store') }}"
                    data-store-url="{{ route('api.chart-of-accounts.store') }}"
                    data-method="POST"
                    data-success="Account saved successfully."
                >
                    <input type="hidden" name="_method" id="accountFormMethod" value="POST">
                    <input type="hidden" name="account_level" id="accountLevelHidden" value="Ledger">
                    <input type="hidden" name="posting_allowed" id="postingAllowed" value="1">
                    <input type="hidden" name="is_cash_bank" id="isCashBank" value="0">
                    <input type="hidden" name="is_party_control" id="isPartyControl" value="0">
                    <input type="hidden" name="account_nature" id="accountNatureHidden" value="">
                    <input type="hidden" name="account_group" id="accountGroupHidden" value="">
                    <input type="hidden" name="account_sub_group" id="accountSubGroupHidden" value="">
                    <input type="hidden" name="is_system_ledger" id="isSystemLedger" value="0">
                    <input type="hidden" name="is_user_selectable" id="isUserSelectable" value="1">

                    <div class="coa-form-grid">
                        <div class="coa-field">
                            <label for="coaLevel">What are you creating? <span class="required">*</span></label>
                            <select id="coaLevel" name="coa_level" required>
                                <option value="">Select account type</option>
                                @foreach($coaLevels as $level => $label)
                                    <option value="{{ $level }}" @selected((int) $level === 4)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="coa-field" id="parentWrap">
                            <label for="parentAccount">Under which account should this be placed? <span class="required">*</span></label>
                            <select
                                id="parentAccount"
                                name="parent_id"
                                data-parent-account-select
                                data-base-url="/api/dropdowns/parent-accounts"
                                data-dropdown="/api/dropdowns/parent-accounts?child_level=4"
                                data-label="account"
                                data-placeholder="Select parent account"
                            ></select>
                        </div>

                        <div class="coa-field">
                            <label for="accountCode">Account code <span class="required">*</span></label>
                            <input id="accountCode" name="account_code" type="text" maxlength="50" placeholder="Example: 1114" required>
                        </div>

                        <div class="coa-field">
                            <label for="accountName">Account name <span class="required">*</span></label>
                            <input id="accountName" name="account_name" type="text" maxlength="255" placeholder="Example: Dutch Bangla Bank Account" required>
                        </div>

                        <div class="coa-field">
                            <label for="accountType">Account nature <span class="required">*</span></label>
                            <select
                                id="accountType"
                                name="account_type_id"
                                required
                                data-dropdown="/api/dropdowns/account-types"
                                data-placeholder="Select nature"
                            ></select>
                        </div>

                        <div class="coa-field">
                            <label for="normalBalance">Normal balance <span class="required">*</span></label>
                            <select id="normalBalance" name="normal_balance" required>
                                <option value="Debit">Debit</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>

                        <div class="coa-field coa-hidden">
                            <label for="postingPreview">Can transactions be posted here?</label>
                            <select id="postingPreview" disabled>
                                <option value="0">No</option>
                                <option value="1" selected>Yes</option>
                            </select>
                        </div>

                        <div class="coa-field">
                            <label for="ledgerType">What type of ledger is this? <span class="required">*</span></label>
                            <select id="ledgerType" name="ledger_type" required>
                                @foreach($ledgerTypes as $type)
                                    <option value="{{ $type }}" @selected($type === 'Asset')>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="coa-field">
                            <label for="statusSelect">Is active?</label>
                            <select id="statusSelect" name="status" required>
                                <option value="Active">Yes</option>
                                <option value="Inactive">No</option>
                            </select>
                        </div>

                        <div class="coa-field coa-hidden">
                            <label for="userSelectableSelect">Should users select this in transaction entry?</label>
                            <select id="userSelectableSelect">
                                <option value="0">No</option>
                                <option value="1" selected>Yes</option>
                            </select>
                        </div>
                    </div>

                    <div class="coa-derived-flags coa-hidden">
                        <span id="postingBadge" class="coa-badge posting">Posting: Yes</span>
                        <span id="cashBankBadge" class="coa-badge group">Cash/Bank: No</span>
                        <span id="partyControlBadge" class="coa-badge group">Party Control: No</span>
                    </div>

                    <div class="coa-advanced">
                        <button type="button" class="btn-ghost" id="advancedToggleBtn">Show / Hide Advanced Details</button>
                        <div class="coa-form-grid coa-hidden" id="advancedFields" style="margin-top:15px">
                            <div class="coa-field coa-hidden">
                                <label for="cashBankPreview">Is this a cash or bank account?</label>
                                <select id="cashBankPreview" disabled>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>

                            <div class="coa-field coa-hidden">
                                <label for="partyControlPreview">Does this track customer/supplier/employee/owner balance?</label>
                                <select id="partyControlPreview" disabled>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>

                            <div class="coa-field coa-hidden" id="partyTypeWrap">
                                <label for="partyType">Party type <span class="required">*</span></label>
                                <select
                                    id="partyType"
                                    name="party_type_id"
                                    data-dropdown="/api/dropdowns/party-types"
                                    data-placeholder="Select party type"
                                ></select>
                            </div>

                            <select id="systemLedgerSelect" class="coa-hidden" aria-hidden="true" tabindex="-1">
                                <option value="1">Yes</option>
                                <option value="0" selected>No</option>
                            </select>

                            <div class="coa-field full">
                                <label for="description">Short description</label>
                                <textarea id="description" name="description" placeholder="What is this account used for?"></textarea>
                            </div>

                            <div class="coa-field full">
                                <label for="exampleUsage">Example usage</label>
                                <textarea id="exampleUsage" name="example_usage" placeholder="Example: Bank receipt/payment"></textarea>
                            </div>
                        </div>
                    </div>

                    <div id="messages" class="coa-message-list"></div>

                    <div class="coa-actions-row">
                        <button type="button" class="btn-soft" id="clearAccountBtn">Clear</button>
                        <button type="button" class="btn-ghost" id="loadDemoBtn">Load Demo</button>
                        <button class="btn-primary" type="submit">Save Account</button>
                    </div>
                </form>
            </div>
        </div>

    </section>

    <section class="coa-card coa-section">
        <div class="coa-card-header">
            <div>
                <h2 class="coa-card-title">CoA Views</h2>
            </div>
        </div>
        <div class="coa-card-body">
            <div class="coa-tabs" role="tablist" aria-label="Chart of Accounts views">
                <button class="coa-tab active" type="button" data-coa-tab-button="tree" id="tabTree">CoA Tree</button>
                <button class="coa-tab" type="button" data-coa-tab-button="posting" id="tabPosting">Posting Ledgers</button>
                <button class="coa-tab" type="button" data-coa-tab-button="full" id="tabFull">Full CoA List</button>
            </div>

            <div id="treeView" data-coa-tab-panel="tree">
                <div class="coa-toolbar-slot" data-coa-toolbar-slot="tree">
                    <div
                        class="coa-view-toolbar {{ $canManageCoa ? 'coa-bulk-toolbar' : '' }}"
                        data-coa-view-toolbar
                        aria-label="Chart of Accounts row and bulk actions"
                        @if($canManageCoa)
                            data-coa-bulk-toolbar
                            data-delete-url="{{ route('setup.chart-of-accounts.bulk-destroy') }}"
                        @endif
                    >
                        @if($canManageCoa)
                            <div class="coa-bulk-selection">
                                <label class="coa-select-control" data-coa-select-control title="Select all visible accounts">
                                    <input type="checkbox" id="coaSelectVisible" aria-label="Select all visible accounts" title="Select all visible accounts">
                                </label>
                                <span class="coa-selected-count" id="coaSelectedCount">0 selected</span>
                                <div class="coa-bulk-actions">
                                    <button type="button" class="btn-ghost delete-btn coa-bulk-delete" id="coaDeleteSelected" disabled>Delete selected</button>
                                    <button type="button" class="btn-ghost coa-bulk-clear" id="coaClearSelected" disabled>Clear</button>
                                </div>
                            </div>
                        @endif

                        <div class="coa-load-group">
                            <div class="coa-list-summary" id="coaVisibleSummary">Showing records as you scroll.</div>
                            <div class="coa-load-control">
                                <label for="coaLoadSize">Rows</label>
                                <select id="coaLoadSize">
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                    <option value="150">150</option>
                                    <option value="200">200</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="coa-tree" id="tree">
                    @forelse($accountRows as $row)
                        <div
                            class="coa-tree-node coa-lvl{{ $row['coa_level'] }}"
                            data-coa-tree-row
                            data-coa-account-id="{{ $row['id'] }}"
                        >
                            @if($canManageCoa)
                                <label class="coa-select-control" data-coa-select-control title="Select this CoA branch">
                                    <input type="checkbox" value="{{ $row['id'] }}" data-coa-select data-select-tab="tree" aria-label="Select {{ $row['display_name'] }}">
                                </label>
                            @endif
                            <div class="coa-tree-content" data-edit-row-id="{{ $row['id'] }}" role="button" tabindex="0">
                                <div class="coa-tree-name">{{ $row['account_code'] }} · {{ $row['account_name'] }}</div>
                                <div class="coa-tree-meta">Level {{ $row['coa_level'] }} · {{ $row['level_name'] }}{{ $row['parent_name'] ? ' · Under ' . $row['parent_name'] : '' }}</div>
                                <div>
                                    <span class="coa-badge {{ $row['posting_allowed'] ? 'posting' : 'group' }}">{{ $row['ledger_type'] ?: 'Group' }}</span>
                                    @if($row['posting_allowed'])<span class="coa-badge posting">Posting</span>@endif
                                    @if($row['ledger_type'] === 'Cash')<span class="coa-badge cash">Cash</span>@endif
                                    @if($row['ledger_type'] === 'Bank')<span class="coa-badge bank">Bank</span>@endif
                                    @if($row['is_party_control'])<span class="coa-badge party">Party Control</span>@endif
                                    @if($row['is_system_ledger'])<span class="coa-badge system">System</span>@endif
                                    <span class="coa-badge {{ $row['status'] === 'Active' ? '' : 'inactive' }}">{{ $row['status'] }}</span>
                                </div>
                            </div>
                            <div class="coa-action-stack coa-tree-actions">
                                <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
                                <form method="POST" action="{{ $row['delete_url'] }}" data-delete-form data-coa-delete-form data-account-id="{{ $row['id'] }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_tab" value="tree">
                                    <button type="submit" class="btn-ghost coa-small-btn delete-btn">
                                        {{ $row['coa_level'] < 4 ? 'Delete Branch' : 'Delete' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="coa-table-empty">No accounts found. Use the guided form to create the first CoA account.</div>
                    @endforelse
                </div>
            </div>

            <div id="postingView" class="coa-hidden" data-coa-tab-panel="posting">
                <div class="coa-toolbar-slot" data-coa-toolbar-slot="posting"></div>
                <div class="coa-table-wrap">
                    <table id="postingCoaTable" data-no-client-pagination="true">
                        <thead>
                            <tr>
                                @if($canManageCoa)<th class="coa-select-cell">Select</th>@endif
                                <th>CoA Code</th>
                                <th>Ledger Name</th>
                                <th>Class</th>
                                <th>Group</th>
                                <th>Sub-Group</th>
                                <th>Ledger Type</th>
                                <th>Normal</th>
                                <th>Cash/Bank</th>
                                <th>Party Control</th>
                                <th>Party Type</th>
                                <th>User Selectable</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accountRows->where('posting_allowed', true) as $row)
                                <tr data-posting-row data-coa-account-id="{{ $row['id'] }}" data-edit-row-id="{{ $row['id'] }}">
                                    @if($canManageCoa)
                                        <td class="coa-select-cell">
                                            <label class="coa-select-control" data-coa-select-control>
                                                <input type="checkbox" value="{{ $row['id'] }}" data-coa-select data-select-tab="posting" aria-label="Select {{ $row['display_name'] }}">
                                            </label>
                                        </td>
                                    @endif
                                    <td class="code">{{ $row['account_code'] }}</td>
                                    <td><strong>{{ $row['account_name'] }}</strong></td>
                                    <td>{{ $row['account_class'] ?: '—' }}</td>
                                    <td>{{ $row['account_group'] ?: '—' }}</td>
                                    <td>{{ $row['account_sub_group'] ?: '—' }}</td>
                                    <td><span class="coa-badge posting">{{ $row['ledger_type'] ?: '—' }}</span></td>
                                    <td>{{ $row['normal_balance'] ?: '—' }}</td>
                                    <td>{{ $row['is_cash_bank'] ? 'Yes' : 'No' }}</td>
                                    <td>{{ $row['is_party_control'] ? 'Yes' : 'No' }}</td>
                                    <td>{{ $row['party_type_name'] ?: '—' }}</td>
                                    <td>{{ $row['is_user_selectable'] ? 'Yes' : 'No' }}</td>
                                    <td>{{ $row['status'] }}</td>
                                    <td>
                                        <div class="coa-action-stack">
                                            <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
                                            <form method="POST" action="{{ $row['delete_url'] }}" data-delete-form data-coa-delete-form data-account-id="{{ $row['id'] }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_tab" value="posting">
                                                <button type="submit" class="btn-ghost coa-small-btn delete-btn">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManageCoa ? 14 : 13 }}" class="coa-table-empty">No posting ledgers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="coa-mobile-list">
                    @foreach($accountRows->where('posting_allowed', true) as $row)
                        <div class="coa-mobile-item" data-posting-mobile-row data-coa-account-id="{{ $row['id'] }}">
                            <div class="coa-mobile-top">
                                <div>
                                    <div class="coa-mobile-title">{{ $row['account_code'] }} · {{ $row['account_name'] }}</div>
                                    <div class="coa-mobile-meta">Level {{ $row['coa_level'] }} · {{ $row['level_name'] }}</div>
                                </div>
                                <div class="coa-action-stack">
                                    @if($canManageCoa)
                                        <label class="coa-select-control" data-coa-select-control>
                                            <input type="checkbox" value="{{ $row['id'] }}" data-coa-select data-select-tab="posting" aria-label="Select {{ $row['display_name'] }}">
                                        </label>
                                    @endif
                                    <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
                                    <form method="POST" action="{{ $row['delete_url'] }}" data-delete-form data-coa-delete-form data-account-id="{{ $row['id'] }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return_tab" value="posting">
                                        <button type="submit" class="btn-ghost coa-small-btn delete-btn">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <div>
                                <span class="coa-badge posting">{{ $row['ledger_type'] ?: 'Posting' }}</span>
                                @if($row['is_cash_bank'])<span class="coa-badge bank">Cash/Bank</span>@endif
                                @if($row['is_party_control'])<span class="coa-badge party">Party Control</span>@endif
                                @if($row['is_system_ledger'])<span class="coa-badge system">System</span>@endif
                            </div>
                            <div class="coa-mobile-lines">
                                <div><strong>Class:</strong> {{ $row['account_class'] ?: '—' }}</div>
                                <div><strong>Group:</strong> {{ $row['account_group'] ?: '—' }}</div>
                                <div><strong>Sub-Group:</strong> {{ $row['account_sub_group'] ?: '—' }}</div>
                                <div><strong>Party:</strong> {{ $row['party_type_name'] ?: '—' }}</div>
                                <div><strong>Status:</strong> {{ $row['status'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="fullView" class="coa-hidden" data-coa-tab-panel="full">
                <div class="coa-filter-grid" id="fullFilterRoot">
                    <input id="search" type="search" placeholder="Search by code or account name">
                    <select id="fClass">
                        <option value="">All Classes</option>
                        @foreach($accountRows->pluck('account_class')->filter()->unique()->sort()->values() as $class)
                            <option value="{{ $class }}">{{ $class }}</option>
                        @endforeach
                    </select>
                    <select id="fLevel">
                        <option value="">All Levels</option>
                        @foreach($coaLevels as $level => $label)
                            <option value="{{ $level }}">Level {{ $level }}</option>
                        @endforeach
                    </select>
                    <select id="fLedger">
                        <option value="">All Types</option>
                        @foreach($ledgerTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select id="fPosting">
                        <option value="">Posting?</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>

                <div class="coa-toolbar-slot" data-coa-toolbar-slot="full"></div>

                <div class="coa-table-wrap">
                    <table id="fullCoaTable" data-no-client-pagination="true">
                        <thead>
                            <tr>
                                @if($canManageCoa)<th class="coa-select-cell">Select</th>@endif
                                <th>CoA Code</th>
                                <th>Account Name</th>
                                <th>Level</th>
                                <th>Parent</th>
                                <th>Class</th>
                                <th>Group</th>
                                <th>Sub-Group</th>
                                <th>Normal</th>
                                <th>Posting</th>
                                <th>Ledger Type</th>
                                <th>Party Type</th>
                                <th>Active</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accountRows as $row)
                                <tr
                                    data-full-row
                                    data-coa-account-id="{{ $row['id'] }}"
                                    data-search="{{ strtolower($row['account_code'] . ' ' . $row['account_name'] . ' ' . ($row['parent_name'] ?? '') . ' ' . ($row['account_group'] ?? '') . ' ' . ($row['account_sub_group'] ?? '')) }}"
                                    data-class="{{ $row['account_class'] }}"
                                    data-level="{{ $row['coa_level'] }}"
                                    data-ledger="{{ $row['ledger_type'] }}"
                                    data-posting="{{ $row['posting_allowed'] ? 'Yes' : 'No' }}"
                                    data-edit-row-id="{{ $row['id'] }}"
                                >
                                    @if($canManageCoa)
                                        <td class="coa-select-cell">
                                            <label class="coa-select-control" data-coa-select-control>
                                                <input type="checkbox" value="{{ $row['id'] }}" data-coa-select data-select-tab="full" aria-label="Select {{ $row['display_name'] }}">
                                            </label>
                                        </td>
                                    @endif
                                    <td class="code">{{ $row['account_code'] }}</td>
                                    <td>
                                        <strong>{{ $row['account_name'] }}</strong>
                                        @if($row['description'])<br><span class="coa-hint">{{ $row['description'] }}</span>@endif
                                    </td>
                                    <td>{{ $row['coa_level'] }}</td>
                                    <td>{{ $row['parent_display_name'] ?: '—' }}</td>
                                    <td>{{ $row['account_class'] ?: '—' }}</td>
                                    <td>{{ $row['account_group'] ?: '—' }}</td>
                                    <td>{{ $row['account_sub_group'] ?: '—' }}</td>
                                    <td>{{ $row['normal_balance'] ?: '—' }}</td>
                                    <td>{{ $row['posting_allowed'] ? 'Yes' : 'No' }}</td>
                                    <td><span class="coa-badge {{ $row['posting_allowed'] ? 'posting' : 'group' }}">{{ $row['ledger_type'] ?: '—' }}</span></td>
                                    <td>{{ $row['party_type_name'] ?: '—' }}</td>
                                    <td>{{ $row['status'] === 'Active' ? 'Yes' : 'No' }}</td>
                                    <td>
                                        <div class="coa-action-stack">
                                            <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
                                            <form method="POST" action="{{ $row['delete_url'] }}" data-delete-form data-coa-delete-form data-account-id="{{ $row['id'] }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_tab" value="full">
                                                <button type="submit" class="btn-ghost coa-small-btn delete-btn">
                                                    {{ $row['coa_level'] < 4 ? 'Delete Branch' : 'Delete' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManageCoa ? 14 : 13 }}" class="coa-table-empty">No accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="coa-mobile-list" id="fullMobileList">
                    @foreach($accountRows as $row)
                        <div
                            class="coa-mobile-item"
                            data-full-mobile-row
                            data-coa-account-id="{{ $row['id'] }}"
                            data-search="{{ strtolower($row['account_code'] . ' ' . $row['account_name'] . ' ' . ($row['parent_name'] ?? '') . ' ' . ($row['account_group'] ?? '') . ' ' . ($row['account_sub_group'] ?? '')) }}"
                            data-class="{{ $row['account_class'] }}"
                            data-level="{{ $row['coa_level'] }}"
                            data-ledger="{{ $row['ledger_type'] }}"
                            data-posting="{{ $row['posting_allowed'] ? 'Yes' : 'No' }}"
                        >
                            <div class="coa-mobile-top">
                                <div>
                                    <div class="coa-mobile-title">{{ $row['account_code'] }} · {{ $row['account_name'] }}</div>
                                    <div class="coa-mobile-meta">Level {{ $row['coa_level'] }} · {{ $row['level_name'] }}</div>
                                </div>
                                <div class="coa-action-stack">
                                    @if($canManageCoa)
                                        <label class="coa-select-control" data-coa-select-control>
                                            <input type="checkbox" value="{{ $row['id'] }}" data-coa-select data-select-tab="full" aria-label="Select {{ $row['display_name'] }}">
                                        </label>
                                    @endif
                                    <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
                                    <form method="POST" action="{{ $row['delete_url'] }}" data-delete-form data-coa-delete-form data-account-id="{{ $row['id'] }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return_tab" value="full">
                                        <button type="submit" class="btn-ghost coa-small-btn delete-btn">
                                            {{ $row['coa_level'] < 4 ? 'Delete Branch' : 'Delete' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div>
                                <span class="coa-badge {{ $row['posting_allowed'] ? 'posting' : 'group' }}">{{ $row['ledger_type'] ?: 'Group' }}</span>
                                @if($row['posting_allowed'])<span class="coa-badge posting">Posting</span>@endif
                                @if($row['is_party_control'])<span class="coa-badge party">Party Control</span>@endif
                                @if($row['is_system_ledger'])<span class="coa-badge system">System</span>@endif
                            </div>
                            <div class="coa-mobile-lines">
                                <div><strong>Parent:</strong> {{ $row['parent_display_name'] ?: '—' }}</div>
                                <div><strong>Class:</strong> {{ $row['account_class'] ?: '—' }}</div>
                                <div><strong>Normal:</strong> {{ $row['normal_balance'] ?: '—' }}</div>
                                <div><strong>Posting:</strong> {{ $row['posting_allowed'] ? 'Yes' : 'No' }}</div>
                                <div><strong>Party:</strong> {{ $row['party_type_name'] ?: '—' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const importForm = document.querySelector('[data-coa-import-form]');
    if (importForm) {
        const importFile = importForm.querySelector('[data-coa-import-file]');
        const importSubmit = importForm.querySelector('[data-coa-import-submit]');
        const importProgress = importForm.querySelector('[data-coa-import-progress]');
        const importStatus = importForm.querySelector('[data-coa-import-status]');
        const importPercent = importForm.querySelector('[data-coa-import-percent]');
        const importDetail = importForm.querySelector('[data-coa-import-detail]');
        const importTrack = importForm.querySelector('[data-coa-import-track]');
        const importBar = importForm.querySelector('[data-coa-import-bar]');
        let processingTimer = null;
        let currentProgress = 0;

        function setImportProgress(value, status, detail) {
            currentProgress = Math.max(currentProgress, Math.min(100, Math.round(value)));
            if (importProgress) {
                importProgress.classList.add('is-active');
                importProgress.setAttribute('aria-hidden', 'false');
            }
            if (importStatus && status) importStatus.textContent = status;
            if (importDetail && detail) importDetail.textContent = detail;
            if (importPercent) importPercent.textContent = `${currentProgress}%`;
            if (importTrack) importTrack.setAttribute('aria-valuenow', String(currentProgress));
            if (importBar) importBar.style.width = `${currentProgress}%`;
        }

        function stopProcessingTimer() {
            if (processingTimer) {
                clearInterval(processingTimer);
                processingTimer = null;
            }
        }

        function startProcessingTimer() {
            stopProcessingTimer();
            processingTimer = setInterval(() => {
                if (currentProgress < 94) {
                    setImportProgress(currentProgress + 1, 'Importing rows...', 'The server is validating hierarchy, duplicates, and accounting rules.');
                }
            }, 650);
        }

        importFile?.addEventListener('change', () => {
            const fileName = importFile.files?.[0]?.name || 'Selected file';
            currentProgress = 0;
            setImportProgress(0, 'Ready to import', fileName);
        });

        importForm.addEventListener('submit', (event) => {
            if (!window.XMLHttpRequest || !window.FormData) {
                return;
            }

            event.preventDefault();

            if (!importFile?.files?.length) {
                importFile?.reportValidity?.();
                return;
            }

            stopProcessingTimer();
            currentProgress = 0;
            importForm.classList.add('is-importing');
            importSubmit?.setAttribute('disabled', 'disabled');

            const formData = new FormData(importForm);
            const xhr = new XMLHttpRequest();
            const fileName = importFile.files[0]?.name || 'selected file';

            setImportProgress(1, 'Starting import...', `Preparing ${fileName}.`);

            xhr.upload.addEventListener('progress', (progressEvent) => {
                if (!progressEvent.lengthComputable) {
                    setImportProgress(8, 'Uploading file...', 'Uploading file to the server.');
                    return;
                }

                const uploadPercent = Math.round((progressEvent.loaded / progressEvent.total) * 65);
                setImportProgress(Math.max(1, uploadPercent), 'Uploading file...', `${Math.round((progressEvent.loaded / progressEvent.total) * 100)}% of the file uploaded.`);
            });

            xhr.upload.addEventListener('load', () => {
                setImportProgress(70, 'File uploaded', 'Server import has started.');
                startProcessingTimer();
            });

            xhr.addEventListener('load', () => {
                stopProcessingTimer();

                if (xhr.status >= 200 && xhr.status < 300) {
                    let response = {};
                    try {
                        response = JSON.parse(xhr.responseText || '{}');
                    } catch (error) {
                        response = {};
                    }

                    const resultParts = [];
                    if (typeof response.created !== 'undefined') resultParts.push(`Created: ${response.created}`);
                    if (typeof response.skipped !== 'undefined') resultParts.push(`Skipped/review: ${response.skipped}`);

                    setImportProgress(100, 'Import completed', resultParts.join(' · ') || 'Redirecting to the updated Chart of Accounts.');

                    window.setTimeout(() => {
                        window.location.assign(response.redirect_url || importForm.dataset.successUrl || window.location.href);
                    }, 500);
                    return;
                }

                let errorMessage = 'Please check the selected file and try again.';
                try {
                    const errorResponse = JSON.parse(xhr.responseText || '{}');
                    const validationErrors = errorResponse.errors ? Object.values(errorResponse.errors).flat() : [];
                    errorMessage = validationErrors[0] || errorResponse.message || errorMessage;
                } catch (error) {
                    // Keep the generic message when the response is not JSON.
                }

                importForm.classList.remove('is-importing');
                importSubmit?.removeAttribute('disabled');
                setImportProgress(100, 'Import failed', errorMessage);
            });

            xhr.addEventListener('error', () => {
                stopProcessingTimer();
                importForm.classList.remove('is-importing');
                importSubmit?.removeAttribute('disabled');
                setImportProgress(100, 'Network error', 'The file could not be uploaded. Check your connection and try again.');
            });

            xhr.open('POST', importForm.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.addEventListener('readystatechange', () => {
                if (xhr.readyState === XMLHttpRequest.HEADERS_RECEIVED || xhr.readyState === XMLHttpRequest.LOADING) {
                    if (currentProgress < 70) {
                        setImportProgress(70, 'File uploaded', 'Server import has started.');
                    }
                    startProcessingTimer();
                }
            });
            xhr.send(formData);
        });
    }

    document.querySelectorAll('[data-discard-import-review]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! confirm('Discard the pending Chart of Accounts import review? These skipped rows will not be imported and this popup will not appear again unless you import another file.')) {
                event.preventDefault();
            }
        });
    });

    const accountRows = @json($accountRows->keyBy('id')->toArray());
    const levelMap = @json($coaLevels);
    const normalByNature = { Asset: 'Debit', Expense: 'Debit', 'Equity Contra': 'Debit', Liability: 'Credit', Equity: 'Credit', Income: 'Credit', "Owner's Equity": 'Credit', 'Owner’s Equity': 'Credit' };
    let activeCoaTab = 'tree';
    let visibleLimit = Number(document.getElementById('coaLoadSize')?.value || 50);
    const validCoaTabs = ['tree', 'posting', 'full'];
    const selectedCoaIds = {
        tree: new Set(),
        posting: new Set(),
        full: new Set(),
    };
    const viewToolbar = document.querySelector('[data-coa-view-toolbar]');
    const bulkToolbar = document.querySelector('[data-coa-bulk-toolbar]');
    const selectVisibleCheckbox = document.getElementById('coaSelectVisible');
    const selectedCountLabel = document.getElementById('coaSelectedCount');
    const clearSelectedButton = document.getElementById('coaClearSelected');
    const deleteSelectedButton = document.getElementById('coaDeleteSelected');

    function showCoaToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function showCoaReassignmentNotice(result) {
        if (!result?.reassignment_message) return;

        alert(result.reassignment_message);
    }

    function currentCoaSelection() {
        return selectedCoaIds[activeCoaTab] || selectedCoaIds.tree;
    }

    function syncCoaSelectionCheckboxes(tabName, accountId, checked) {
        document.querySelectorAll(`[data-coa-select][data-select-tab="${tabName}"][value="${accountId}"]`).forEach((checkbox) => {
            checkbox.checked = checked;
        });
    }

    function visibleAccountIdsForSelection() {
        return activeRowsFor(activeCoaTab)
            .filter((row) => row.isConnected && row.style.display !== 'none' && row.dataset.filterMatch !== '0')
            .map((row) => String(row.dataset.coaAccountId || ''))
            .filter(Boolean);
    }

    function refreshBulkSelectionUi() {
        if (!bulkToolbar) return;

        const selection = currentCoaSelection();
        const visibleIds = Array.from(new Set(visibleAccountIdsForSelection()));
        const selectedVisibleCount = visibleIds.filter((id) => selection.has(id)).length;
        const selectedCount = selection.size;

        if (selectedCountLabel) {
            selectedCountLabel.textContent = `${selectedCount} selected in ${activeCoaTab === 'tree' ? 'CoA Tree' : (activeCoaTab === 'posting' ? 'Posting Ledgers' : 'Full CoA List')}`;
        }

        if (selectVisibleCheckbox) {
            selectVisibleCheckbox.checked = visibleIds.length > 0 && selectedVisibleCount === visibleIds.length;
            selectVisibleCheckbox.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleIds.length;
            selectVisibleCheckbox.disabled = visibleIds.length === 0;
        }

        if (clearSelectedButton) clearSelectedButton.disabled = selectedCount === 0;
        if (deleteSelectedButton) deleteSelectedButton.disabled = selectedCount === 0;
    }

    function clearDeletedCoaSelections(deletedIds) {
        const ids = Array.from(new Set((deletedIds || []).map((id) => String(id))));

        Object.entries(selectedCoaIds).forEach(([tabName, selection]) => {
            ids.forEach((id) => {
                selection.delete(id);
                syncCoaSelectionCheckboxes(tabName, id, false);
            });
        });

        refreshBulkSelectionUi();
    }

    async function submitBulkCoaDelete(accountIds, confirmed = false) {
        if (!bulkToolbar?.dataset.deleteUrl) {
            throw new Error('Bulk deletion is not available for this user.');
        }

        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        formData.set('_token', csrfToken);
        formData.set('_method', 'DELETE');
        formData.set('return_tab', activeCoaTab);
        accountIds.forEach((id) => formData.append('account_ids[]', id));
        if (confirmed) formData.set('confirm_cascade', '1');

        const response = await fetch(bulkToolbar.dataset.deleteUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 409 && data.requires_confirmation) {
            const approved = confirm(data.confirmation_message || data.message || 'Delete all selected Chart of Accounts branches?');
            if (!approved) return { cancelled: true };

            return submitBulkCoaDelete(accountIds, true);
        }

        if (!response.ok || data.success === false) {
            const validationMessage = data?.errors
                ? Object.values(data.errors).flat().join('\n')
                : '';
            throw new Error(validationMessage || data.message || 'Selected Chart of Accounts records could not be deleted.');
        }

        return data;
    }

    document.querySelectorAll('[data-coa-select]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const tabName = checkbox.dataset.selectTab;
            const accountId = String(checkbox.value || '');
            const selection = selectedCoaIds[tabName];

            if (!selection || !accountId) return;

            if (checkbox.checked) selection.add(accountId);
            else selection.delete(accountId);

            syncCoaSelectionCheckboxes(tabName, accountId, checkbox.checked);
            refreshBulkSelectionUi();
        });
    });

    selectVisibleCheckbox?.addEventListener('change', () => {
        const selection = currentCoaSelection();
        const shouldSelect = selectVisibleCheckbox.checked;

        visibleAccountIdsForSelection().forEach((accountId) => {
            if (shouldSelect) selection.add(accountId);
            else selection.delete(accountId);

            syncCoaSelectionCheckboxes(activeCoaTab, accountId, shouldSelect);
        });

        refreshBulkSelectionUi();
    });

    clearSelectedButton?.addEventListener('click', () => {
        const selection = currentCoaSelection();
        Array.from(selection).forEach((accountId) => syncCoaSelectionCheckboxes(activeCoaTab, accountId, false));
        selection.clear();
        refreshBulkSelectionUi();
    });

    deleteSelectedButton?.addEventListener('click', async () => {
        const selectedIds = Array.from(currentCoaSelection());
        if (selectedIds.length === 0) return;

        const originalText = deleteSelectedButton.textContent;
        deleteSelectedButton.disabled = true;
        deleteSelectedButton.textContent = 'Checking selection…';

        try {
            const result = await submitBulkCoaDelete(selectedIds, false);
            if (result?.cancelled) return;

            removeDeletedCoaRecords(result.deleted_ids || selectedIds);
            showCoaToast(result.message || 'Selected Chart of Accounts records were deleted successfully.');
            showCoaReassignmentNotice(result);
        } catch (error) {
            showCoaToast(error.message || 'Selected Chart of Accounts records could not be deleted.');
        } finally {
            if (deleteSelectedButton?.isConnected) {
                deleteSelectedButton.textContent = originalText;
                refreshBulkSelectionUi();
            }
        }
    });

    function updateCoaStat(name, decreaseBy) {
        const element = document.querySelector(`[data-coa-stat="${name}"]`);
        if (!element) return;

        const current = Number(element.textContent || 0);
        element.textContent = String(Math.max(0, current - Number(decreaseBy || 0)));
    }

    function updateCoaStatsForDeletedRows(deletedRows) {
        updateCoaStat('total', deletedRows.length);
        updateCoaStat('posting', deletedRows.filter((row) => Boolean(row?.posting_allowed)).length);
        updateCoaStat('groups', deletedRows.filter((row) => !Boolean(row?.posting_allowed)).length);
        updateCoaStat('cash_bank', deletedRows.filter((row) => Boolean(row?.is_cash_bank)).length);
        updateCoaStat('party_control', deletedRows.filter((row) => Boolean(row?.is_party_control)).length);
        updateCoaStat('active', deletedRows.filter((row) => row?.status === 'Active').length);
    }

    function addEmptyCoaState(container, message, isTable = false) {
        if (!container || container.querySelector('[data-coa-generated-empty]')) return;

        if (isTable) {
            const row = document.createElement('tr');
            row.dataset.coaGeneratedEmpty = '1';
            const columnCount = container.closest('table')?.querySelectorAll('thead th').length || 13;
            row.innerHTML = `<td colspan="${columnCount}" class="coa-table-empty">${message}</td>`;
            container.appendChild(row);
            return;
        }

        const item = document.createElement('div');
        item.dataset.coaGeneratedEmpty = '1';
        item.className = 'coa-table-empty';
        item.textContent = message;
        container.appendChild(item);
    }

    function refreshCoaEmptyStates() {
        const tree = document.getElementById('tree');
        if (tree && !tree.querySelector('[data-coa-tree-row]')) {
            addEmptyCoaState(tree, 'No accounts found. Use the guided form to create the first CoA account.');
        }

        const fullBody = document.querySelector('#fullCoaTable tbody');
        if (fullBody && !fullBody.querySelector('[data-full-row]')) {
            fullBody.querySelectorAll('tr:not([data-full-row])').forEach((item) => {
                if (item.querySelector('.coa-table-empty')) item.remove();
            });
            addEmptyCoaState(fullBody, 'No accounts found.', true);
        }

        const postingBody = document.querySelector('#postingCoaTable tbody');
        if (postingBody && !postingBody.querySelector('[data-posting-row]')) {
            postingBody.querySelectorAll('tr:not([data-posting-row])').forEach((item) => {
                if (item.querySelector('.coa-table-empty')) item.remove();
            });
            addEmptyCoaState(postingBody, 'No posting ledgers found.', true);
        }

        const fullMobile = document.getElementById('fullMobileList');
        if (fullMobile && !fullMobile.querySelector('[data-full-mobile-row]')) {
            addEmptyCoaState(fullMobile, 'No accounts found.');
        }
    }

    function removeDeletedCoaRecords(deletedIds) {
        const normalizedIds = Array.from(new Set((deletedIds || []).map((id) => String(id))));
        const deletedRows = normalizedIds.map((id) => accountRows[id]).filter(Boolean);

        normalizedIds.forEach((id) => {
            document.querySelectorAll(`[data-coa-account-id="${id}"]`).forEach((element) => element.remove());
            delete accountRows[id];
        });

        updateCoaStatsForDeletedRows(deletedRows);
        clearDeletedCoaSelections(normalizedIds);
        refreshCoaEmptyStates();

        if (typeof applyFullFilters === 'function') applyFullFilters(false);
        if (typeof applyProgressiveLimit === 'function') applyProgressiveLimit(true);
    }

    async function submitCoaDelete(form, confirmed = false) {
        const formData = new FormData(form);
        if (confirmed) formData.set('confirm_cascade', '1');

        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 409 && data.requires_confirmation) {
            const approved = confirm(data.confirmation_message || data.message || 'Delete this complete Chart of Accounts branch?');
            if (!approved) return { cancelled: true };

            return submitCoaDelete(form, true);
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Chart of Accounts deletion failed.');
        }

        return data;
    }

    document.querySelectorAll('form[data-coa-delete-form]').forEach((deleteForm) => {
        deleteForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const submitButton = event.submitter || deleteForm.querySelector('button[type="submit"]');
            const originalText = submitButton?.textContent || 'Delete';

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Deleting…';
            }

            try {
                const result = await submitCoaDelete(deleteForm, false);

                if (result?.cancelled) return;

                removeDeletedCoaRecords(result.deleted_ids || [deleteForm.dataset.accountId]);
                showCoaToast(result.message || 'Chart of Accounts record deleted successfully.');
                showCoaReassignmentNotice(result);
            } catch (error) {
                showCoaToast(error.message || 'Chart of Accounts deletion failed.');
            } finally {
                if (submitButton?.isConnected) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }
        });
    });

    const form = document.getElementById('accountForm');
    if (!form) return;

    const formTitle = document.getElementById('accountFormTitle');
    const methodInput = document.getElementById('accountFormMethod');
    const coaLevel = document.getElementById('coaLevel');
    const parentAccount = document.getElementById('parentAccount');
    const parentWrap = document.getElementById('parentWrap');
    const accountCode = document.getElementById('accountCode');
    const accountName = document.getElementById('accountName');
    const accountType = document.getElementById('accountType');
    const normalBalance = document.getElementById('normalBalance');
    const ledgerType = document.getElementById('ledgerType');
    const statusSelect = document.getElementById('statusSelect');
    const userSelectableSelect = document.getElementById('userSelectableSelect');
    const postingPreview = document.getElementById('postingPreview');
    const cashBankPreview = document.getElementById('cashBankPreview');
    const partyControlPreview = document.getElementById('partyControlPreview');
    const partyType = document.getElementById('partyType');
    const partyTypeWrap = document.getElementById('partyTypeWrap');
    const systemLedgerSelect = document.getElementById('systemLedgerSelect');
    const description = document.getElementById('description');
    const exampleUsage = document.getElementById('exampleUsage');
    const accountLevelHidden = document.getElementById('accountLevelHidden');
    const postingAllowed = document.getElementById('postingAllowed');
    const isCashBank = document.getElementById('isCashBank');
    const isPartyControl = document.getElementById('isPartyControl');
    const accountNatureHidden = document.getElementById('accountNatureHidden');
    const accountGroupHidden = document.getElementById('accountGroupHidden');
    const accountSubGroupHidden = document.getElementById('accountSubGroupHidden');
    const isSystemLedger = document.getElementById('isSystemLedger');
    const isUserSelectable = document.getElementById('isUserSelectable');
    const postingBadge = document.getElementById('postingBadge');
    const cashBankBadge = document.getElementById('cashBankBadge');
    const partyControlBadge = document.getElementById('partyControlBadge');
    const messages = document.getElementById('messages');
    const advancedFields = document.getElementById('advancedFields');
    const guidanceText = document.getElementById('guidanceText');
    let normalBalanceManuallyChanged = false;

    function selectedOption(select) {
        return select?.selectedOptions?.[0] || null;
    }

    function selectedTypeName() {
        return selectedOption(accountType)?.dataset.name || selectedOption(accountType)?.textContent || '';
    }

    function selectedNormalBalance() {
        return selectedOption(accountType)?.dataset.normalBalance || normalByNature[selectedTypeName()] || '';
    }

    function setBadge(badge, enabled, label, activeClass = 'posting') {
        badge.textContent = `${label}: ${enabled ? 'Yes' : 'No'}`;
        badge.classList.remove('posting', 'group', 'party');
        badge.classList.add(enabled ? activeClass : 'group');
    }

    function setGuidance() {
        if (!guidanceText) return;

        const level = Number(coaLevel.value || 4);
        const type = ledgerType.value;
        let text = 'You are setting up the accounting structure. Create groups for reporting and ledger heads for transaction posting.';

        if (level === 4) text = 'This is a posting account. It can be used in transaction entry and accounting rules.';
        if (level && level !== 4) text = 'This is only for structure and reports. Transactions should not be posted directly here.';
        if (type === 'Party Control') text = 'This ledger will hold balances for customers, suppliers, employees, or owners. The actual party is selected during transaction entry.';
        if (type === 'Cash' || type === 'Bank') text = 'This ledger can be selected in transaction entry when money is received or paid.';

        guidanceText.textContent = text;
    }

    function reloadParentDropdown(selectedValue = '') {
        const level = Number(coaLevel.value || 4);
        const baseUrl = parentAccount.dataset.baseUrl || '/api/dropdowns/parent-accounts';
        const params = new URLSearchParams();

        if (level > 1) params.set('child_level', String(level));
        if (accountType.value) params.set('account_type_id', accountType.value);
        if (parentAccount.dataset.excludeId) params.set('exclude_id', parentAccount.dataset.excludeId);

        parentAccount.dataset.dropdown = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
        parentAccount.dataset.selected = selectedValue || parentAccount.dataset.selected || '';

        if (window.AccountingUI?.loadSelect) {
            window.AccountingUI.loadSelect(parentAccount).then(() => {
                parentAccount.value = selectedValue || parentAccount.dataset.selected || '';
            });
        }
    }

    function syncSmartFields(options = {}) {
        const level = Number(coaLevel.value || 4);
        const isPostingLevel = level === 4;
        const typeName = selectedTypeName();
        const balance = selectedNormalBalance();
        let normalizedLedgerType = isPostingLevel ? (ledgerType.value || 'Asset') : 'Group';

        if (isPostingLevel && normalizedLedgerType === 'Group') {
            normalizedLedgerType = 'Asset';
        }

        const cashBank = ['Cash', 'Bank'].includes(normalizedLedgerType);
        const partyControl = normalizedLedgerType === 'Party Control';

        accountLevelHidden.value = isPostingLevel ? 'Ledger' : 'Group';
        postingAllowed.value = isPostingLevel ? '1' : '0';
        postingPreview.value = isPostingLevel ? '1' : '0';
        ledgerType.value = normalizedLedgerType;
        ledgerType.disabled = !isPostingLevel;
        if (options.forceNormalBalance || !normalBalanceManuallyChanged || !normalBalance.value) {
            normalBalance.value = balance || normalBalance.value || 'Debit';
        }
        accountNatureHidden.value = typeName;

        isCashBank.value = cashBank ? '1' : '0';
        cashBankPreview.value = cashBank ? '1' : '0';
        isPartyControl.value = partyControl ? '1' : '0';
        partyControlPreview.value = partyControl ? '1' : '0';

        parentWrap.classList.toggle('coa-hidden', level === 1);
        parentAccount.required = level > 1;
        if (level === 1) {
            parentAccount.value = '';
            parentAccount.dataset.selected = '';
        }

        partyTypeWrap.classList.toggle('coa-hidden', !partyControl);
        partyType.required = partyControl;
        if (!partyControl) {
            partyType.value = '';
            partyType.dataset.selected = '';
        }

        const selectableLocked = !isPostingLevel || partyControl;
        userSelectableSelect.disabled = selectableLocked;
        if (selectableLocked) {
            userSelectableSelect.value = '0';
        } else if (!['0', '1'].includes(userSelectableSelect.value)) {
            userSelectableSelect.value = cashBank ? '1' : '0';
        }

        isUserSelectable.value = userSelectableSelect.value;
        isSystemLedger.value = systemLedgerSelect.value;

        setBadge(postingBadge, isPostingLevel, 'Posting');
        setBadge(cashBankBadge, cashBank, 'Cash/Bank');
        setBadge(partyControlBadge, partyControl, 'Party Control', 'party');
        setGuidance();
    }

    function setDropdownValue(select, value) {
        if (!select) return Promise.resolve();
        select.dataset.selected = value || '';
        select.value = value || '';

        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            return window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncSmartFields();
            });
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncSmartFields();
        return Promise.resolve();
    }

    function showMessages(errors) {
        messages.innerHTML = '';
        if (!errors.length) return;

        messages.innerHTML = errors.map((error) => `<div class="coa-message error">${error}</div>`).join('');
        messages.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function validateFormBeforeSubmit() {
        const errors = [];
        const level = Number(coaLevel.value || 0);
        const partyControl = ledgerType.value === 'Party Control';

        if (!level) errors.push('Please select what you are creating.');
        if (!accountCode.value.trim()) errors.push('Please enter account code.');
        if (!accountName.value.trim()) errors.push('Please enter account name.');
        if (!accountType.value) errors.push('Please select account nature.');
        if (level > 1 && !parentAccount.value) errors.push('Please select parent account.');
        if (level < 4 && ledgerType.value !== 'Group') errors.push('Only Ledger Head / Posting Account can use non-group ledger type.');
        if (partyControl && !partyType.value) errors.push('Please select party type for party control ledger.');

        showMessages(errors);
        return errors.length === 0;
    }

    function resetForm(scroll = true) {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        formTitle.textContent = 'Guided Account Setup';
        normalBalanceManuallyChanged = false;
        coaLevel.value = '4';
        parentAccount.dataset.selected = '';
        parentAccount.dataset.excludeId = '';
        partyType.dataset.selected = '';
        ledgerType.disabled = false;
        ledgerType.value = 'Asset';
        statusSelect.value = 'Active';
        userSelectableSelect.disabled = false;
        userSelectableSelect.value = '1';
        systemLedgerSelect.value = '0';
        messages.innerHTML = '';
        advancedFields.classList.add('coa-hidden');
        reloadParentDropdown('');
        syncSmartFields();
        if (scroll) form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function loadForEdit(rowId) {
        const row = accountRows[rowId];
        if (!row) return;

        form.dataset.action = row.update_url;
        methodInput.value = 'PUT';
        formTitle.textContent = 'Edit Account';

        const rowNormalBalance = row.normal_balance || 'Debit';
        normalBalanceManuallyChanged = true;

        accountCode.value = row.account_code || '';
        accountName.value = row.account_name || '';
        coaLevel.value = String(row.coa_level || 4);
        ledgerType.disabled = false;
        ledgerType.value = row.ledger_type || 'Asset';
        normalBalance.value = rowNormalBalance;
        statusSelect.value = row.status || 'Active';
        userSelectableSelect.disabled = false;
        userSelectableSelect.value = row.is_user_selectable ? '1' : '0';
        systemLedgerSelect.value = row.is_system_ledger ? '1' : '0';
        description.value = row.description || '';
        exampleUsage.value = row.example_usage || '';
        accountGroupHidden.value = row.account_group || '';
        accountSubGroupHidden.value = row.account_sub_group || '';

        parentAccount.dataset.excludeId = row.id || '';
        parentAccount.dataset.selected = row.parent_id || '';
        partyType.dataset.selected = row.party_type_id || '';

        setDropdownValue(accountType, row.account_type_id || '').then(() => {
            normalBalance.value = rowNormalBalance;
            normalBalanceManuallyChanged = true;
            syncSmartFields();
        });
        reloadParentDropdown(row.parent_id || '');
        setDropdownValue(partyType, row.party_type_id || '');

        advancedFields.classList.remove('coa-hidden');
        syncSmartFields();
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function loadDemo() {
        resetForm(false);
        coaLevel.value = '4';
        ledgerType.value = 'Bank';
        accountCode.value = '1114';
        accountName.value = 'Dutch Bangla Bank Account';
        description.value = 'Company bank account';
        exampleUsage.value = 'Bank receipt/payment';
        advancedFields.classList.remove('coa-hidden');
        reloadParentDropdown(parentAccount.value || '');
        syncSmartFields();
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function preferredCoaTab() {
        const requestedTab = new URLSearchParams(window.location.search).get('coa_tab');
        if (validCoaTabs.includes(requestedTab)) return requestedTab;

        return 'tree';
    }

    function selectedLoadSize() {
        const value = document.getElementById('coaLoadSize')?.value || '50';
        return value === 'all' ? Infinity : Number(value || 50);
    }

    function activeRowsFor(tabName = activeCoaTab) {
        const useMobileList = window.matchMedia('(max-width: 720px)').matches;

        if (tabName === 'tree') {
            return Array.from(document.querySelectorAll('[data-coa-tree-row]'));
        }

        if (tabName === 'posting') {
            return Array.from(document.querySelectorAll(useMobileList ? '[data-posting-mobile-row]' : '[data-posting-row]'));
        }

        return Array.from(document.querySelectorAll(useMobileList ? '[data-full-mobile-row]' : '[data-full-row]'))
            .filter((row) => row.dataset.filterMatch !== '0');
    }

    function scrollContainersFor(tabName = activeCoaTab) {
        if (tabName === 'tree') return [document.getElementById('tree')].filter(Boolean);
        if (tabName === 'posting') return Array.from(document.querySelectorAll('#postingView .coa-table-wrap, #postingView .coa-mobile-list'));
        return Array.from(document.querySelectorAll('#fullView .coa-table-wrap, #fullView .coa-mobile-list'));
    }

    function updateVisibleSummary(shown, total) {
        const summary = document.getElementById('coaVisibleSummary');
        if (!summary) return;
        summary.textContent = total
            ? `Showing ${Math.min(shown, total)} of ${total} record${total === 1 ? '' : 's'}. Scroll to load more.`
            : 'No records match the current view.';
    }

    function applyProgressiveLimit(reset = false) {
        const batchSize = selectedLoadSize();
        const rows = activeRowsFor();

        if (reset) visibleLimit = batchSize;
        if (batchSize === Infinity) visibleLimit = Infinity;
        if (!Number.isFinite(visibleLimit) || visibleLimit < batchSize) visibleLimit = batchSize;

        rows.forEach((row, index) => {
            row.style.display = index < visibleLimit ? '' : 'none';
        });

        updateVisibleSummary(Math.min(visibleLimit, rows.length), rows.length);
        refreshBulkSelectionUi();
    }

    function loadMoreVisibleRows() {
        const batchSize = selectedLoadSize();
        if (batchSize === Infinity) return;
        const rows = activeRowsFor();
        if (visibleLimit >= rows.length) return;
        visibleLimit += batchSize;
        applyProgressiveLimit(false);
    }

    function bindProgressiveScroll() {
        scrollContainersFor().forEach((container) => {
            if (container.dataset.coaScrollBound === '1') return;
            container.dataset.coaScrollBound = '1';
            container.addEventListener('scroll', () => {
                const nearBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 80;
                if (nearBottom) loadMoreVisibleRows();
            });
        });
    }

    function placeCoaToolbar(tabName) {
        if (!viewToolbar) return;

        const slot = document.querySelector(`[data-coa-toolbar-slot="${tabName}"]`);
        if (slot && viewToolbar.parentElement !== slot) {
            slot.appendChild(viewToolbar);
        }
    }

    function showTab(name) {
        name = validCoaTabs.includes(name) ? name : 'tree';
        activeCoaTab = name;
        placeCoaToolbar(name);
        document.querySelectorAll('[data-coa-tab-panel]').forEach((panel) => {
            panel.classList.toggle('coa-hidden', panel.dataset.coaTabPanel !== name);
        });
        document.querySelectorAll('[data-coa-tab-button]').forEach((button) => {
            button.classList.toggle('active', button.dataset.coaTabButton === name);
        });
        if (name === 'full') applyFullFilters(false);

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('coa_tab', name);
            window.history.replaceState({}, '', url);
        } catch (error) {
            // Tab switching still works if URL updates are unavailable.
        }

        bindProgressiveScroll();
        applyProgressiveLimit(true);
        refreshBulkSelectionUi();
    }

    function applyFullFilters(resetLimit = true) {
        const search = String(document.getElementById('search')?.value || '').toLowerCase().trim();
        const selectedClass = document.getElementById('fClass')?.value || '';
        const selectedLevel = document.getElementById('fLevel')?.value || '';
        const selectedLedger = document.getElementById('fLedger')?.value || '';
        const selectedPosting = document.getElementById('fPosting')?.value || '';

        document.querySelectorAll('[data-full-row], [data-full-mobile-row]').forEach((row) => {
            const show = (!search || String(row.dataset.search || '').includes(search))
                && (!selectedClass || row.dataset.class === selectedClass)
                && (!selectedLevel || row.dataset.level === selectedLevel)
                && (!selectedLedger || row.dataset.ledger === selectedLedger)
                && (!selectedPosting || row.dataset.posting === selectedPosting);

            row.dataset.filterMatch = show ? '1' : '0';
            if (!show) row.style.display = 'none';
        });

        if (activeCoaTab === 'full') applyProgressiveLimit(resetLimit);
    }

    coaLevel.addEventListener('change', () => { reloadParentDropdown(''); syncSmartFields(); });
    accountType.addEventListener('change', () => {
        normalBalanceManuallyChanged = false;
        reloadParentDropdown(parentAccount.value || '');
        syncSmartFields({ forceNormalBalance: true });
    });
    ledgerType.addEventListener('change', syncSmartFields);
    normalBalance.addEventListener('change', () => {
        normalBalanceManuallyChanged = true;
        syncSmartFields();
    });
    userSelectableSelect.addEventListener('change', syncSmartFields);
    systemLedgerSelect.addEventListener('change', syncSmartFields);

    form.addEventListener('submit', (event) => {
        syncSmartFields();
        if (!validateFormBeforeSubmit()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    document.getElementById('advancedToggleBtn')?.addEventListener('click', () => {
        advancedFields.classList.toggle('coa-hidden');
    });
    document.getElementById('clearAccountBtn')?.addEventListener('click', () => resetForm(true));
    document.getElementById('newAccountHeroBtn')?.addEventListener('click', () => resetForm(true));
    document.getElementById('loadDemoBtn')?.addEventListener('click', loadDemo);

    document.querySelectorAll('[data-coa-tab-button]').forEach((button) => {
        button.addEventListener('click', () => showTab(button.dataset.coaTabButton));
    });

    document.querySelectorAll('[data-edit-row-id]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (event.target.closest('form, [data-coa-select-control], input[type="checkbox"]')) return;
            loadForEdit(element.dataset.editRowId);
        });
        element.addEventListener('keydown', (event) => {
            if (event.target.closest('[data-coa-select-control], input[type="checkbox"]')) return;
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                loadForEdit(element.dataset.editRowId);
            }
        });
    });

    ['search', 'fClass', 'fLevel', 'fLedger', 'fPosting'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => applyFullFilters(true));
        document.getElementById(id)?.addEventListener('change', () => applyFullFilters(true));
    });

    document.getElementById('coaLoadSize')?.addEventListener('change', () => {
        if (activeCoaTab === 'full') applyFullFilters(true);
        applyProgressiveLimit(true);
    });

    window.addEventListener('resize', () => {
        if (activeCoaTab === 'full') applyFullFilters(false);
        applyProgressiveLimit(true);
    });

    resetForm(false);
    showTab(preferredCoaTab());
});
</script>
@endsection
