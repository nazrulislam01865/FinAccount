const parseJson = (value, fallback = []) => {
    try { return JSON.parse(value || '[]'); } catch (_) { return fallback; }
};

const syncTransactionHeadForm = (form) => {
    const typeSelect = form.querySelector('[data-head-transaction-type]');
    const selected = typeSelect?.selectedOptions[0];
    if (!selected) return;

    const allowedSettlements = parseJson(selected.dataset.allowedSettlements);
    form.querySelectorAll('[data-settlement-wrapper]').forEach((wrapper) => {
        const input = wrapper.querySelector('input');
        const allowed = allowedSettlements.includes(wrapper.dataset.settlementWrapper);
        wrapper.hidden = !allowed;
        input.disabled = !allowed;
        if (!allowed) input.checked = false;
    });

    const checkedAllowed = Array.from(form.querySelectorAll('input[name="allowed_settlements[]"]:checked:not(:disabled)'));
    if (checkedAllowed.length === 0) {
        const first = form.querySelector('input[name="allowed_settlements[]"]:not(:disabled)');
        if (first) first.checked = true;
    }

    const expectedPartyType = selected.dataset.partyType || 'Any';
    const partySelect = form.querySelector('[data-head-party-type]');
    if (partySelect && expectedPartyType !== 'Any') {
        partySelect.value = expectedPartyType;
    }

    const postingTypes = parseJson(selected.dataset.postingTypes);
    const accountSelect = form.querySelector('[data-head-posting-account]');
    if (accountSelect) {
        Array.from(accountSelect.options).forEach((option) => {
            if (!option.value) return;
            const visible = postingTypes.length === 0 || postingTypes.includes(option.dataset.accountType);
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (accountSelect.selectedOptions[0]?.disabled) accountSelect.value = '';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-transaction-head-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-head-transaction-type]');
        typeSelect?.addEventListener('change', () => syncTransactionHeadForm(form));
        document.querySelectorAll(`[data-setup-target="${form.closest('[data-setup-modal]')?.id}"]`).forEach((button) => {
            button.addEventListener('click', () => window.setTimeout(() => syncTransactionHeadForm(form), 0));
        });
        syncTransactionHeadForm(form);
    });
});
