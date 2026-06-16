document.querySelectorAll('[data-setup-modal]').forEach((modal) => {
    const form = modal.querySelector('[data-setup-form]');
    const title = modal.querySelector('[data-setup-title]');
    const method = form?.querySelector('[data-setup-method]');

    if (!form || !title) return;

    const applyValues = (values = {}) => {
        Object.entries(values).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (!field) return;

            if (field instanceof RadioNodeList) {
                [...field].forEach((item) => {
                    item.checked = String(item.value) === String(value);
                });
                return;
            }

            if (field.type === 'checkbox') {
                field.checked = value === true || value === 1 || value === '1';
            } else {
                field.value = value ?? '';
            }
        });
    };

    const setModeVisibility = (mode) => {
        const isEdit = mode === 'edit';

        form.querySelectorAll('[data-create-only]').forEach((container) => {
            container.hidden = isEdit;
            container.querySelectorAll('input, select, textarea, button').forEach((field) => {
                field.disabled = isEdit;
            });
        });

        form.querySelectorAll('[data-edit-only]').forEach((container) => {
            container.hidden = !isEdit;
            container.querySelectorAll('input, select, textarea, button').forEach((field) => {
                field.disabled = !isEdit;
            });
        });
    };

    const clearFieldErrors = () => {
        form.querySelectorAll('.hg-field-error').forEach((error) => error.remove());
    };

    const showModal = () => {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hg-modal-open');
        window.setTimeout(() => form.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus(), 0);
    };

    const closeModal = () => {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hg-modal-open');
    };

    const openCreate = (button) => {
        clearFieldErrors();
        form.reset();
        form.action = button.dataset.storeUrl || modal.dataset.storeUrl;
        title.textContent = button.dataset.createTitle || modal.dataset.createTitle;
        if (method) method.disabled = true;
        setModeVisibility('create');
        applyValues(JSON.parse(button.dataset.defaults || '{}'));
        showModal();
    };

    const openEdit = (button) => {
        clearFieldErrors();
        form.reset();
        form.action = button.dataset.updateUrl;
        title.textContent = button.dataset.editTitle || 'Edit Record';
        if (method) {
            method.disabled = false;
            method.value = 'PUT';
        }
        setModeVisibility('edit');
        applyValues(JSON.parse(button.dataset.values || '{}'));
        showModal();
    };

    document.querySelectorAll(`[data-setup-target="${modal.id}"]`).forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.setupOpen === 'edit') {
                openEdit(button);
            } else {
                openCreate(button);
            }
        });
    });

    modal.querySelectorAll('[data-setup-close]').forEach((button) => {
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
    }
});
