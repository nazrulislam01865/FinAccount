const syncRuleSettlementOptions = (form) => {
    const typeSelect = form.querySelector('[data-rule-transaction-type]');
    const settlementSelect = form.querySelector('[data-rule-settlement-type]');
    if (!typeSelect || !settlementSelect) return;

    let allowed = [];
    try {
        allowed = JSON.parse(typeSelect.selectedOptions[0]?.dataset.allowedSettlements || '[]');
    } catch (_) {
        allowed = [];
    }

    let firstVisible = null;
    Array.from(settlementSelect.options).forEach((option) => {
        const visible = allowed.includes(option.value);
        option.hidden = !visible;
        option.disabled = !visible;
        if (visible && !firstVisible) firstVisible = option;
    });

    if (!allowed.includes(settlementSelect.value) && firstVisible) {
        settlementSelect.value = firstVisible.value;
    }
};

const syncRuleHeadOptions = (form) => {
    const typeSelect = form.querySelector('[data-rule-transaction-type]');
    const headSelect = form.querySelector('[data-rule-transaction-head]');
    if (!typeSelect || !headSelect) return;

    const category = typeSelect.value.toLowerCase();
    let currentIsVisible = headSelect.value === '';

    Array.from(headSelect.options).forEach((option) => {
        if (!option.value) {
            option.hidden = false;
            option.disabled = false;
            return;
        }

        const visible = (option.dataset.headCategory || '').toLowerCase() === category;
        option.hidden = !visible;
        option.disabled = !visible;
        if (visible && option.value === headSelect.value) currentIsVisible = true;
    });

    if (!currentIsVisible) headSelect.value = '';
};

const syncAccountingRuleForm = (form) => {
    syncRuleSettlementOptions(form);
    syncRuleHeadOptions(form);
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accounting-rule-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-rule-transaction-type]');
        typeSelect?.addEventListener('change', () => syncAccountingRuleForm(form));
        form.addEventListener('hisebghor:setup-values-applied', () => {
            window.setTimeout(() => syncAccountingRuleForm(form), 0);
        });
        syncAccountingRuleForm(form);
    });
});
