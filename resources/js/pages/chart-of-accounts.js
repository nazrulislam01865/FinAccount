const modal = document.getElementById('coa-modal');

if (modal) {
    const form = document.getElementById('coa-form');
    const title = document.getElementById('coa-modal-title');
    const method = document.getElementById('coa-method');
    const accountId = document.getElementById('coa-account-id');
    const parent = document.getElementById('coa-parent');
    const level = document.getElementById('coa-level');
    const code = document.getElementById('coa-code');
    const codeHelp = document.getElementById('coa-code-help');
    const name = document.getElementById('coa-name');
    const type = document.getElementById('coa-type');
    const typeHidden = document.getElementById('coa-type-hidden');
    const typeHelp = document.getElementById('coa-type-help');
    const normal = document.getElementById('coa-normal');
    const normalHidden = document.getElementById('coa-normal-hidden');
    const normalHelp = document.getElementById('coa-normal-help');
    const normalField = document.getElementById('coa-normal-field');
    const active = document.getElementById('coa-active');

    let originalParentId = modal.dataset.editingParentId || '';
    let originalLevel = Number(modal.dataset.editingLevel || level.value || 1);
    let originalCode = modal.dataset.editingCode || code.value;
    let originalType = modal.dataset.editingType || type.value;
    let hierarchyLocked = Number(modal.dataset.editingChildrenCount || 0) > 0;

    const clearFieldErrors = () => {
        form.querySelectorAll('.hg-field-error').forEach((error) => error.remove());
    };

    const setDraftContext = (mode, id = '') => {
        const base = form.dataset.draftKeyBase;
        if (!base) return;
        const key = mode === 'edit' && id ? `${base}.edit.${id}` : `${base}.create`;
        form.dataset.draftKey = key;
        form.dispatchEvent(new CustomEvent('hisebghor:draft-context', {
            detail: { key, title: mode === 'edit' ? 'Edit Chart of Account' : 'Add Chart of Account' },
        }));
    };

    const refreshSearchableSelects = () => window.HisebGhorSearchableSelect?.refreshAll?.();

    const showModal = () => {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hg-modal-open');
        refreshSearchableSelects();
        window.setTimeout(() => name.focus(), 0);
    };

    const closeModal = () => {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hg-modal-open');
    };

    const unlockParentOptions = () => {
        [...parent.options].forEach((option) => { option.disabled = false; });
    };

    const typeDefaultNormal = () => type.selectedOptions[0]?.dataset.defaultNormal || '';
    const defaultNormalForType = () => typeDefaultNormal() || normal.value || modal.dataset.defaultNormal || '';

    const setInheritedNormalBalance = (value, parentLevel = 0) => {
        const inherited = value || normal.value || defaultNormalForType();
        if (inherited) normal.value = inherited;
        normal.disabled = true;
        normalHidden.disabled = false;
        normalHidden.value = normal.value;
        normalField?.classList.add('is-inherited');
        if (normalHelp) {
            normalHelp.textContent = parentLevel
                ? `Inherited automatically from the selected Level ${parentLevel} parent.`
                : 'Inherited automatically from the selected parent.';
        }
    };

    const setEditableNormalBalance = (message = '') => {
        normal.disabled = false;
        normalHidden.disabled = true;
        normalHidden.value = normal.value;
        normalField?.classList.remove('is-inherited');
        if (!normal.value) normal.value = defaultNormalForType();
        if (normalHelp) {
            normalHelp.textContent = message || 'Level 1 normal balance can be selected. Level 2 and Level 3 inherit from their parent.';
        }
    };

    const syncHierarchy = ({ preserveOriginal = false } = {}) => {
        const selected = parent.selectedOptions[0];
        const parentId = parent.value;
        const parentLevel = Number(selected?.dataset.level || 0);
        const isLegacyUnassignedLedger = Boolean(accountId.value)
            && !originalParentId
            && !parentId
            && originalLevel === 3;
        const calculatedLevel = isLegacyUnassignedLedger
            ? 3
            : (parentId ? parentLevel + 1 : 1);
        const inheritedType = selected?.dataset.type || '';
        const inheritedNormal = selected?.dataset.normal || '';

        level.value = String(calculatedLevel);

        if (parentId) {
            if (inheritedType) type.value = inheritedType;
            type.disabled = true;
            typeHidden.disabled = false;
            typeHidden.value = type.value;
            typeHelp.textContent = `Inherited from the selected Level ${parentLevel} parent.`;
            setInheritedNormalBalance(inheritedNormal, parentLevel);
        } else {
            type.disabled = false;
            typeHidden.disabled = true;
            typeHidden.value = type.value;
            typeHelp.textContent = isLegacyUnassignedLedger
                ? 'Existing flat posting ledger. Its type remains editable until it is assigned to a Level 2 parent.'
                : 'Level 1 account type can be selected. Child levels inherit their parent type.';
            setEditableNormalBalance(isLegacyUnassignedLedger
                ? 'Existing flat posting ledger. Its normal balance remains editable until it is assigned to a parent.'
                : 'Level 1 normal balance can be selected. Child levels inherit from their parent.');
        }

        const shouldKeepOriginal = preserveOriginal
            || (accountId.value && String(parentId) === String(originalParentId));
        code.value = shouldKeepOriginal
            ? originalCode
            : (selected?.dataset.nextCode || '');
        codeHelp.textContent = isLegacyUnassignedLedger
            ? 'Existing flat ledger preserved as Level 3. Select a Level 2 parent to place it in the hierarchy.'
            : (code.value
            ? `Generated automatically for Level ${calculatedLevel}.`
            : 'No code is available under this parent. Create another parent group or reorganise its children.');

        level.dispatchEvent(new Event('input', { bubbles: true }));
        code.dispatchEvent(new Event('input', { bubbles: true }));
        refreshSearchableSelects();
    };

    const openCreate = () => {
        clearFieldErrors();
        unlockParentOptions();
        title.textContent = 'Add COA Account';
        form.action = modal.dataset.storeUrl;
        method.disabled = true;
        accountId.value = '';
        originalParentId = '';
        originalLevel = 1;
        originalCode = modal.dataset.rootNextCode || '';
        originalType = modal.dataset.defaultType || type.value;
        hierarchyLocked = false;
        name.value = '';
        parent.value = '';
        type.value = modal.dataset.defaultType || '';
        normal.value = modal.dataset.defaultNormal || '';
        active.checked = true;
        syncHierarchy();
        showModal();
        setDraftContext('create');
    };

    const openEdit = (button) => {
        clearFieldErrors();
        unlockParentOptions();
        title.textContent = 'Edit COA Account';
        form.action = button.dataset.updateUrl;
        method.disabled = false;
        method.value = 'PUT';
        accountId.value = button.dataset.accountId;
        originalParentId = button.dataset.parentId || '';
        originalLevel = Number(button.dataset.level || 1);
        originalCode = button.dataset.code;
        originalType = button.dataset.type;
        hierarchyLocked = Number(button.dataset.childrenCount || 0) > 0;
        parent.value = originalParentId;
        code.value = originalCode;
        name.value = button.dataset.name;
        type.value = button.dataset.type;
        normal.value = button.dataset.normal;
        active.checked = button.dataset.active === '1';

        const selfOption = [...parent.options].find((option) => option.value === button.dataset.accountId);
        if (selfOption) selfOption.disabled = true;

        syncHierarchy({ preserveOriginal: true });
        refreshSearchableSelects();
        showModal();
        setDraftContext('edit', button.dataset.accountId);
    };

    parent.addEventListener('change', () => {
        if (hierarchyLocked && String(parent.value) !== String(originalParentId)) {
            parent.value = originalParentId;
            window.alert('This account has child accounts. Move or delete its children before changing the parent.');
        }
        syncHierarchy();
    });

    type.addEventListener('change', () => {
        if (parent.value) {
            type.value = parent.selectedOptions[0]?.dataset.type || type.value;
        } else if (hierarchyLocked && accountId.value && type.value !== originalType) {
            type.value = originalType;
            window.alert('This account has child accounts. Move or delete its children before changing the account type.');
        } else {
            const defaultNormal = typeDefaultNormal();
            if (defaultNormal) normal.value = defaultNormal;
        }
        typeHidden.value = type.value;
        normalHidden.value = normal.value;
        refreshSearchableSelects();
    });

    normal.addEventListener('change', () => {
        normalHidden.value = normal.value;
        refreshSearchableSelects();
    });

    form.addEventListener('submit', () => {
        syncHierarchy({ preserveOriginal: Boolean(accountId.value && parent.value === originalParentId) });
        normalHidden.value = normal.value;
    });

    document.querySelectorAll('[data-coa-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.coaOpen === 'edit') {
                openEdit(button);
            } else {
                openCreate();
            }
        });
    });

    document.querySelectorAll('[data-coa-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });

    if (modal.classList.contains('show')) {
        document.body.classList.add('hg-modal-open');
        syncHierarchy({ preserveOriginal: Boolean(accountId.value && parent.value === originalParentId) });
    }
}


const bulkForm = document.querySelector('[data-coa-bulk-form]');

if (bulkForm) {
    const checkboxes = Array.from(document.querySelectorAll('[data-coa-bulk-checkbox]'));
    const master = document.querySelector('[data-coa-bulk-master]');
    const toolbar = document.querySelector('[data-coa-bulk-toolbar]');
    const countLabel = document.querySelector('[data-coa-bulk-count]');
    const deleteButton = document.querySelector('[data-coa-bulk-delete]');

    const syncBulkState = () => {
        const checked = checkboxes.filter((checkbox) => checkbox.checked);
        const count = checked.length;

        if (toolbar) toolbar.hidden = count === 0;
        if (deleteButton) deleteButton.disabled = count === 0;
        if (countLabel) countLabel.textContent = `${count.toLocaleString()} selected`;

        if (master) {
            master.checked = count > 0 && count === checkboxes.length;
            master.indeterminate = count > 0 && count < checkboxes.length;
        }
    };

    master?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = master.checked; });
        syncBulkState();
    });

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', syncBulkState));

    bulkForm.addEventListener('submit', (event) => {
        if (!checkboxes.some((checkbox) => checkbox.checked)) {
            event.preventDefault();
            window.alert('Select at least one Chart of Account record to delete.');
        }
    });

    syncBulkState();
}

const coaFilterForm = document.querySelector('[data-coa-filter-form]');

if (coaFilterForm) {
    const searchInput = coaFilterForm.querySelector('[data-coa-filter-search]');
    const levelSelect = coaFilterForm.querySelector('[data-coa-filter-level]');
    const clearButton = coaFilterForm.querySelector('[data-coa-filter-clear]');
    const rows = Array.from(document.querySelectorAll('[data-coa-row]'));
    const emptyRow = document.querySelector('[data-coa-search-empty]');
    const draftRows = Array.from(document.querySelectorAll('.hg-table-draft-row'));

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const keywordsFor = (value) => {
        const normalized = normalize(value);
        const extras = [];
        if (normalized.includes('rent')) extras.push('rental lease payable expense bill');
        if (normalized.includes('payable')) extras.push('due liability creditor supplier bill outstanding');
        if (normalized.includes('receivable')) extras.push('due asset debtor customer collection outstanding');
        if (normalized.includes('asset')) extras.push('assets assest cash bank receivable inventory stock');
        if (normalized.includes('liability')) extras.push('liabilities payable due loan creditor');
        if (normalized.includes('equity')) extras.push('oe owner capital owners equity');
        return normalize([normalized, ...extras].join(' '));
    };

    const applyCoaFilter = () => {
        const query = normalize(searchInput?.value || '');
        const queryCompact = query.replace(/\s+/g, '');
        const tokens = query.split(' ').filter(Boolean);
        const level = String(levelSelect?.value || '0');
        let visibleCount = 0;
        const filterActive = query !== '' || level !== '0';

        rows.forEach((row) => {
            const haystack = keywordsFor(row.dataset.search || row.textContent || '');
            const haystackCompact = haystack.replace(/\s+/g, '');
            const rowLevel = String(row.dataset.level || '0');
            const levelMatches = level === '0' || rowLevel === level;
            const searchMatches = query === ''
                || haystack.includes(query)
                || (queryCompact !== '' && haystackCompact.includes(queryCompact))
                || tokens.every((token) => haystack.includes(token));
            const show = levelMatches && searchMatches;

            row.hidden = !show;
            if (show) visibleCount += 1;
        });

        if (emptyRow) {
            emptyRow.hidden = visibleCount > 0;
        }

        draftRows.forEach((row) => {
            row.hidden = filterActive;
        });

        if (clearButton) {
            clearButton.hidden = !filterActive;
        }
    };

    coaFilterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        applyCoaFilter();
    });

    searchInput?.addEventListener('input', applyCoaFilter);
    levelSelect?.addEventListener('change', applyCoaFilter);
    clearButton?.addEventListener('click', (event) => {
        event.preventDefault();
        if (searchInput) searchInput.value = '';
        if (levelSelect) levelSelect.value = '0';
        applyCoaFilter();
        if (window.history?.replaceState) {
            window.history.replaceState({}, '', clearButton.href || window.location.pathname);
        }
    });

    applyCoaFilter();
}
