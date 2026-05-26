@extends('layouts.app')

@section('title', 'Chart of Accounts Setup | Accounting System')

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
    .coa-filter-grid{display:grid;grid-template-columns:2fr repeat(4,1fr);gap:10px;margin-bottom:14px;}
    .coa-tree{display:grid;gap:6px;}
    .coa-tree-node{padding:10px 12px;border:1px solid var(--coa-line);border-radius:14px;background:#fff;cursor:pointer;transition:.16s ease;}
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
    @media(max-width:720px){
        .coa-hero{padding:18px;border-radius:20px;}
        .coa-hero-logo{width:46px;height:46px;}
        .coa-table-wrap{display:none;}
        .coa-mobile-list{display:block;}
    }
    @media(max-width:560px){
        .coa-stats{grid-template-columns:1fr;}
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
            <button class="coa-btn-light" type="button" data-coa-tab-button="tree">View CoA Tree</button>
            <button class="coa-btn-light" type="button" data-coa-tab-button="posting">Posting Ledgers</button>
        </div>
    </header>

    <section class="coa-stats" aria-label="Chart of Accounts statistics">
        <div class="coa-stat"><span>Total Accounts</span><strong>{{ $stats['total'] ?? $accountRows->count() }}</strong></div>
        <div class="coa-stat"><span>Posting Ledgers</span><strong>{{ $stats['posting'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Group Accounts</span><strong>{{ $stats['groups'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Cash/Bank Ledgers</span><strong>{{ $stats['cash_bank'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Party Control</span><strong>{{ $stats['party_control'] ?? 0 }}</strong></div>
        <div class="coa-stat"><span>Active Accounts</span><strong>{{ $stats['active'] ?? 0 }}</strong></div>
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
                            <div class="coa-hint">Auto-suggested from account nature.</div>
                        </div>

                        <div class="coa-field">
                            <label for="postingPreview">Can transactions be posted here?</label>
                            <select id="postingPreview" disabled>
                                <option value="0">No</option>
                                <option value="1" selected>Yes</option>
                            </select>
                            <div class="coa-hint">Only Ledger Head should be Yes.</div>
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

                        <div class="coa-field">
                            <label for="userSelectableSelect">Should users select this in transaction entry?</label>
                            <select id="userSelectableSelect">
                                <option value="0">No</option>
                                <option value="1" selected>Yes</option>
                            </select>
                        </div>
                    </div>

                    <div class="coa-derived-flags">
                        <span id="postingBadge" class="coa-badge posting">Posting: Yes</span>
                        <span id="cashBankBadge" class="coa-badge group">Cash/Bank: No</span>
                        <span id="partyControlBadge" class="coa-badge group">Party Control: No</span>
                    </div>

                    <div class="coa-advanced">
                        <button type="button" class="btn-ghost" id="advancedToggleBtn">Show / Hide Advanced Details</button>
                        <div class="coa-form-grid coa-hidden" id="advancedFields" style="margin-top:15px">
                            <div class="coa-field">
                                <label for="cashBankPreview">Is this a cash or bank account?</label>
                                <select id="cashBankPreview" disabled>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>

                            <div class="coa-field">
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

                            <div class="coa-field">
                                <label for="systemLedgerSelect">Is system ledger?</label>
                                <select id="systemLedgerSelect">
                                    <option value="1">Yes</option>
                                    <option value="0" selected>No</option>
                                </select>
                            </div>

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
                <p class="coa-card-subtitle">Tree view, posting ledgers, and full account list.</p>
            </div>
        </div>
        <div class="coa-card-body">
            <div class="coa-tabs" role="tablist" aria-label="Chart of Accounts views">
                <button class="coa-tab active" type="button" data-coa-tab-button="tree" id="tabTree">CoA Tree</button>
                <button class="coa-tab" type="button" data-coa-tab-button="posting" id="tabPosting">Posting Ledgers</button>
                <button class="coa-tab" type="button" data-coa-tab-button="full" id="tabFull">Full CoA List</button>
            </div>

            <div id="treeView" data-coa-tab-panel="tree">
                <div class="coa-tree" id="tree">
                    @forelse($accountRows as $row)
                        <div
                            class="coa-tree-node coa-lvl{{ $row['coa_level'] }}"
                            data-edit-row-id="{{ $row['id'] }}"
                            role="button"
                            tabindex="0"
                        >
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
                    @empty
                        <div class="coa-table-empty">No accounts found. Use the guided form to create the first CoA account.</div>
                    @endforelse
                </div>
            </div>

            <div id="postingView" class="coa-hidden" data-coa-tab-panel="posting">
                <div class="coa-table-wrap">
                    <table>
                        <thead>
                            <tr>
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
                                <tr data-edit-row-id="{{ $row['id'] }}">
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
                                    <td><button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="coa-table-empty">No posting ledgers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="coa-mobile-list">
                    @foreach($accountRows->where('posting_allowed', true) as $row)
                        <div class="coa-mobile-item">
                            <div class="coa-mobile-top">
                                <div>
                                    <div class="coa-mobile-title">{{ $row['account_code'] }} · {{ $row['account_name'] }}</div>
                                    <div class="coa-mobile-meta">Level {{ $row['coa_level'] }} · {{ $row['level_name'] }}</div>
                                </div>
                                <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
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

                <div class="coa-table-wrap">
                    <table id="fullCoaTable">
                        <thead>
                            <tr>
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
                                    data-search="{{ strtolower($row['account_code'] . ' ' . $row['account_name'] . ' ' . ($row['parent_name'] ?? '') . ' ' . ($row['account_group'] ?? '') . ' ' . ($row['account_sub_group'] ?? '')) }}"
                                    data-class="{{ $row['account_class'] }}"
                                    data-level="{{ $row['coa_level'] }}"
                                    data-ledger="{{ $row['ledger_type'] }}"
                                    data-posting="{{ $row['posting_allowed'] ? 'Yes' : 'No' }}"
                                    data-edit-row-id="{{ $row['id'] }}"
                                >
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
                                            @if(! $row['is_system_ledger'])
                                                <form method="POST" action="{{ $row['delete_url'] }}" data-delete-form>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-ghost coa-small-btn delete-btn">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="coa-table-empty">No accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="coa-mobile-list" id="fullMobileList">
                    @foreach($accountRows as $row)
                        <div
                            class="coa-mobile-item"
                            data-full-mobile-row
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
                                <button type="button" class="btn-ghost coa-small-btn" data-edit-row-id="{{ $row['id'] }}">Edit</button>
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
    const accountRows = @json($accountRows->keyBy('id')->toArray());
    const levelMap = @json($coaLevels);
    const normalByNature = { Asset: 'Debit', Expense: 'Debit', 'Equity Contra': 'Debit', Liability: 'Credit', Equity: 'Credit', Income: 'Credit', "Owner's Equity": 'Credit', 'Owner’s Equity': 'Credit' };

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

    function syncSmartFields() {
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
        normalBalance.value = balance || normalBalance.value || 'Debit';
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
        if (!select) return;
        select.dataset.selected = value || '';
        select.value = value || '';

        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncSmartFields();
            });
            return;
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncSmartFields();
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

        accountCode.value = row.account_code || '';
        accountName.value = row.account_name || '';
        coaLevel.value = String(row.coa_level || 4);
        ledgerType.disabled = false;
        ledgerType.value = row.ledger_type || 'Asset';
        normalBalance.value = row.normal_balance || 'Debit';
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

        setDropdownValue(accountType, row.account_type_id || '');
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

    function showTab(name) {
        document.querySelectorAll('[data-coa-tab-panel]').forEach((panel) => {
            panel.classList.toggle('coa-hidden', panel.dataset.coaTabPanel !== name);
        });
        document.querySelectorAll('[data-coa-tab-button]').forEach((button) => {
            button.classList.toggle('active', button.dataset.coaTabButton === name);
        });
        if (name === 'full') applyFullFilters();
    }

    function applyFullFilters() {
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

            row.style.display = show ? '' : 'none';
        });
    }

    coaLevel.addEventListener('change', () => { reloadParentDropdown(''); syncSmartFields(); });
    accountType.addEventListener('change', () => { reloadParentDropdown(parentAccount.value || ''); syncSmartFields(); });
    ledgerType.addEventListener('change', syncSmartFields);
    normalBalance.addEventListener('change', syncSmartFields);
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
            if (event.target.closest('form')) return;
            loadForEdit(element.dataset.editRowId);
        });
        element.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                loadForEdit(element.dataset.editRowId);
            }
        });
    });

    ['search', 'fClass', 'fLevel', 'fLedger', 'fPosting'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', applyFullFilters);
        document.getElementById(id)?.addEventListener('change', applyFullFilters);
    });

    resetForm(false);
    showTab('tree');
});
</script>
@endsection
