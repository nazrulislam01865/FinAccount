const enableAllRuleSettlementOptions = (form) => {
    const settlementSelect = form.querySelector('[data-rule-settlement-type]');
    if (!settlementSelect) return;

    Array.from(settlementSelect.options).forEach((option) => {
        option.hidden = false;
        option.disabled = false;
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accounting-rule-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-rule-transaction-type]');

        typeSelect?.addEventListener('change', () => {
            enableAllRuleSettlementOptions(form);
        });

        form.addEventListener('hisebghor:setup-values-applied', () => {
            enableAllRuleSettlementOptions(form);
        });

        enableAllRuleSettlementOptions(form);
    });
});
