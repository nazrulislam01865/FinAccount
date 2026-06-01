<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label',
    'value',
    'note' => null,
    'tone' => 'primary',
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
    'label',
    'value',
    'note' => null,
    'tone' => 'primary',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $toneClass = match ($tone) {
        'success' => 'report-tone-success',
        'danger' => 'report-tone-danger',
        'warning' => 'report-tone-warning',
        'muted' => 'report-tone-muted',
        default => 'report-tone-primary',
    };
?>

<div <?php echo e($attributes->merge(['class' => 'card stat-card'])); ?>>
    <small><?php echo e($label); ?></small>
    <strong class="<?php echo e($toneClass); ?>"><?php echo e($value); ?></strong>
    <?php if($note): ?>
        <span><?php echo e($note); ?></span>
    <?php endif; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/components/report/stat-card.blade.php ENDPATH**/ ?>