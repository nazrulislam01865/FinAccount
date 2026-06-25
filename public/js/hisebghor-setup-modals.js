(function () {
    'use strict';

    const initSetupModal = (modal) => {
        if (!(modal instanceof HTMLElement) || modal.dataset.setupModalBound === '1') {
            return;
        }

        const form = modal.querySelector('[data-setup-form]');
        const title = modal.querySelector('[data-setup-title]');
        const method = form?.querySelector('[data-setup-method]');

        if (!form || !title) {
            return;
        }

        modal.dataset.setupModalBound = '1';

        const parseJson = (value) => {
            try {
                return JSON.parse(value || '{}');
            } catch (_) {
                return {};
            }
        };

        const applyValues = (values = {}) => {
            Object.entries(values).forEach(([name, value]) => {
                const field = form.elements.namedItem(name);
                if (!field) return;

                if (field instanceof RadioNodeList) {
                    const selectedValues = Array.isArray(value) ? value.map(String) : [String(value)];
                    Array.from(field).forEach((item) => {
                        item.checked = selectedValues.includes(String(item.value));
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

        const setDraftContext = (mode, values = {}) => {
            const base = form.dataset.draftKeyBase;
            if (!base) return;

            const recordId = values.record_id || form.elements.namedItem('record_id')?.value || '';
            const key = mode === 'edit' && recordId
                ? `${base}.edit.${recordId}`
                : `${base}.create`;
            const modeTitle = mode === 'edit'
                ? `Edit ${form.dataset.draftTitle || 'Record'}`
                : `Add ${form.dataset.draftTitle || 'Record'}`;

            form.dataset.draftKey = key;
            form.dispatchEvent(new CustomEvent('hisebghor:draft-context', {
                detail: { key, title: modeTitle },
            }));
        };

        const showModal = () => {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('hg-modal-open');
            window.setTimeout(() => {
                form.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus();
            }, 0);
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

            if (method) {
                method.disabled = true;
            }

            setModeVisibility('create');
            const values = parseJson(button.dataset.defaults);
            applyValues(values);
            showModal();
            setDraftContext('create', values);

            form.dispatchEvent(new CustomEvent('hisebghor:setup-opened', {
                bubbles: true,
                detail: { mode: 'create', values },
            }));
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
            const values = parseJson(button.dataset.values);
            applyValues(values);
            showModal();
            setDraftContext('edit', values);

            form.dispatchEvent(new CustomEvent('hisebghor:setup-opened', {
                bubbles: true,
                detail: { mode: 'edit', values },
            }));
        };

        document.querySelectorAll(`[data-setup-target="${CSS.escape(modal.id)}"]`).forEach((button) => {
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
    };

    const initAllSetupModals = () => {
        document.querySelectorAll('[data-setup-modal]').forEach(initSetupModal);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllSetupModals, { once: true });
    } else {
        initAllSetupModals();
    }

    window.HisebGhorSetupModals = {
        init: initAllSetupModals,
    };
})();
