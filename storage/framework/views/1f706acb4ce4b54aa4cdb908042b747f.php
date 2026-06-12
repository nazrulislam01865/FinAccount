<?php $__env->startSection('title', 'Audit Log Report | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $selectedAction = $filters['action'] ?? $filters['event'] ?? '';
?>

<style>
    .audit-page { display:flex; flex-direction:column; gap:16px; }
    .audit-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
    .audit-header h2 { margin:0; font-size:28px; font-weight:800; color:#0f172a; }
    .audit-header p { margin:6px 0 0; color:#64748b; max-width:780px; }
    .audit-label { display:inline-flex; padding:6px 10px; border-radius:999px; background:#eef2ff; color:#3730a3; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .audit-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .audit-stats { display:grid; grid-template-columns:repeat(4, minmax(160px, 1fr)); gap:12px; }
    .audit-stat { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px; box-shadow:0 12px 30px rgba(15,23,42,.05); }
    .audit-stat span { color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
    .audit-stat strong { display:block; margin-top:6px; font-size:26px; color:#0f172a; }
    .audit-banner { display:flex; gap:12px; align-items:flex-start; padding:14px 16px; border:1px solid #bfdbfe; background:#eff6ff; color:#1e3a8a; border-radius:16px; }
    .audit-banner strong { display:block; color:#1e40af; }
    .audit-card { background:#fff; border:1px solid #e2e8f0; border-radius:18px; box-shadow:0 16px 35px rgba(15,23,42,.06); }
    .audit-filter { padding:16px; display:grid; grid-template-columns:repeat(6, minmax(140px, 1fr)); gap:12px; align-items:end; }
    .audit-filter label { display:block; margin-bottom:6px; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
    .audit-filter input, .audit-filter select { width:100%; min-height:42px; border:1px solid #cbd5e1; border-radius:12px; padding:9px 11px; background:#fff; color:#0f172a; }
    .audit-filter .wide { grid-column:span 2; }
    .audit-filter .filter-buttons { display:flex; gap:8px; flex-wrap:wrap; }
    .audit-btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:0 16px; border-radius:12px; border:1px solid #cbd5e1; color:#0f172a; background:#fff; font-weight:800; text-decoration:none; cursor:pointer; }
    .audit-btn.primary { background:#2563eb; color:#fff; border-color:#2563eb; }
    .audit-btn.ghost { background:#f8fafc; }
    .audit-table-wrap { width:100%; overflow-x:auto; }
    .audit-table { width:100%; border-collapse:collapse; min-width:1180px; }
    .audit-table th { background:#f8fafc; color:#475569; text-align:left; padding:12px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; }
    .audit-table td { padding:12px; vertical-align:top; border-bottom:1px solid #eef2f7; color:#0f172a; }
    .audit-muted { color:#64748b; font-size:12px; }
    .audit-badge { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px; background:#f1f5f9; color:#334155; font-size:12px; font-weight:800; white-space:nowrap; }
    .audit-badge.posting { background:#dcfce7; color:#166534; }
    .audit-badge.reversal { background:#fee2e2; color:#991b1b; }
    .audit-badge.security { background:#fef3c7; color:#92400e; }
    .audit-fields { display:flex; gap:6px; flex-wrap:wrap; max-width:280px; }
    .audit-field { padding:3px 7px; border-radius:999px; background:#f8fafc; border:1px solid #e2e8f0; color:#475569; font-size:11px; font-weight:700; }
    .audit-change-panel { margin-top:8px; display:flex; flex-direction:column; gap:10px; min-width:520px; }
    .audit-change-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
    .audit-change-line { color:#0f172a; font-size:14px; line-height:1.55; word-break:break-word; }
    .audit-change-field { font-weight:900; color:#111827; }
    .audit-change-old { font-weight:800; color:#b91c1c; }
    .audit-change-new { font-weight:800; color:#166534; }
    .audit-change-action { font-weight:900; color:#1d4ed8; text-transform:lowercase; }
    .audit-context-title { margin:4px 0 4px; font-size:12px; font-weight:900; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
    .audit-context-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:4px; }
    .audit-context-line { color:#475569; font-size:13px; line-height:1.5; word-break:break-word; }
    .audit-context-line strong { color:#0f172a; font-weight:800; }
    .audit-footer { padding:14px 16px; }
    @media (max-width: 1100px) { .audit-stats { grid-template-columns:repeat(2, minmax(160px, 1fr)); } .audit-filter { grid-template-columns:repeat(2, minmax(140px, 1fr)); } .audit-filter .wide { grid-column:span 2; } .audit-change-panel { min-width:0; } }
    @media (max-width: 640px) { .audit-stats, .audit-filter { grid-template-columns:1fr; } .audit-filter .wide { grid-column:auto; } }
</style>

<div class="audit-page">
    <div class="audit-header">
        <div>
            <span class="audit-label">Audit & Compliance</span>
            <h2>Audit Log Report</h2>
            <p>Read-only report for setup changes, posting, approval, reversal, role permission changes, and other sensitive system activity.</p>
        </div>
        <div class="audit-actions">
            <a class="audit-btn ghost" href="<?php echo e(route('audit-trail.index')); ?>">Reset</a>
            <a class="audit-btn primary" href="<?php echo e(route('audit-trail.export', request()->query())); ?>">Export CSV</a>
        </div>
    </div>

    <div class="audit-stats">
        <div class="audit-stat"><span>Filtered Logs</span><strong><?php echo e(number_format($stats['total'] ?? 0)); ?></strong></div>
        <div class="audit-stat"><span>Today</span><strong><?php echo e(number_format($stats['today'] ?? 0)); ?></strong></div>
        <div class="audit-stat"><span>Posting Events</span><strong><?php echo e(number_format($stats['posting'] ?? 0)); ?></strong></div>
        <div class="audit-stat"><span>Security Changes</span><strong><?php echo e(number_format($stats['security'] ?? 0)); ?></strong></div>
    </div>

    <div class="audit-banner">
        <div aria-hidden="true">LOCK</div>
        <div>
            <strong>Read-only accounting evidence.</strong>
            The audit report does not edit accounting data. It records who changed what, when it happened, the target record, request context, and before/after values where available. Posted voucher corrections should remain visible through reversal activity, not physical deletion.
        </div>
    </div>

    <div class="audit-card">
        <form method="GET" class="audit-filter">
            <div>
                <label for="module">Module</label>
                <select id="module" name="module">
                    <option value="">All modules</option>
                    <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($module); ?>" <?php if(($filters['module'] ?? '') === $module): echo 'selected'; endif; ?>><?php echo e($module); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label for="action">Action</label>
                <select id="action" name="action">
                    <option value="">All actions</option>
                    <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($action); ?>" <?php if($selectedAction === $action): echo 'selected'; endif; ?>><?php echo e(\Illuminate\Support\Str::of($action)->replace('_', ' ')->headline()); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label for="user_id">User</label>
                <select id="user_id" name="user_id">
                    <option value="">All users</option>
                    <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($user->id); ?>" <?php if((string)($filters['user_id'] ?? '') === (string)$user->id): echo 'selected'; endif; ?>><?php echo e($user->name); ?> <?php if($user->email): ?>(<?php echo e($user->email); ?>)<?php endif; ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label for="date_from">From</label>
                <input id="date_from" type="date" name="date_from" value="<?php echo e($filters['date_from'] ?? ''); ?>">
            </div>

            <div>
                <label for="date_to">To</label>
                <input id="date_to" type="date" name="date_to" value="<?php echo e($filters['date_to'] ?? ''); ?>">
            </div>

            <div>
                <label for="per_page">Rows</label>
                <select id="per_page" name="per_page">
                    <?php $__currentLoopData = [30, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($size); ?>" <?php if((int)($filters['per_page'] ?? 30) === $size): echo 'selected'; endif; ?>><?php echo e($size); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label for="route">Route</label>
                <input id="route" name="route" value="<?php echo e($filters['route'] ?? ''); ?>" placeholder="transactions, setup, api">
            </div>

            <div class="wide">
                <label for="search">Search</label>
                <input id="search" name="search" value="<?php echo e($filters['search'] ?? ''); ?>" placeholder="module, action, record id, user, URL">
            </div>

            <div class="filter-buttons">
                <button class="audit-btn primary" type="submit">Filter</button>
                <a class="audit-btn ghost" href="<?php echo e(route('audit-trail.index')); ?>">Clear</a>
            </div>
        </form>
    </div>

    <div class="audit-card">
        <div class="audit-table-wrap">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Module / Action</th>
                        <th>Record</th>
                        <th>User</th>
                        <th>Request</th>
                        <th>Changed Fields</th>
                        <th>Change Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $action = $log->action ?: $log->event;
                            $badgeClass = str_contains((string) $action, 'reverse') ? 'reversal' : (str_contains((string) $action, 'posted') || str_contains((string) $action, 'submitted') ? 'posting' : (($log->module ?? '') === 'SecurityAccess' ? 'security' : ''));
                            $changeRows = $log->humanChangeRows();
                            $metadataRows = $log->humanMetadataRows();
                            $changedFields = collect($changeRows)->pluck('field')->all();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo e(optional($log->created_at)->format('d M Y')); ?></strong><br>
                                <span class="audit-muted"><?php echo e(optional($log->created_at)->format('h:i:s A')); ?></span>
                            </td>
                            <td>
                                <strong><?php echo e($log->module_label); ?></strong><br>
                                <span class="audit-badge <?php echo e($badgeClass); ?>"><?php echo e($log->action_label); ?></span>
                            </td>
                            <td>
                                <strong><?php echo e($log->subject_label); ?></strong><br>
                                <span class="audit-muted"><?php echo e($log->auditable_type); ?></span>
                            </td>
                            <td>
                                <strong><?php echo e($log->user?->name ?? 'System'); ?></strong><br>
                                <span class="audit-muted"><?php echo e($log->user?->email ?? '-'); ?></span>
                            </td>
                            <td>
                                <strong><?php echo e($log->request_method ?? '-'); ?></strong>
                                <span class="audit-muted"><?php echo e($log->route_name ?? '-'); ?></span><br>
                                <span class="audit-muted">IP: <?php echo e($log->ip_address ?? '-'); ?></span>
                            </td>
                            <td>
                                <?php if($changedFields !== []): ?>
                                    <div class="audit-fields">
                                        <?php $__currentLoopData = array_slice($changedFields, 0, 8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <span class="audit-field"><?php echo e($field); ?></span>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        <?php if(count($changedFields) > 8): ?>
                                            <span class="audit-field">+<?php echo e(count($changedFields) - 8); ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="audit-muted">No field diff</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="audit-change-panel">
                                    <?php if($changeRows !== []): ?>
                                        <ul class="audit-change-list">
                                            <?php $__currentLoopData = $changeRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php
                                                    $status = strtolower((string) ($row['status'] ?? 'changed'));
                                                    $oldValue = (string) ($row['old'] ?? '—');
                                                    $newValue = (string) ($row['new'] ?? '—');
                                                ?>
                                                <li class="audit-change-line">
                                                    <span class="audit-change-field"><?php echo e($row['field']); ?>:</span>
                                                    <?php if($status === 'added'): ?>
                                                        <span class="audit-change-new"><?php echo e($newValue); ?></span>
                                                        <span class="audit-change-action">-&gt; added</span>
                                                    <?php elseif($status === 'removed'): ?>
                                                        <span class="audit-change-old"><?php echo e($oldValue); ?></span>
                                                        <span class="audit-change-action">-&gt; removed</span>
                                                    <?php else: ?>
                                                        <span class="audit-change-old"><?php echo e($oldValue); ?></span>
                                                        <span class="audit-change-action">-&gt;</span>
                                                        <span class="audit-change-new"><?php echo e($newValue); ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </ul>
                                    <?php else: ?>
                                        <div class="audit-muted">No before/after value change was captured for this activity.</div>
                                    <?php endif; ?>

                                    <?php if($metadataRows !== []): ?>
                                        <div>
                                            <p class="audit-context-title">Additional Context</p>
                                            <ul class="audit-context-list">
                                                <?php $__currentLoopData = $metadataRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <li class="audit-context-line"><strong><?php echo e($row['field']); ?>:</strong> <?php echo e($row['value']); ?></li>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="audit-muted">No audit log entries found for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="audit-footer"><?php echo e($logs->links()); ?></div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/audit/index.blade.php ENDPATH**/ ?>