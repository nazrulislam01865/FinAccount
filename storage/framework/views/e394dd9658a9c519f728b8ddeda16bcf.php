<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title',
    'subtitle' => null,
    'badge' => null,
    'badgeClass' => 'badge-primary',
    'footerLeft' => null,
    'footerRight' => null,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'title',
    'subtitle' => null,
    'badge' => null,
    'badgeClass' => 'badge-primary',
    'footerLeft' => null,
    'footerRight' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->merge(['class' => 'card table-card'])); ?>>
    <div class="card-head">
        <div>
            <h3><?php echo e($title); ?></h3>
            <?php if($subtitle): ?>
                <p><?php echo e($subtitle); ?></p>
            <?php endif; ?>
        </div>
        <?php if($badge): ?>
            <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($badge); ?></span>
        <?php endif; ?>
    </div>

    <?php echo e($slot); ?>


    <?php if($footerLeft || $footerRight): ?>
        <div class="table-footer">
            <span><?php echo e($footerLeft); ?></span>
            <span><?php echo e($footerRight); ?></span>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/components/report/table-card.blade.php ENDPATH**/ ?>