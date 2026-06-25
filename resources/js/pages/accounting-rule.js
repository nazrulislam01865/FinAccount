const syncRuleSettlementOptions = (form) => {
    const typeSelect = form.querySelector('[data-rule-transaction-type]');
    const settlementSelect = form.querySelector('[data-rule-settlement-type]');
    if (!typeSelect || !settlementSelect) return;

    const allOptions = Array.from(settlementSelect.options);
    const allValues = allOptions.map((option) => option.value);
    let configuredAllowed = [];

    try {
        configuredAllowed = JSON.parse(typeSelect.selectedOptions[0]?.dataset.allowedSettlements || '[]');
    } catch (_) {
        configuredAllowed = [];
    }

    // Payment types are system-defined. If cloud metadata is missing, stale,
    // or malformed, keep every canonical option available instead of locking
    // the field to the first option.
    const allowed = Array.isArray(configuredAllowed) && configuredAllowed.length > 0
        ? configuredAllowed.filter((value) => allValues.includes(value))
        : allValues;
    const effectiveAllowed = allowed.length > 0 ? allowed : allValues;

    let firstVisible = null;
    allOptions.forEach((option) => {
        const visible = effectiveAllowed.includes(option.value);
        option.hidden = !visible;
        option.disabled = !visible;
        if (visible && !firstVisible) firstVisible = option;
    });

    if (!effectiveAllowed.includes(settlementSelect.value) && firstVisible) {
        settlementSelect.value = firstVisible.value;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accounting-rule-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-rule-transaction-type]');
        const modalId = form.closest('[data-setup-modal]')?.id;

        typeSelect?.addEventListener('change', () => syncRuleSettlementOptions(form));

        if (modalId) {
            document.querySelectorAll(`[data-setup-target="${modalId}"]`).forEach((button) => {
                button.addEventListener('click', () => {
                    window.setTimeout(() => syncRuleSettlementOptions(form), 0);
                });
            });
        }

        syncRuleSettlementOptions(form);
    });
});
