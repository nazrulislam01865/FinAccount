document.querySelectorAll('[data-setup-modal]').forEach((modal) => {
    const form = modal.querySelector('[data-setup-form]');
    const title = modal.querySelector('[data-setup-title]');
    const method = form?.querySelector('[data-setup-method]');

    if (!form || !title) return;

    const matchingFields = (name) => Array.from(form.elements).filter((field) =>
        field.name === name || field.name === `${name}[]`,
    );

    const applyValues = (values = {}) => {
        Object.entries(values).forEach(([name, value]) => {
            const fields = matchingFields(name);
            if (fields.length === 0) return;

            const selectedValues = (Array.isArray(value) ? value : [value])
                .filter((item) => item !== null && item !== undefined)
                .map(String);

            fields.forEach((field) => {
                if (field.type === 'radio') {
                    field.checked = selectedValues.includes(String(field.value));
                    return;
                }

                if (field.type === 'checkbox') {
                    const isArrayField = field.name.endsWith('[]') || fields.length > 1 || Array.isArray(value);
                    field.checked = isArrayField
                        ? selectedValues.includes(String(field.value))
                        : value === true || value === 1 || value === '1';
                    return;
                }

                if (field instanceof HTMLSelectElement && field.multiple) {
                    Array.from(field.options).forEach((option) => {
                        option.selected = selectedValues.includes(String(option.value));
                    });
                    return;
                }

                field.value = value ?? '';
            });
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

    const setDraftContext = (mode, values = {}) => {
        const base = form.dataset.draftKeyBase;
        if (!base) return;

        const recordId = values.record_id || form.elements.namedItem('record_id')?.value || '';
        const key = mode === 'edit' && recordId
            ? `${base}.edit.${recordId}`
            : `${base}.create`;
        const modeTitle = mode === 'edit' ? `Edit ${form.dataset.draftTitle || 'Record'}` : `Add ${form.dataset.draftTitle || 'Record'}`;

        form.dataset.draftKey = key;
        form.dispatchEvent(new CustomEvent('hisebghor:draft-context', {
            detail: { key, title: modeTitle },
        }));
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
        form.dataset.setupMode = 'create';
        const values = JSON.parse(button.dataset.defaults || '{}');
        applyValues(values);
        form.dispatchEvent(new CustomEvent('hisebghor:setup-values-applied', { detail: { mode: 'create', values } }));
        showModal();
        setDraftContext('create', values);
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
        form.dataset.setupMode = 'edit';
        const values = JSON.parse(button.dataset.values || '{}');
        applyValues(values);
        form.dispatchEvent(new CustomEvent('hisebghor:setup-values-applied', { detail: { mode: 'edit', values } }));
        showModal();
        setDraftContext('edit', values);
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
        form.dataset.setupMode = form.elements.namedItem('record_id')?.value ? 'edit' : 'create';
        document.body.classList.add('hg-modal-open');
    }
});
