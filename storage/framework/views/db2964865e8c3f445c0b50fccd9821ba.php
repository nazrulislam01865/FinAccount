<?php $__env->startSection('title', 'Master Data Setup | HisebGhor'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    /* Master data uses the existing card, table, badge, and form styles without changing global design. */
    .master-data-grid {
        display: grid;
        gap: 22px;
    }

    .master-section {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
        align-items: start;
    }

    .master-section > .form-panel {
        order: 1;
        width: 100%;
    }

    .master-section > .table-card {
        order: 2;
        width: 100%;
    }

    .master-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        align-items: start;
    }

    .master-form .actions,
    .master-form .full {
        grid-column: 1 / -1;
    }

    .master-section .table-wrap {
        width: 100%;
        overflow-x: scroll;
        scrollbar-gutter: stable both-edges;
    }

    .master-section table {
        min-width: 1000px;
    }

    .master-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--line);
    }

    .master-card-head h3 {
        margin: 0 0 4px;
        font-size: 17px;
    }

    .master-card-head p {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.45;
    }

    .master-form-wrap {
        padding: 20px;
    }



    .master-subnav {
        display: flex;
        align-items: stretch;
        gap: 12px;
        overflow-x: auto;
        padding: 14px;
        margin-bottom: 18px;
        scroll-snap-type: x proximity;
    }

    .master-subnav-link {
        min-width: 190px;
        flex: 1 0 190px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: #fff;
        color: #475467;
        scroll-snap-align: start;
        transition: .17s ease;
    }

    .master-subnav-link:hover {
        border-color: #bfdbfe;
        background: #f9fbff;
        transform: translateY(-1px);
    }

    .master-subnav-link.active {
        border-color: #bfdbfe;
        background: var(--primary-soft);
        color: var(--primary);
        box-shadow: inset 4px 0 0 var(--primary);
    }

    .master-subnav-link strong {
        display: block;
        margin-bottom: 3px;
        font-size: 14px;
    }

    .master-subnav-link span {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.35;
    }

    .master-subnav-link.active span {
        color: #1d4ed8;
    }

    .master-subnav-count {
        flex: 0 0 auto;
    }

    .locked-action {
        opacity: .45;
        cursor: not-allowed;
        pointer-events: auto;
    }

    .usage-help {
        display: block;
        margin-top: 4px;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.35;
    }

    @media (max-width: 880px) {
        .master-form {
            grid-template-columns: 1fr;
        }

        .master-subnav {
            padding: 12px;
        }

        .master-subnav-link {
            min-width: 210px;
            flex-basis: 210px;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $activeMasterDataPage = $activeMasterDataPage ?? 'business-types';
    $activeMasterDataTab = $activeMasterDataTab ?? ($masterDataTabs[$activeMasterDataPage] ?? [
        'label' => 'Master Data',
        'description' => 'Manage reusable dropdown data used across the setup pages.',
    ]);
?>

<div class="page-title">
    <div>
        <span class="page-label">Master Data</span>
        <h2><?php echo e($activeMasterDataTab['label'] ?? 'Master Data Setup'); ?></h2>
        <p><?php echo e($activeMasterDataTab['description'] ?? 'Add or update reusable master data used across setup and transaction forms.'); ?></p>
    </div>
</div>

<?php if(!empty($masterDataTabs)): ?>
    <nav class="card master-subnav" aria-label="Master Data Sub Pages">
        <?php $__currentLoopData = $masterDataTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a
                href="<?php echo e(route($tab['route'])); ?>"
                class="master-subnav-link <?php echo e($activeMasterDataPage === $key ? 'active' : ''); ?>"
                <?php if($activeMasterDataPage === $key): ?> aria-current="page" <?php endif; ?>
            >
                <div>
                    <strong><?php echo e($tab['label']); ?></strong>
                    <span><?php echo e($tab['description']); ?></span>
                </div>
                <span class="badge badge-primary master-subnav-count"><?php echo e($tab['count']); ?></span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
<?php endif; ?>

<div class="master-data-grid">
    <?php if($activeMasterDataPage === 'business-types'): ?>
    <section class="master-section" id="businessTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Business Types</h3>
                    <p>These values appear in Company Setup business type dropdown.</p>
                </div>
                <span class="badge badge-primary"><?php echo e($businessTypes->count()); ?> Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Default</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $businessTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                data-id="<?php echo e($type->id); ?>"
                                data-name="<?php echo e(e($type->name)); ?>"
                                data-code="<?php echo e(e($type->code)); ?>"
                                data-description="<?php echo e(e($type->description)); ?>"
                                data-is-default="<?php echo e($type->is_default ? 1 : 0); ?>"
                                data-sort-order="<?php echo e($type->sort_order); ?>"
                                data-status="<?php echo e($type->status); ?>"
                                data-update-url="<?php echo e(route('api.master-data.business-types.update', $type)); ?>"
                            >
                                <td class="strong"><?php echo e($type->name); ?></td>
                                <td><?php echo e($type->code); ?></td>
                                <td><?php echo e($type->description ?: '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo e($type->is_default ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($type->is_default ? 'Yes' : 'No'); ?>

                                    </span>
                                </td>
                                <td><?php echo e($type->sort_order); ?></td>
                                <td>
                                    <span class="badge <?php echo e($type->status === 'Active' ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($type->status); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="businessTypeForm" title="Edit">✎</button>
                                        <form method="POST" action="<?php echo e(route('setup.master-data.business-types.destroy', $type)); ?>" data-delete-form onsubmit="return confirm('Delete this business type?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">No business types found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Business Type</h3>
                    <span>Example: Trading Business, Service Business, Fleet Management.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="businessTypeForm"
                data-frontend-form
                data-action="<?php echo e(route('api.master-data.business-types.store')); ?>"
                data-store-url="<?php echo e(route('api.master-data.business-types.store')); ?>"
                data-success="Business type saved successfully."
            >
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Trading Business" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="TRADING" required>
                    <div class="inline-help">Uppercase letters, numbers, and underscores only.</div>
                </div>

                <div>
                    <label>Description</label>
                    <textarea name="description" placeholder="Optional description"></textarea>
                </div>

                <div>
                    <label>Default Business Type</label>
                    <input type="hidden" id="businessTypeDefault" name="is_default" value="0">
                    <div class="switch" data-input="businessTypeDefault"></div>
                    <div class="inline-help">When enabled, other business types are marked non-default.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
    <?php endif; ?>

    <?php if($activeMasterDataPage === 'currencies'): ?>
    <section class="master-section" id="currenciesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Currencies</h3>
                    <p>These values appear in Company Setup currency dropdown.</p>
                </div>
                <span class="badge badge-primary"><?php echo e($currencies->count()); ?> Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Symbol</th>
                            <th>Decimals</th>
                            <th>Default</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $currencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                data-id="<?php echo e($currency->id); ?>"
                                data-code="<?php echo e(e($currency->code)); ?>"
                                data-name="<?php echo e(e($currency->name)); ?>"
                                data-symbol="<?php echo e(e($currency->symbol)); ?>"
                                data-decimal-places="<?php echo e($currency->decimal_places); ?>"
                                data-is-default="<?php echo e($currency->is_default ? 1 : 0); ?>"
                                data-sort-order="<?php echo e($currency->sort_order); ?>"
                                data-status="<?php echo e($currency->status); ?>"
                                data-update-url="<?php echo e(route('api.master-data.currencies.update', $currency)); ?>"
                            >
                                <td class="strong"><?php echo e($currency->code); ?></td>
                                <td><?php echo e($currency->name); ?></td>
                                <td><?php echo e($currency->symbol ?: '—'); ?></td>
                                <td><?php echo e($currency->decimal_places); ?></td>
                                <td>
                                    <span class="badge <?php echo e($currency->is_default ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($currency->is_default ? 'Yes' : 'No'); ?>

                                    </span>
                                </td>
                                <td><?php echo e($currency->sort_order); ?></td>
                                <td>
                                    <span class="badge <?php echo e($currency->status === 'Active' ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($currency->status); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="currencyForm" title="Edit">✎</button>
                                        <form method="POST" action="<?php echo e(route('setup.master-data.currencies.destroy', $currency)); ?>" data-delete-form onsubmit="return confirm('Delete this currency?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">No currencies found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Currency</h3>
                    <span>Example: BDT, USD, EUR.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="currencyForm"
                data-frontend-form
                data-action="<?php echo e(route('api.master-data.currencies.store')); ?>"
                data-store-url="<?php echo e(route('api.master-data.currencies.store')); ?>"
                data-success="Currency saved successfully."
            >
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" maxlength="3" placeholder="BDT" required>
                    <div class="inline-help">Use a 3-letter ISO code such as BDT or USD.</div>
                </div>

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Bangladeshi Taka" required>
                </div>

                <div>
                    <label>Symbol</label>
                    <input name="symbol" maxlength="10" placeholder="৳">
                </div>

                <div>
                    <label>Decimal Places <span class="required">*</span></label>
                    <input type="number" name="decimal_places" min="0" max="6" value="2" required>
                </div>

                <div>
                    <label>Default Currency</label>
                    <input type="hidden" id="currencyDefault" name="is_default" value="0">
                    <div class="switch" data-input="currencyDefault"></div>
                    <div class="inline-help">When enabled, other currencies are marked non-default.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
    <?php endif; ?>

    <?php if($activeMasterDataPage === 'settlement-types'): ?>
    <section class="master-section" id="settlementTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Settlement Types</h3>
                    <p>These values appear in Transaction Head, Accounting Rules Setup, and Add Transaction forms.</p>
                </div>
                <span class="badge badge-primary"><?php echo e($settlementTypes->count()); ?> Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $settlementTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                data-id="<?php echo e($type->id); ?>"
                                data-name="<?php echo e(e($type->name)); ?>"
                                data-code="<?php echo e(e($type->code)); ?>"
                                data-sort-order="<?php echo e($type->sort_order); ?>"
                                data-status="<?php echo e($type->status); ?>"
                                data-update-url="<?php echo e(route('api.master-data.settlement-types.update', $type)); ?>"
                            >
                                <td class="strong"><?php echo e($type->name); ?></td>
                                <td><?php echo e($type->code); ?></td>
                                <td><?php echo e($type->sort_order); ?></td>
                                <td>
                                    <span class="badge <?php echo e($type->status === 'Active' ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($type->status); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="settlementTypeForm" title="Edit">✎</button>
                                        <form method="POST" action="<?php echo e(route('setup.master-data.settlement-types.destroy', $type)); ?>" data-delete-form onsubmit="return confirm('Delete this settlement type?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="5" style="text-align:center;padding:24px;color:var(--muted)">No settlement types found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Settlement Type</h3>
                    <span>Example: Cash, Bank, Due, Adjustment.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="settlementTypeForm"
                data-frontend-form
                data-action="<?php echo e(route('api.master-data.settlement-types.store')); ?>"
                data-store-url="<?php echo e(route('api.master-data.settlement-types.store')); ?>"
                data-success="Settlement type saved successfully."
            >
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Cash" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="CASH" required>
                    <div class="inline-help">Uppercase letters, numbers, and underscores only.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
    <?php endif; ?>

    <?php if($activeMasterDataPage === 'party-types'): ?>
    <section class="master-section" id="partyTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Party Types</h3>
                    <p>These values appear in Party / Person Setup and Transaction Head default party type.</p>
                </div>
                <span class="badge badge-primary"><?php echo e($partyTypes->count()); ?> Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Default Nature</th>
                            <th>Default Ledger</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $partyTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                data-id="<?php echo e($type->id); ?>"
                                data-name="<?php echo e(e($type->name)); ?>"
                                data-code="<?php echo e(e($type->code)); ?>"
                                data-default-ledger-account-id="<?php echo e($type->default_ledger_account_id); ?>"
                                data-default-ledger-nature="<?php echo e($type->default_ledger_nature ?: 'No Effect'); ?>"
                                data-sort-order="<?php echo e($type->sort_order); ?>"
                                data-status="<?php echo e($type->status); ?>"
                                data-update-url="<?php echo e(route('api.master-data.party-types.update', $type)); ?>"
                            >
                                <td class="strong"><?php echo e($type->name); ?></td>
                                <td><?php echo e($type->code); ?></td>
                                <td><span class="badge badge-primary"><?php echo e($type->default_ledger_nature ?: 'No Effect'); ?></span></td>
                                <td>
                                    <?php echo e($type->defaultLedger?->display_name ?? '—'); ?>

                                    <?php if(! $type->default_ledger_account_id && $type->status === 'Inactive'): ?>
                                        <div class="hint" style="margin-top:4px;color:#b42318;font-weight:700">
                                            Ledger reassignment required. Select a replacement default ledger and reactivate this party type.
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($type->sort_order); ?></td>
                                <td>
                                    <span class="badge <?php echo e($type->status === 'Active' ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($type->status); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="partyTypeForm" title="Edit">✎</button>
                                        <form method="POST" action="<?php echo e(route('setup.master-data.party-types.destroy', $type)); ?>" data-delete-form onsubmit="return confirm('Delete this party type?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">No party types found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Party Type</h3>
                    <span>Example: Employee, Supplier, Customer, Vendor.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="partyTypeForm"
                data-frontend-form
                data-action="<?php echo e(route('api.master-data.party-types.store')); ?>"
                data-store-url="<?php echo e(route('api.master-data.party-types.store')); ?>"
                data-success="Party type saved successfully."
            >
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Supplier" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="SUP" required>
                </div>

                <div>
                    <label>Default Accounting Nature <span class="required">*</span></label>
                    <select name="default_ledger_nature" required>
                        <option value="Receivable">Receivable</option>
                        <option value="Payable">Payable</option>
                        <option value="Advance Paid">Advance Paid</option>
                        <option value="Advance Received">Advance Received</option>
                        <option value="Capital">Capital / Owner Equity</option>
                        <option value="No Effect">No Effect</option>
                    </select>
                    <div class="hint">Sets the automatic primary nature for parties of this type. Transaction rules still use explicit party ledger mappings.</div>
                </div>

                <div>
                    <label>Default Ledger / Group</label>
                    <select name="default_ledger_account_id">
                        <option value="">Select Default Ledger</option>
                        <?php $__currentLoopData = $ledgerAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($account->id); ?>"><?php echo e($account->display_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
    <?php endif; ?>


    <?php if($activeMasterDataPage === 'ledger-types'): ?>
    <section class="master-section" id="ledgerTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Ledger Types</h3>
                    <p>These values appear in Chart of Accounts setup under "What type of ledger is this?"</p>
                </div>
                <span class="badge badge-primary"><?php echo e($ledgerTypes->count()); ?> Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Usage</th>
                            <th>System</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $ledgerTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $usageCount = (int) ($ledgerTypeUsageCounts[$type->name] ?? 0);
                                $isProtectedLedgerType = $type->isProtectedSystemType();
                                $deleteLocked = $usageCount > 0 || $isProtectedLedgerType;
                                $deleteTitle = $usageCount > 0
                                    ? 'Used by '.$usageCount.' setup/rule record(s). Reassign those records or set this ledger type inactive.'
                                    : ($isProtectedLedgerType ? 'Protected accounting ledger type cannot be deleted. Set it inactive if needed.' : 'Delete');
                            ?>
                            <tr
                                data-id="<?php echo e($type->id); ?>"
                                data-name="<?php echo e(e($type->name)); ?>"
                                data-code="<?php echo e(e($type->code)); ?>"
                                data-description="<?php echo e(e($type->description)); ?>"
                                data-is-system="<?php echo e($type->is_system ? 1 : 0); ?>"
                                data-sort-order="<?php echo e($type->sort_order); ?>"
                                data-status="<?php echo e($type->status); ?>"
                                data-usage-count="<?php echo e($usageCount); ?>"
                                data-update-url="<?php echo e(route('api.master-data.ledger-types.update', $type)); ?>"
                            >
                                <td class="strong"><?php echo e($type->name); ?></td>
                                <td><?php echo e($type->code); ?></td>
                                <td><?php echo e($type->description ?: '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo e($usageCount > 0 ? 'badge-primary' : 'badge-neutral'); ?>">
                                        <?php echo e($usageCount); ?> Used
                                    </span>
                                    <?php if($usageCount > 0): ?>
                                        <span class="usage-help">Reassign or deactivate instead of deleting.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo e($type->is_system ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($isProtectedLedgerType ? 'Protected' : ($type->is_system ? 'Yes' : 'No')); ?>

                                    </span>
                                </td>
                                <td><?php echo e($type->sort_order); ?></td>
                                <td>
                                    <span class="badge <?php echo e($type->status === 'Active' ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($type->status); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="ledgerTypeForm" title="Edit">✎</button>
                                        <?php if($deleteLocked): ?>
                                            <button class="icon-btn delete-btn locked-action" type="button" title="<?php echo e($deleteTitle); ?>" aria-disabled="true">🗑</button>
                                        <?php else: ?>
                                            <form method="POST" action="<?php echo e(route('setup.master-data.ledger-types.destroy', $type)); ?>" data-delete-form onsubmit="return confirm('Delete this ledger type?')">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('DELETE'); ?>
                                                <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">No ledger types found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Ledger Type</h3>
                    <span>Example: Cash, Bank, Party Control, Loan, Asset, Income, Expense.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="ledgerTypeForm"
                data-frontend-form
                data-action="<?php echo e(route('api.master-data.ledger-types.store')); ?>"
                data-store-url="<?php echo e(route('api.master-data.ledger-types.store')); ?>"
                data-success="Ledger type saved successfully."
            >
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Loan" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="LOAN" required>
                    <div class="inline-help">Uppercase letters, numbers, and underscores only.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <div class="inline-help">Use Inactive to hide a ledger type from new CoA entries when it is already used by existing accounts.</div>
                </div>

                <div class="full">
                    <label>Description</label>
                    <textarea name="description" placeholder="Where this ledger type should be used"></textarea>
                </div>

                <input type="hidden" name="is_system" value="0">

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
    <?php endif; ?>

    <?php if($activeMasterDataPage === 'financial-years'): ?>
    <section class="master-section" id="financialYearsSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Financial Years</h3>
                    <p>Control the posting period, current year, and lock date used by transaction posting.</p>
                </div>
                <span class="badge badge-primary"><?php echo e($financialYears->count()); ?> Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Lock Date</th>
                            <th>Current Year</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $financialYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                data-id="<?php echo e($year->id); ?>"
                                data-name="<?php echo e(e($year->name)); ?>"
                                data-start-date="<?php echo e(optional($year->start_date)->format('Y-m-d')); ?>"
                                data-end-date="<?php echo e(optional($year->end_date)->format('Y-m-d')); ?>"
                                data-lock-date="<?php echo e(optional($year->lock_date)->format('Y-m-d')); ?>"
                                data-is-active="<?php echo e(($year->is_current || $year->is_active) ? 1 : 0); ?>"
                                data-is-current="<?php echo e(($year->is_current || $year->is_active) ? 1 : 0); ?>"
                                data-status="<?php echo e($year->status); ?>"
                                data-update-url="<?php echo e(route('api.master-data.financial-years.update', $year)); ?>"
                            >
                                <td class="strong"><?php echo e($year->name); ?></td>
                                <td><?php echo e(optional($year->start_date)->format('d M Y')); ?></td>
                                <td><?php echo e(optional($year->end_date)->format('d M Y')); ?></td>
                                <td><?php echo e(optional($year->lock_date)->format('d M Y') ?: '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo e(($year->is_current || $year->is_active) ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e(($year->is_current || $year->is_active) ? 'Yes' : 'No'); ?>

                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo e($year->status === 'Open' || $year->status === 'Active' ? 'badge-active' : 'badge-neutral'); ?>">
                                        <?php echo e($year->status === 'Active' ? 'Open' : $year->status); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="financialYearForm" title="Edit">✎</button>

                                        <?php if(!($year->is_current || $year->is_active) || $year->status !== 'Open'): ?>
                                            <form method="POST" action="<?php echo e(route('api.master-data.financial-years.set-current', $year)); ?>" onsubmit="return confirm('Set this as the current financial year?')">
                                                <?php echo csrf_field(); ?>
                                                <button class="icon-btn" type="submit" title="Set Current">★</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if(in_array($year->status, ['Open', 'Active'], true)): ?>
                                            <form method="POST" action="<?php echo e(route('api.master-data.financial-years.close', $year)); ?>" onsubmit="return confirm('Close this financial year and block posting?')">
                                                <?php echo csrf_field(); ?>
                                                <button class="icon-btn" type="submit" title="Close Year">🔒</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?php echo e(route('api.master-data.financial-years.reopen', $year)); ?>" onsubmit="return confirm('Reopen this financial year?')">
                                                <?php echo csrf_field(); ?>
                                                <button class="icon-btn" type="submit" title="Reopen Year">↻</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" action="<?php echo e(route('setup.master-data.financial-years.destroy', $year)); ?>" data-delete-form onsubmit="return confirm('Delete this financial year?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">No financial years found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Financial Year</h3>
                    <span>Example: 2026-2027 from 01 Jul 2026 to 30 Jun 2027.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="financialYearForm"
                data-frontend-form
                data-action="<?php echo e(route('api.master-data.financial-years.store')); ?>"
                data-store-url="<?php echo e(route('api.master-data.financial-years.store')); ?>"
                data-success="Financial year saved successfully."
            >
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name</label>
                    <input name="name" placeholder="2026-2027">
                    <div class="inline-help">Leave blank to generate from start and end dates.</div>
                </div>

                <div>
                    <label>Start Date <span class="required">*</span></label>
                    <input type="date" name="start_date" required>
                </div>

                <div>
                    <label>End Date <span class="required">*</span></label>
                    <input type="date" name="end_date" required>
                </div>

                <div>
                    <label>Lock Date</label>
                    <input type="date" name="lock_date">
                    <div class="inline-help">Postings on or before this date are blocked.</div>
                </div>

                <div>
                    <label>Current Financial Year</label>
                    <input type="hidden" id="financialYearCurrent" name="is_current" value="0">
                    <input type="hidden" id="financialYearActive" name="is_active" value="0">
                    <div class="switch" data-input="financialYearCurrent"></div>
                    <div class="inline-help">Only one financial year should be current at a time.</div>
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                        <option value="Locked">Locked</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    /* Edit buttons reuse the same form design and only switch the backend endpoint. */
    document.querySelectorAll('.js-master-edit').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            const form = document.getElementById(button.dataset.target);

            if (!row || !form) {
                return;
            }

            form.dataset.action = row.dataset.updateUrl;
            form.querySelector('[name="_method"]').value = 'PUT';

            Object.entries(row.dataset).forEach(([key, value]) => {
                const name = key.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);
                const field = form.querySelector(`[name="${name}"]`);

                if (!field) {
                    return;
                }

                field.value = value || '';
            });

            form.querySelectorAll('.switch[data-input]').forEach((switchElement) => {
                const input = document.getElementById(switchElement.dataset.input);

                if (!input) {
                    return;
                }

                const dataKey = input.name.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
                const enabled = Number(row.dataset[dataKey] || row.dataset.isActive || 0) === 1;
                switchElement.classList.toggle('on', enabled);
                input.value = enabled ? '1' : '0';

                if (input.name === 'is_current') {
                    const activeInput = form.querySelector('[name="is_active"]');
                    if (activeInput) {
                        activeInput.value = input.value;
                    }
                }
            });

            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    /* Cancel returns the form to create mode without changing the page layout. */
    document.querySelectorAll('.js-master-reset').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');

            if (!form) {
                return;
            }

            form.reset();
            form.dataset.action = form.dataset.storeUrl;
            form.querySelector('[name="_method"]').value = 'POST';

            form.querySelectorAll('.switch[data-input]').forEach((switchElement) => {
                switchElement.classList.remove('on');
                const input = document.getElementById(switchElement.dataset.input);

                if (input) {
                    input.value = '0';
                }

                if (input?.name === 'is_current') {
                    const activeInput = form.querySelector('[name="is_active"]');
                    if (activeInput) {
                        activeInput.value = '0';
                    }
                }
            });
        });
    });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/setup/master-data.blade.php ENDPATH**/ ?>