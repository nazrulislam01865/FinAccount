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

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accounting-rule-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-rule-transaction-type]');
        typeSelect?.addEventListener('change', () => syncRuleSettlementOptions(form));
        syncRuleSettlementOptions(form);
    });
});
