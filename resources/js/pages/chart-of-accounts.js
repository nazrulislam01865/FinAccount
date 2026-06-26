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

    const showModal = () => {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hg-modal-open');
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

        level.value = String(calculatedLevel);

        if (parentId) {
            if (inheritedType) type.value = inheritedType;
            type.disabled = true;
            typeHidden.disabled = false;
            typeHidden.value = type.value;
            typeHelp.textContent = `Inherited from the selected Level ${parentLevel} parent.`;
        } else {
            type.disabled = false;
            typeHidden.disabled = true;
            typeHidden.value = type.value;
            typeHelp.textContent = isLegacyUnassignedLedger
                ? 'Existing flat posting ledger. Its type remains editable until it is assigned to a Level 2 parent.'
                : 'Level 1 account type can be selected. Child levels inherit their parent type.';
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
        }
        typeHidden.value = type.value;
    });

    form.addEventListener('submit', () => {
        syncHierarchy({ preserveOriginal: Boolean(accountId.value && parent.value === originalParentId) });
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
