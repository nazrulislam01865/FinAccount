const safeDeleteModal = document.querySelector('[data-safe-delete-modal]');

if (safeDeleteModal) {
    const entity = safeDeleteModal.querySelector('[data-safe-delete-entity]');
    const dependenciesWrap = safeDeleteModal.querySelector('[data-safe-delete-dependencies-wrap]');
    const dependencies = safeDeleteModal.querySelector('[data-safe-delete-dependencies]');
    const confirmation = safeDeleteModal.querySelector('[data-safe-delete-confirmation]');
    const errorBox = safeDeleteModal.querySelector('[data-safe-delete-error]');
    const confirmButton = safeDeleteModal.querySelector('[data-safe-delete-confirm]');
    let activeForm = null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const close = () => {
        safeDeleteModal.classList.remove('show');
        safeDeleteModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hg-modal-open');
        activeForm = null;
        errorBox.hidden = true;
        errorBox.textContent = '';
        confirmButton.disabled = false;
        confirmButton.textContent = 'Yes, delete permanently';
    };

    const show = () => {
        safeDeleteModal.classList.add('show');
        safeDeleteModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hg-modal-open');
        window.setTimeout(() => confirmButton.focus(), 0);
    };

    const submitRequest = async (form, extra = {}) => {
        const body = new FormData(form);
        Object.entries(extra).forEach(([key, value]) => body.set(key, value));

        const response = await fetch(form.action, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json().catch(() => ({
            success: false,
            message: 'The server returned an invalid response.',
        }));

        if (!response.ok) {
            throw new Error(payload.message || payload.errors?.master_data?.[0] || 'Deletion could not be completed.');
        }

        return payload;
    };

    const renderPlan = (plan) => {
        entity.textContent = plan.entity_label || 'Selected record';
        confirmation.textContent = plan.confirmation_text || '';
        dependencies.innerHTML = '';

        if (plan.dependencies?.length) {
            dependenciesWrap.hidden = false;
            plan.dependencies.forEach((dependency) => {
                const item = document.createElement('div');
                item.className = 'hg-safe-delete-item';
                item.innerHTML = `
                    <div class="hg-safe-delete-item-head">
                        <strong>${escapeHtml(dependency.label)}</strong>
                        <span class="hg-badge off">${Number(dependency.count).toLocaleString()}</span>
                    </div>
                    <p>${escapeHtml(dependency.effect)}</p>
                `;
                dependencies.appendChild(item);
            });
        } else {
            dependenciesWrap.hidden = true;
        }
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
    }[character]));

    document.querySelectorAll('[data-safe-delete-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            activeForm = form;
            errorBox.hidden = true;

            try {
                const payload = await submitRequest(form, { preview: '1', confirmed: '0' });
                renderPlan(payload.plan);
                show();
            } catch (error) {
                window.alert(error.message);
                activeForm = null;
            }
        });
    });

    confirmButton.addEventListener('click', async () => {
        if (!activeForm) return;

        confirmButton.disabled = true;
        confirmButton.textContent = 'Deleting...';
        errorBox.hidden = true;

        try {
            const payload = await submitRequest(activeForm, { preview: '0', confirmed: '1' });
            window.location.assign(payload.redirect_url || window.location.href);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
            confirmButton.disabled = false;
            confirmButton.textContent = 'Yes, delete permanently';
        }
    });

    safeDeleteModal.querySelectorAll('[data-safe-delete-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    safeDeleteModal.addEventListener('click', (event) => {
        if (event.target === safeDeleteModal) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && safeDeleteModal.classList.contains('show')) close();
    });
}
