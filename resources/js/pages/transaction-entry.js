const page = document.querySelector('[data-transaction-entry]');

if (page) {
    const head = document.getElementById('transaction_head_id');
    const money = document.getElementById('money_account_id');
    const party = document.getElementById('party_id');
    const amount = document.getElementById('amount');
    const moneyField = document.getElementById('money-field');
    const partyField = document.getElementById('party-field');
    const preview = document.getElementById('journal-preview');
    const emptyPreviewTemplate = document.getElementById('journal-preview-empty-template');
    const form = document.querySelector('[data-transaction-form]');
    const previewUrl = preview.dataset.previewUrl;
    let previewTimer;

    const filterPartyOptions = (partyType) => {
        [...party.options].forEach((option) => {
            if (!option.value) return;

            const allowed = partyType === 'Any' || option.dataset.partyType === partyType;
            option.hidden = !allowed;
            option.disabled = !allowed;
        });

        const selected = party.options[party.selectedIndex];

        if (selected && selected.disabled) {
            party.value = '';
            return true;
        }

        return false;
    };

    const refreshPreview = async () => {
        if (!head.value) {
            preview.innerHTML = emptyPreviewTemplate.innerHTML;
            moneyField.classList.add('hidden');
            partyField.classList.add('hidden');
            money.required = false;
            party.required = false;
            return;
        }

        const params = new URLSearchParams({
            transaction_head_id: head.value,
            money_account_id: money.value,
            party_id: party.value,
            amount: amount.value || '0',
        });

        try {
            const response = await fetch(`${previewUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Preview request failed.');
            }

            const data = await response.json();
            preview.innerHTML = data.html;
            moneyField.classList.toggle('hidden', !data.moneyRequired);
            partyField.classList.toggle('hidden', !data.partyRequired);
            money.required = data.moneyRequired;
            party.required = data.partyRequired;

            const partySelectionCleared = filterPartyOptions(data.partyType);

            if (partySelectionCleared) {
                await refreshPreview();
            }
        } catch (error) {
            preview.innerHTML = '<div class="hg-notice">The journal preview could not be loaded. You may retry by changing a field.</div>';
        }
    };

    const schedulePreview = () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 180);
    };

    [head, money, party].forEach((element) => element.addEventListener('change', refreshPreview));
    amount.addEventListener('input', schedulePreview);

    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-submit-button]');
        button.disabled = true;
        button.textContent = button.textContent.trim() === 'Update Transaction' ? 'Updating...' : 'Posting...';
    });

    refreshPreview();
}
