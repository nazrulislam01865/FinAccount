<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'resetRoute',
    'submitLabel' => 'Run',
    'resetLabel' => 'Reset',
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
    'resetRoute',
    'submitLabel' => 'Run',
    'resetLabel' => 'Reset',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->merge(['class' => 'filter-actions'])); ?>>
    <button class="btn-primary" type="submit"><?php echo e($submitLabel); ?></button>
    <a class="button btn-ghost" href="<?php echo e($resetRoute); ?>"><?php echo e($resetLabel); ?></a>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/components/report/filter-actions.blade.php ENDPATH**/ ?>