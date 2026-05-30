<?php
    use Illuminate\Support\Facades\Route;

    $currentUser = auth()->user();
    $routeName = request()->route()?->getName();
    $isActive = function ($name) use ($routeName) {
        if ($routeName === 'dashboard') {
            return '';
        }

        return ($routeName === $name || str_starts_with((string) $routeName, $name . '.')) ? 'active' : '';
    };

    $canRoute = fn ($name) => Route::has($name) && ($currentUser?->canViewRoute($name) ?? false);
    $canPermission = fn ($permission) => $currentUser?->hasPermission($permission) ?? false;

    $isMasterDataRoute = request()->routeIs('setup.master-data*');

    $setupLinks = [
        ['label' => 'Company Setup', 'route' => 'setup.company', 'icon' => '1'],
        ['label' => 'Chart of Accounts', 'route' => 'setup.chart-of-accounts', 'icon' => '2'],
        ['label' => 'Cash / Bank Setup', 'route' => 'setup.cash-bank-accounts', 'icon' => '3'],
        ['label' => 'Party / Person Setup', 'route' => 'setup.parties', 'icon' => '4'],
        ['label' => 'Transaction Head Setup', 'route' => 'setup.transaction-heads', 'icon' => '5'],
        ['label' => 'Accounting Rules Setup', 'route' => 'setup.accounting-rules-setup', 'icon' => '6'],
        ['label' => 'Opening Balance Setup', 'route' => 'setup.opening-balances', 'icon' => '7'],
        ['label' => 'Voucher Numbering', 'route' => 'setup.voucher-numbering', 'icon' => '8'],
    ];

    $masterDataLinks = [
        ['label' => 'Business Types', 'route' => 'setup.master-data.business-types'],
        ['label' => 'Currencies', 'route' => 'setup.master-data.currencies'],
        ['label' => 'Settlement Types', 'route' => 'setup.master-data.settlement-types'],
        ['label' => 'Party Types', 'route' => 'setup.master-data.party-types'],
        ['label' => 'Financial Years', 'route' => 'setup.master-data.financial-years'],
    ];

?>

<aside class="sidebar" id="appSidebar">
    <a href="<?php echo e(url('/')); ?>" class="brand brand-home" aria-label="Go to home">
        <div class="brand-mark">হি</div>
        <div>
            <h1>HisebGhor</h1>
            <p>Accounting System</p>
        </div>
    </a>

    <?php if(collect($setupLinks)->contains(fn ($link) => $canRoute($link['route'])) || $canRoute('setup.master-data')): ?>
        <div class="nav-title">Setup</div>

        <?php $__currentLoopData = $setupLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($canRoute($link['route'])): ?>
                <a href="<?php echo e(route($link['route'])); ?>" class="nav-item <?php echo e($isActive($link['route'])); ?>">
                    <div class="nav-icon"><?php echo e($link['icon']); ?></div>
                    <span><?php echo e($link['label']); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <?php if($canRoute('setup.master-data')): ?>
            <details class="nav-group master-data-nav-group" <?php echo e($isMasterDataRoute ? 'open' : ''); ?>>
                <summary
                    class="nav-item nav-parent <?php echo e($isActive('setup.master-data')); ?>"
                    data-sidebar-group-summary
                    aria-controls="masterDataSubmenu"
                >
                    <div class="nav-icon">9</div>
                    <span>Master Setup</span>
                    <span class="nav-arrow" aria-hidden="true">⌄</span>
                </summary>

                <div
                    class="nav-submenu <?php echo e($isMasterDataRoute ? 'is-open' : ''); ?>"
                    id="masterDataSubmenu"
                    aria-label="Master Setup Submenu"
                >
                    <?php $__currentLoopData = $masterDataLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $masterLink): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($canRoute($masterLink['route'])): ?>
                            <a
                                href="<?php echo e(route($masterLink['route'])); ?>"
                                class="nav-subitem <?php echo e(request()->routeIs($masterLink['route']) ? 'active' : ''); ?>"
                            >
                                <span><?php echo e($masterLink['label']); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <div class="nav-title">Main Menu</div>

    <?php if($canRoute('transactions.create')): ?>
        <a
            href="<?php echo e(route('transactions.create')); ?>"
            class="nav-item <?php echo e($isActive('transactions.create')); ?>"
        >
            <div class="nav-icon">＋</div>
            <span>Add Transaction</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('manual-journals.index')): ?>
        <a href="<?php echo e(route('manual-journals.index')); ?>" class="nav-item <?php echo e($isActive('manual-journals.index')); ?>">
            <div class="nav-icon">JV</div>
            <span>Manual Journal</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('accounting-reports.transactions.index') || $canPermission('transactions.view')): ?>
        <a href="<?php echo e(route('accounting-reports.transactions.index')); ?>" class="nav-item <?php echo e(request()->routeIs('accounting-reports.transactions.*') ? 'active' : ''); ?>">
            <div class="nav-icon">📄</div>
            <span>Transaction List</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('due-management.index')): ?>
        <a href="<?php echo e(route('due-management.index')); ?>" class="nav-item <?php echo e($isActive('due-management.index')); ?>">
            <div class="nav-icon">⏳</div>
            <span>Due Management</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('advance-management.index')): ?>
        <a href="<?php echo e(route('advance-management.index')); ?>" class="nav-item <?php echo e($isActive('advance-management.index')); ?>">
            <div class="nav-icon">↗</div>
            <span>Advance Management</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('ledger-report.index')): ?>
        <a href="<?php echo e(route('ledger-report.index')); ?>" class="nav-item <?php echo e($isActive('ledger-report.index')); ?>">
            <div class="nav-icon">📘</div>
            <span>Ledger Report</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('accounting-reports.cash-bank-book.index')): ?>
        <a href="<?php echo e(route('accounting-reports.cash-bank-book.index')); ?>" class="nav-item <?php echo e(request()->routeIs('accounting-reports.cash-bank-book.*') ? 'active' : ''); ?>">
            <div class="nav-icon">🏦</div>
            <span>Cash / Bank Book</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('accounting-reports.trial-balance.index')): ?>
        <a href="<?php echo e(route('accounting-reports.trial-balance.index')); ?>" class="nav-item <?php echo e(request()->routeIs('accounting-reports.trial-balance.*') ? 'active' : ''); ?>">
            <div class="nav-icon">TB</div>
            <span>Trial Balance</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('accounting-reports.income-statement.index')): ?>
        <a href="<?php echo e(route('accounting-reports.income-statement.index')); ?>" class="nav-item <?php echo e(request()->routeIs('accounting-reports.income-statement.*') ? 'active' : ''); ?>">
            <div class="nav-icon">IS</div>
            <span>Income Statement</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('accounting-reports.index') || $canPermission('reports.view')): ?>
        <a href="<?php echo e(route('accounting-reports.index')); ?>" class="nav-item <?php echo e(request()->routeIs('accounting-reports.index') ? 'active' : ''); ?>">
            <div class="nav-icon">▣</div>
            <span>Reports</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('approvals.index')): ?>
        <a href="<?php echo e(route('approvals.index')); ?>" class="nav-item <?php echo e($isActive('approvals.index')); ?>">
            <div class="nav-icon">✓</div>
            <span>Approvals</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('audit-trail.index')): ?>
        <a href="<?php echo e(route('audit-trail.index')); ?>" class="nav-item <?php echo e($isActive('audit-trail.index')); ?>">
            <div class="nav-icon">A</div>
            <span>Audit Trail</span>
        </a>
    <?php endif; ?>


    <?php if($canRoute('release-notes.index')): ?>
        <div class="nav-title">System</div>

        <a href="<?php echo e(route('release-notes.index')); ?>" class="nav-item <?php echo e($isActive('release-notes.index')); ?>">
            <div class="nav-icon">🚀</div>
            <span>Release Tracker</span>
        </a>
    <?php endif; ?>

    <?php if($canRoute('settings.users-roles')): ?>
        <div class="nav-title">Settings</div>

        <a href="<?php echo e(route('settings.users-roles')); ?>" class="nav-item <?php echo e($isActive('settings.users-roles')); ?>">
            <div class="nav-icon">U</div>
            <span>Users & Roles</span>
        </a>
    <?php endif; ?>

    <div class="help-card">
        <div class="help-badge">?</div>
        <div>
            <strong>Need Help?</strong>
            <span>Daily entry guide ↗</span>
        </div>
    </div>
</aside>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/partials/sidebar.blade.php ENDPATH**/ ?>