const slugCode = (value, fallback = 'CODE') => {
    const normalized = String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return (normalized || fallback).slice(0, 50);
};

const initials = (value, fallback = 'X') => {
    const words = String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .split(/[^A-Za-z0-9]+/)
        .filter(Boolean);

    const generated = words.map((word) => word.charAt(0).toUpperCase()).join('');
    return (generated || fallback).slice(0, 10);
};

const bindNameGeneratedCode = (form, nameSelector, codeSelector, fallback) => {
    const name = form.querySelector(nameSelector);
    const code = form.querySelector(codeSelector);
    if (!name || !code) return;

    let protectedSystemCode = code.value.toUpperCase().startsWith('SYS-FEED-');

    name.addEventListener('input', () => {
        if (form.dataset.setupMode === 'edit' && protectedSystemCode) return;
        code.value = slugCode(name.value, fallback);
        code.dispatchEvent(new Event('input', { bubbles: true }));
    });

    form.addEventListener('hisebghor:setup-values-applied', (event) => {
        protectedSystemCode = String(event.detail?.values?.code || code.value || '')
            .toUpperCase()
            .startsWith('SYS-FEED-');
        if (event.detail?.mode === 'create') {
            code.value = name.value ? slugCode(name.value, fallback) : '';
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accounting-rule-form]').forEach((form) => {
        bindNameGeneratedCode(form, '#rule-name', '#rule-code', 'RULE');
    });

    document.querySelectorAll('[data-transaction-head-form]').forEach((form) => {
        bindNameGeneratedCode(form, '#head-name', '#head-code', 'HEAD');
    });

    document.querySelectorAll('[data-master-data-form]').forEach((form) => {
        const label = form.querySelector('[data-master-label]');
        const value = form.querySelector('[data-master-value]');
        if (!label || !value || form.dataset.autoInitial !== '1') return;

        label.addEventListener('input', () => {
            if (form.dataset.setupMode !== 'edit') {
                value.value = initials(label.value, form.dataset.initialFallback || 'X');
                value.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

        form.addEventListener('hisebghor:setup-values-applied', (event) => {
            if (event.detail?.mode === 'create') {
                value.value = label.value ? initials(label.value, form.dataset.initialFallback || 'X') : '';
            }
        });
    });

    document.querySelectorAll('[data-party-form]').forEach((form) => {
        const type = form.querySelector('[data-party-type]');
        const code = form.querySelector('[data-party-code]');
        if (!type || !code) return;

        let originalType = type.value;
        let originalCode = code.value;

        const sync = () => {
            if (form.dataset.setupMode === 'edit' && type.value === originalType) {
                code.value = originalCode;
                return;
            }

            code.value = type.selectedOptions[0]?.dataset.nextCode || '';
            code.dispatchEvent(new Event('input', { bubbles: true }));
        };

        type.addEventListener('change', sync);
        form.addEventListener('hisebghor:setup-values-applied', (event) => {
            originalType = String(event.detail?.values?.type || type.value);
            originalCode = String(event.detail?.values?.code || code.value || '');
            sync();
        });
    });
});
