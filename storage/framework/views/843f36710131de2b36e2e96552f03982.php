<?php $__env->startSection('title', 'Landing Admin Dashboard | HisebGhor'); ?>
<?php $__env->startSection('page_heading', 'Landing Admin Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-title">
    <div>
        <span class="page-label">Landing Page</span>
        <h2>Landing Page Admin Dashboard</h2>
        <p>Separate control center for managing public HisebGhor landing content, demo/login flow, sections, and inquiries.</p>
        <?php if($updatedAt): ?>
            <p class="hint" style="margin-top:6px">Last updated <?php echo e($updatedAt->format('d M Y h:i A')); ?><?php if($updatedBy): ?> by <?php echo e($updatedBy->name); ?><?php endif; ?>.</p>
        <?php endif; ?>
    </div>
    <div class="actions" style="border-top:0;padding-top:0">
        <a href="<?php echo e(route('landing-admin.edit', ['section' => 'basic'])); ?>" class="button btn-primary">Edit Landing Page</a>
        <a href="<?php echo e(route('landing.public')); ?>" target="_blank" class="button btn-outline">Open Public Page</a>
    </div>
</div>

<div class="landing-dashboard-grid">
    <div class="landing-dashboard-card">
        <small>Publish Status</small>
        <strong><?php echo e($isPublished ? 'Live' : 'Draft'); ?></strong>
        <p><?php echo e($isPublished ? 'Public landing page is visible.' : 'Landing page is hidden except preview mode.'); ?></p>
    </div>
    <div class="landing-dashboard-card">
        <small>Enabled Sections</small>
        <strong><?php echo e($enabledSections); ?>/<?php echo e($totalSections); ?></strong>
        <p>Hero, features, pricing, FAQ, contact and other public sections.</p>
    </div>
    <div class="landing-dashboard-card">
        <small>Total Inquiries</small>
        <strong><?php echo e(number_format($totalInquiries)); ?></strong>
        <p>Historical demo/contact requests stored from the landing page.</p>
    </div>
    <div class="landing-dashboard-card">
        <small>New Inquiries</small>
        <strong><?php echo e(number_format((int) ($statusCounts[\App\Models\LandingPageInquiry::STATUS_NEW] ?? 0))); ?></strong>
        <p>New landing inquiries waiting for review.</p>
    </div>
</div>

<div class="landing-admin-panel" style="margin-bottom:18px">
    <div class="landing-admin-panel-head">
        <div>
            <h3>Quick Edit Sections</h3>
            <p>Open only the section you want to manage. The accounting dashboard and setup menus are kept separate.</p>
        </div>
    </div>
    <div class="landing-admin-panel-body">
        <div class="landing-admin-quick-grid">
            <?php $__currentLoopData = [
                ['section' => 'basic', 'title' => 'Basic Setup', 'desc' => 'Brand, SEO, language, colors'],
                ['section' => 'nav', 'title' => 'Navigation', 'desc' => 'Landing menu links'],
                ['section' => 'hero', 'title' => 'Hero Section', 'desc' => 'Main banner and CTA buttons'],
                ['section' => 'features', 'title' => 'Feature Screens', 'desc' => 'Public feature preview cards'],
                ['section' => 'pricing', 'title' => 'Pricing', 'desc' => 'Packages and pricing notes'],
                ['section' => 'faq', 'title' => 'FAQ', 'desc' => 'Common questions'],
                ['section' => 'contact', 'title' => 'Contact', 'desc' => 'Contact/demo area labels'],
                ['section' => 'footer', 'title' => 'Footer', 'desc' => 'Footer copy'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a class="landing-admin-quick-card" href="<?php echo e(route('landing-admin.edit', ['section' => $item['section']])); ?>">
                    <div>
                        <strong><?php echo e($item['title']); ?></strong>
                        <span><?php echo e($item['desc']); ?></span>
                    </div>
                    <span class="landing-admin-status">Edit</span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</div>

<div class="landing-admin-panel">
    <div class="landing-admin-panel-head">
        <div>
            <h3>Latest Landing Inquiries</h3>
            <p>Recent inquiries are kept here for tracking. Demo CTA buttons now send visitors to the system login page.</p>
        </div>
        <a href="<?php echo e(route('landing-admin.edit', ['section' => 'contact'])); ?>" class="button btn-outline">Manage Contact</a>
    </div>
    <div class="landing-admin-panel-body">
        <div class="landing-admin-table-wrap">
            <table class="landing-admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Business</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $latestInquiries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inquiry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($inquiry->name); ?></td>
                            <td><?php echo e($inquiry->business_name ?: '—'); ?></td>
                            <td><?php echo e($inquiry->mobile ?: '—'); ?></td>
                            <td><span class="landing-admin-status <?php echo e($inquiry->status === \App\Models\LandingPageInquiry::STATUS_NEW ? '' : 'muted'); ?>"><?php echo e(ucfirst($inquiry->status)); ?></span></td>
                            <td><?php echo e($inquiry->created_at?->format('d M Y h:i A')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="5" style="text-align:center;color:#667085;padding:26px">No landing inquiries yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.landing-admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/admin/dashboard.blade.php ENDPATH**/ ?>