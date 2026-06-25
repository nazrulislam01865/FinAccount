const modal = document.getElementById('coa-modal');

if (modal) {
    const form = document.getElementById('coa-form');
    const title = document.getElementById('coa-modal-title');
    const method = document.getElementById('coa-method');
    const accountId = document.getElementById('coa-account-id');
    const code = document.getElementById('coa-code');
    const name = document.getElementById('coa-name');
    const type = document.getElementById('coa-type');
    const normal = document.getElementById('coa-normal');
    const active = document.getElementById('coa-active');

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

    const openCreate = () => {
        clearFieldErrors();
        title.textContent = 'Add COA Account';
        form.action = modal.dataset.storeUrl;
        method.disabled = true;
        accountId.value = '';
        originalType = '';
        originalCode = '';
        name.value = '';
        type.value = modal.dataset.defaultType || '';
        code.value = type.selectedOptions[0]?.dataset.nextCode || modal.dataset.defaultCode || '';
        normal.value = modal.dataset.defaultNormal || '';
        active.checked = true;
        showModal();
        setDraftContext('create');
    };

    const openEdit = (button) => {
        clearFieldErrors();
        title.textContent = 'Edit COA Account';
        form.action = button.dataset.updateUrl;
        method.disabled = false;
        method.value = 'PUT';
        accountId.value = button.dataset.accountId;
        originalType = button.dataset.type;
        originalCode = button.dataset.code;
        code.value = button.dataset.code;
        name.value = button.dataset.name;
        type.value = button.dataset.type;
        normal.value = button.dataset.normal;
        active.checked = button.dataset.active === '1';
        showModal();
        setDraftContext('edit', button.dataset.accountId);
    };

    let originalType = type.value;
    let originalCode = code.value;

    type.addEventListener('change', () => {
        if (accountId.value && type.value === originalType) {
            code.value = originalCode;
            return;
        }

        code.value = type.selectedOptions[0]?.dataset.nextCode || '';
        code.dispatchEvent(new Event('input', { bubbles: true }));
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
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });

    if (modal.classList.contains('show')) {
        document.body.classList.add('hg-modal-open');
    }
}
