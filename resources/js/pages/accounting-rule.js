const form = document.querySelector('[data-accounting-rule-form]');

if (form) {
    const moneyRequired = form.querySelector('[name="money_required"][type="checkbox"]');
    const partyRequired = form.querySelector('[name="party_required"][type="checkbox"]');
    const generatesInvoice = form.querySelector('[name="generates_invoice"][type="checkbox"]');
    const invoiceTitle = form.querySelector('[name="invoice_title"]');
    const category = form.querySelector('[name="category"]');
    const debitSource = form.querySelector('[name="debit_source"]');
    const creditSource = form.querySelector('[name="credit_source"]');
    const supportsSplit = form.querySelector('[name="supports_split_transaction"][type="checkbox"]');
    const invoiceFields = [...form.querySelectorAll('[data-invoice-field]')];

    const selectedOption = (select) => select?.options[select.selectedIndex];

    const sourceRequires = (source, key) => selectedOption(source)?.dataset?.[key] === '1';

    const synchronizeRequirements = () => {
        const moneyFromSource = sourceRequires(debitSource, 'requiresMoney') || sourceRequires(creditSource, 'requiresMoney');
        const partyFromSource = sourceRequires(debitSource, 'requiresParty') || sourceRequires(creditSource, 'requiresParty');
        const split = supportsSplit?.checked === true;

        if (moneyRequired) {
            moneyRequired.checked = moneyFromSource || split;
        }

        if (partyRequired) {
            partyRequired.checked = partyFromSource || split;
        }
    };

    const synchronizeInvoiceFields = () => {
        const isSales = category?.value === 'Sales';

        invoiceFields.forEach((field) => field.classList.toggle('hidden', !isSales));

        if (generatesInvoice) {
            generatesInvoice.disabled = !isSales;

            if (!isSales) {
                generatesInvoice.checked = false;
            }
        }

        if (invoiceTitle) {
            invoiceTitle.disabled = !isSales || !generatesInvoice?.checked;

            if (isSales && generatesInvoice?.checked && !invoiceTitle.value.trim()) {
                invoiceTitle.value = 'Sales Invoice';
            }
        }
    };

    [debitSource, creditSource, supportsSplit].forEach((element) => {
        element?.addEventListener('change', synchronizeRequirements);
    });

    category?.addEventListener('change', () => {
        synchronizeInvoiceFields();
        synchronizeRequirements();
    });

    generatesInvoice?.addEventListener('change', synchronizeInvoiceFields);

    document.querySelectorAll('[data-setup-target="accounting-rule-modal"]').forEach((button) => {
        button.addEventListener('click', () => window.setTimeout(() => {
            synchronizeRequirements();
            synchronizeInvoiceFields();
        }, 0));
    });

    form.addEventListener('submit', () => {
        synchronizeRequirements();
        synchronizeInvoiceFields();
    });

    synchronizeRequirements();
    synchronizeInvoiceFields();
}
