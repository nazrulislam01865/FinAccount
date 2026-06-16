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

    const showModal = () => {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hg-modal-open');
        window.setTimeout(() => code.focus(), 0);
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
        code.value = '';
        name.value = '';
        type.value = modal.dataset.defaultType || '';
        normal.value = modal.dataset.defaultNormal || '';
        active.checked = true;
        showModal();
    };

    const openEdit = (button) => {
        clearFieldErrors();
        title.textContent = 'Edit COA Account';
        form.action = button.dataset.updateUrl;
        method.disabled = false;
        method.value = 'PUT';
        accountId.value = button.dataset.accountId;
        code.value = button.dataset.code;
        name.value = button.dataset.name;
        type.value = button.dataset.type;
        normal.value = button.dataset.normal;
        active.checked = button.dataset.active === '1';
        showModal();
    };

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
