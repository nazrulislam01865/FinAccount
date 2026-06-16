const form = document.querySelector('[data-accounting-rule-form]');

if (form) {
    const debitSource = form.querySelector('[name="debit_source"]');
    const creditSource = form.querySelector('[name="credit_source"]');
    const moneyRequired = form.querySelector('[name="money_required"][type="checkbox"]');
    const partyRequired = form.querySelector('[name="party_required"][type="checkbox"]');

    const selectedOption = (select) => select?.options[select.selectedIndex];

    const synchronizeRequirements = () => {
        const options = [selectedOption(debitSource), selectedOption(creditSource)].filter(Boolean);

        if (options.some((option) => option.dataset.requiresMoney === '1')) {
            moneyRequired.checked = true;
        }

        if (options.some((option) => option.dataset.requiresParty === '1')) {
            partyRequired.checked = true;
        }
    };

    debitSource?.addEventListener('change', synchronizeRequirements);
    creditSource?.addEventListener('change', synchronizeRequirements);
    synchronizeRequirements();
}
