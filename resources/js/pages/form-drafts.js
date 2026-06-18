const config = window.HISEBGHOR_FORM_DRAFTS;

if (config?.enabled) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const managers = new WeakMap();
    const excludedNames = new Set([
        '_token',
        '_method',
        '_draft_key',
        'request_token',
        'record_id',
        'setup_modal',
        'form_mode',
    ]);

    const urlFor = (template, key) => template.replace('__DRAFT_KEY__', encodeURIComponent(key));

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });

        const body = response.status === 204 ? {} : await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = body?.message
                || Object.values(body?.errors || {})?.flat()?.[0]
                || 'The draft request could not be completed.';
            throw new Error(message);
        }

        return body;
    };

    const fieldGroups = (form) => {
        const groups = new Map();
        [...form.elements].forEach((field) => {
            if (!(field instanceof HTMLElement) || !field.name || excludedNames.has(field.name)) return;
            if (field.disabled || field.matches('[data-draft-ignore]')) return;
            if (['submit', 'button', 'reset'].includes(field.type)) return;

            if (!groups.has(field.name)) groups.set(field.name, []);
            groups.get(field.name).push(field);
        });
        return groups;
    };

    const serialize = (form) => {
        const fields = {};
        let omittedFiles = false;
        let omittedSensitive = false;

        fieldGroups(form).forEach((elements, name) => {
            const eligible = elements.filter((field) => {
                if (field.type === 'file') {
                    if (field.files?.length) omittedFiles = true;
                    return false;
                }
                if (field.type === 'password') {
                    if (field.value) omittedSensitive = true;
                    return false;
                }
                return true;
            });

            if (!eligible.length) return;

            const radios = eligible.filter((field) => field.type === 'radio');
            if (radios.length) {
                fields[name] = radios.find((field) => field.checked)?.value ?? null;
                return;
            }

            const checkboxes = eligible.filter((field) => field.type === 'checkbox');
            if (checkboxes.length) {
                const actualCheckboxes = checkboxes;
                fields[name] = actualCheckboxes.length === 1 && !name.endsWith('[]')
                    ? actualCheckboxes[0].checked
                    : actualCheckboxes.filter((field) => field.checked).map((field) => field.value);
                return;
            }

            const field = eligible.find((item) => item.type !== 'hidden') || eligible[eligible.length - 1];
            if (field instanceof HTMLSelectElement && field.multiple) {
                fields[name] = [...field.selectedOptions].map((option) => option.value);
            } else {
                fields[name] = field.value;
            }
        });

        return {
            fields,
            omitted_files: omittedFiles,
            omitted_sensitive: omittedSensitive,
        };
    };

    const restore = (form, payload) => {
        const groups = fieldGroups(form);
        Object.entries(payload?.fields || {}).forEach(([name, value]) => {
            const elements = groups.get(name) || [];
            if (!elements.length) return;

            const radios = elements.filter((field) => field.type === 'radio');
            if (radios.length) {
                radios.forEach((field) => { field.checked = String(field.value) === String(value ?? ''); });
                return;
            }

            const checkboxes = elements.filter((field) => field.type === 'checkbox');
            if (checkboxes.length) {
                if (checkboxes.length === 1 && !name.endsWith('[]')) {
                    checkboxes[0].checked = Boolean(value);
                } else {
                    const selected = new Set((Array.isArray(value) ? value : []).map(String));
                    checkboxes.forEach((field) => { field.checked = selected.has(String(field.value)); });
                }
                return;
            }

            const field = elements.find((item) => item.type !== 'hidden') || elements[elements.length - 1];
            if (field instanceof HTMLSelectElement && field.multiple) {
                const selected = new Set((Array.isArray(value) ? value : []).map(String));
                [...field.options].forEach((option) => { option.selected = selected.has(String(option.value)); });
            } else {
                field.value = value ?? '';
            }
        });

        groups.forEach((elements) => {
            elements.forEach((field) => {
                if (field.type === 'hidden' || field.type === 'file' || field.type === 'password') return;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    };

    class DraftManager {
        constructor(form) {
            this.form = form;
            this.key = form.dataset.draftKey || '';
            this.title = form.dataset.draftTitle || document.title;
            this.saveButton = form.querySelector('[data-draft-save]');
            this.discardButton = form.querySelector('[data-draft-discard]');
            this.clearButton = form.querySelector('[data-draft-clear]');
            this.feedback = form.querySelector('[data-draft-feedback]');
            this.message = form.querySelector('[data-draft-message]');
            this.requestVersion = 0;
            this.ensureHiddenKey();
            this.bind();

            if (!form.hasAttribute('data-draft-defer') && this.key) {
                this.load();
            }
        }

        ensureHiddenKey() {
            let input = this.form.querySelector('input[name="_draft_key"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_draft_key';
                this.form.appendChild(input);
            }
            input.value = this.key;
        }

        bind() {
            this.saveButton?.addEventListener('click', () => this.save());
            this.discardButton?.addEventListener('click', () => this.discard());
            this.clearButton?.addEventListener('click', () => this.clearAndNavigate());
            this.form.addEventListener('hisebghor:draft-context', (event) => {
                this.setContext(event.detail?.key || '', event.detail?.title || this.title);
            });
        }

        setContext(key, title) {
            this.key = key;
            this.title = title || this.title;
            this.ensureHiddenKey();
            this.hideFeedback();
            if (key) this.load();
        }

        async load() {
            const key = this.key;
            const version = ++this.requestVersion;
            if (!key) return;

            try {
                const body = await request(urlFor(config.showUrlTemplate, key), { method: 'GET' });
                if (version !== this.requestVersion || key !== this.key) return;

                if (!body.exists) {
                    this.hideFeedback();
                    return;
                }

                restore(this.form, body.draft.payload);
                const notes = [];
                if (body.draft.payload?.omitted_files) notes.push('Choose file fields again.');
                if (body.draft.payload?.omitted_sensitive) notes.push('Re-enter password fields.');
                this.showFeedback(`Draft restored (${body.draft.updated_at_label || 'previously saved'}). ${notes.join(' ')}`, true);
            } catch (error) {
                this.showFeedback(error.message, false, true);
            }
        }

        async save() {
            if (!this.key || !this.saveButton) return;
            const execution = window.HisebGhorExecution;
            if (execution && !execution.begin(this.saveButton)) return;

            try {
                const body = await request(urlFor(config.storeUrlTemplate, this.key), {
                    method: 'PUT',
                    body: JSON.stringify({
                        title: this.title,
                        payload: serialize(this.form),
                    }),
                });

                const payload = serialize(this.form);
                const notes = [];
                if (payload.omitted_files) notes.push('File selections are not stored.');
                if (payload.omitted_sensitive) notes.push('Passwords are not stored.');
                this.showFeedback(`Draft saved ${body.draft?.updated_at_label || 'just now'}. It will appear in the module list as Draft after refresh and cannot be used until finally saved. ${notes.join(' ')}`, true);
            } catch (error) {
                this.showFeedback(error.message, false, true);
            } finally {
                execution?.end();
            }
        }

        async discard() {
            if (!this.key || !this.discardButton) return;
            const execution = window.HisebGhorExecution;
            if (execution && !execution.begin(this.discardButton)) return;

            try {
                await request(urlFor(config.destroyUrlTemplate, this.key), { method: 'DELETE' });
                this.showFeedback('Draft discarded. The current form values remain until you close or clear the form.', false);
            } catch (error) {
                this.showFeedback(error.message, true, true);
            } finally {
                execution?.end();
            }
        }

        async clearAndNavigate() {
            if (!this.clearButton) return;
            const target = this.clearButton.dataset.draftClearUrl || window.location.href;
            const execution = window.HisebGhorExecution;
            if (execution && !execution.begin(this.clearButton)) return;

            try {
                if (this.key) {
                    await request(urlFor(config.destroyUrlTemplate, this.key), { method: 'DELETE' });
                }
                window.location.assign(target);
            } catch (error) {
                this.showFeedback(error.message, true, true);
                execution?.end();
            }
        }

        showFeedback(text, canDiscard = false, isError = false) {
            if (!this.feedback || !this.message) return;
            this.message.textContent = text;
            this.feedback.hidden = false;
            this.feedback.classList.toggle('is-error', isError);
            if (this.discardButton) this.discardButton.hidden = !canDiscard;
        }

        hideFeedback() {
            if (!this.feedback) return;
            this.feedback.hidden = true;
            this.feedback.classList.remove('is-error');
            if (this.discardButton) this.discardButton.hidden = true;
        }
    }



    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-draft-open-existing]');
        if (!button) return;

        const key = button.dataset.draftOpenExisting;
        if (!key) return;

        const target = document.querySelector(`[data-draft-edit-key="${CSS.escape(key)}"]`);
        if (target) {
            target.click();
        } else {
            window.alert('The original record for this edit draft is no longer available. Discard the draft or recreate the record.');
        }
    });

    document.querySelectorAll('form[data-draft-form]').forEach((form) => {
        const manager = new DraftManager(form);
        managers.set(form, manager);
    });

    window.HisebGhorDrafts = {
        setContext(form, key, title) {
            managers.get(form)?.setContext(key, title);
        },
    };
}
