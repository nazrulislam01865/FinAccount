document.querySelectorAll('[data-bulk-action-form]').forEach((form) => {
    const group = form.dataset.bulkGroup;
    const entity = form.dataset.bulkEntity || 'record';

    if (!group) return;

    const checkboxes = Array.from(document.querySelectorAll('[data-bulk-checkbox]'))
        .filter((checkbox) => checkbox.dataset.bulkCheckbox === group);
    const master = Array.from(document.querySelectorAll('[data-bulk-master]'))
        .find((checkbox) => checkbox.dataset.bulkMaster === group);
    const toolbar = Array.from(document.querySelectorAll('[data-bulk-toolbar]'))
        .find((element) => element.dataset.bulkToolbar === group);
    const countLabel = Array.from(document.querySelectorAll('[data-bulk-count]'))
        .find((element) => element.dataset.bulkCount === group);
    const actionSelect = Array.from(document.querySelectorAll('[data-bulk-action-select]'))
        .find((element) => element.dataset.bulkActionSelect === group);
    const applyButton = Array.from(document.querySelectorAll('[data-bulk-apply]'))
        .find((element) => element.dataset.bulkApply === group);
    const clearButton = Array.from(document.querySelectorAll('[data-bulk-clear]'))
        .find((element) => element.dataset.bulkClear === group);

    const selected = () => checkboxes.filter((checkbox) => checkbox.checked);

    const syncApplyStyle = () => {
        if (!applyButton) return;

        applyButton.classList.remove('hg-btn-primary', 'hg-btn-warning', 'hg-btn-danger');

        if (actionSelect?.value === 'delete') {
            applyButton.classList.add('hg-btn-danger');
            applyButton.textContent = 'Delete Selected';
        } else if (actionSelect?.value === 'deactivate') {
            applyButton.classList.add('hg-btn-warning');
            applyButton.textContent = 'Set Inactive';
        } else {
            applyButton.classList.add('hg-btn-primary');
            applyButton.textContent = actionSelect?.value === 'activate' ? 'Set Active' : 'Apply';
        }
    };

    const sync = () => {
        const count = selected().length;

        if (count === 0 && actionSelect) actionSelect.value = '';
        const hasAction = Boolean(actionSelect?.value);

        if (toolbar) toolbar.hidden = count === 0;
        if (countLabel) countLabel.textContent = `${count.toLocaleString()} selected`;
        if (applyButton) applyButton.disabled = count === 0 || !hasAction;

        if (master) {
            master.checked = count > 0 && count === checkboxes.length;
            master.indeterminate = count > 0 && count < checkboxes.length;
        }

        syncApplyStyle();
    };

    master?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = master.checked; });
        sync();
    });

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', sync));
    actionSelect?.addEventListener('change', sync);

    clearButton?.addEventListener('click', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = false; });
        if (master) master.checked = false;
        if (actionSelect) actionSelect.value = '';
        sync();
    });

    form.addEventListener('submit', (event) => {
        const count = selected().length;
        const action = actionSelect?.value || '';

        if (count === 0) {
            event.preventDefault();
            window.alert(`Select at least one ${entity} record.`);
            return;
        }

        if (!action) {
            event.preventDefault();
            window.alert('Choose a bulk action first.');
            actionSelect?.focus();
            return;
        }

        if (action === 'activate') {
            if (!window.confirm(`Set ${count.toLocaleString()} selected ${entity} ${count === 1 ? 'record' : 'records'} to Active?`)) {
                event.preventDefault();
            }
            return;
        }

        if (action === 'deactivate' && !window.confirm(
            `Set ${count.toLocaleString()} selected ${entity} ${count === 1 ? 'record' : 'records'} to Inactive? They will no longer be available for new transactions.`,
        )) {
            event.preventDefault();
        }
    });

    sync();
});
