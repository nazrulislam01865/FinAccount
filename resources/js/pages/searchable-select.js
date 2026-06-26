const ENHANCED_ATTRIBUTE = 'data-hg-searchable-enhanced';
const SEARCHABLE_SELECTOR = 'select[data-hg-searchable]';
const MOBILE_BREAKPOINT = 760;

let activeInstance = null;
let instanceCounter = 0;
let globalBackdrop = null;

const normalize = (value) => String(value == null ? '' : value).trim();
const searchableText = (value) => normalize(value).toLocaleLowerCase();
const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const cssEscape = (value) => {
    if (window.CSS?.escape) return CSS.escape(value);
    return String(value).replace(/([ #;?%&,.+*~\':"!^$[\]()=>|/@])/g, '\\$1');
};

function ensureBackdrop() {
    if (globalBackdrop && document.body.contains(globalBackdrop)) return globalBackdrop;

    globalBackdrop = document.createElement('button');
    globalBackdrop.type = 'button';
    globalBackdrop.className = 'hg-searchable-backdrop';
    globalBackdrop.setAttribute('aria-label', 'Close searchable dropdown');
    globalBackdrop.addEventListener('click', () => activeInstance?.close({ returnFocus: true }));
    document.body.appendChild(globalBackdrop);

    return globalBackdrop;
}

function fieldLabel(select) {
    const explicitLabel = select.id
        ? document.querySelector(`label[for="${cssEscape(select.id)}"]`)
        : null;
    const nearbyLabel = select.closest('.hg-field')?.querySelector('label');
    const raw = select.dataset.hgSearchableLabel
        || explicitLabel?.textContent
        || nearbyLabel?.textContent
        || select.getAttribute('aria-label')
        || 'Select from list';

    return normalize(raw.replace(/\*/g, '')) || 'Select from list';
}

function optionData(option) {
    const text = normalize(option.textContent);

    return {
        value: normalize(option.value),
        title: normalize(option.dataset.title) || text,
        meta: normalize(option.dataset.meta),
        status: normalize(option.dataset.status),
        keywords: normalize(option.dataset.searchKeywords),
        text,
        disabled: option.disabled,
        hidden: option.hidden,
    };
}

class HisebGhorSearchableSelect {
    constructor(select) {
        this.select = select;
        this.uid = `hg-searchable-${++instanceCounter}`;
        this.isOpen = false;
        this.activeOptionIndex = -1;
        this.renderQueued = false;
        this.hasValidationError = false;

        this.build();
        this.bind();
        this.observe();
        this.refresh();
    }

    build() {
        const parent = this.select.parentNode;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'hg-searchable-select';
        this.wrapper.dataset.hgSearchableFor = this.select.id || this.uid;

        parent.insertBefore(this.wrapper, this.select);
        this.wrapper.appendChild(this.select);

        this.select.setAttribute(ENHANCED_ATTRIBUTE, 'true');
        this.select.classList.add('hg-searchable-native');
        this.select.tabIndex = -1;

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'hg-searchable-trigger';
        this.trigger.setAttribute('role', 'combobox');
        this.trigger.setAttribute('aria-autocomplete', 'none');
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.setAttribute('aria-controls', `${this.uid}-listbox`);
        this.trigger.innerHTML = `
            <span class="hg-searchable-trigger-copy">
                <span class="hg-searchable-trigger-title"></span>
                <span class="hg-searchable-trigger-meta" hidden></span>
            </span>
        `;

        this.clearButton = document.createElement('button');
        this.clearButton.type = 'button';
        this.clearButton.className = 'hg-searchable-clear';
        this.clearButton.innerHTML = '<span aria-hidden="true">×</span>';

        this.arrow = document.createElement('span');
        this.arrow.className = 'hg-searchable-arrow';
        this.arrow.setAttribute('aria-hidden', 'true');
        this.arrow.textContent = '⌄';

        this.panel = document.createElement('div');
        this.panel.className = 'hg-searchable-panel';
        this.panel.setAttribute('aria-hidden', 'true');
        this.panel.innerHTML = `
            <div class="hg-searchable-mobile-handle" aria-hidden="true"></div>
            <div class="hg-searchable-panel-head">
                <strong class="hg-searchable-panel-title"></strong>
                <button type="button" class="hg-searchable-close" aria-label="Close dropdown">×</button>
            </div>
            <div class="hg-searchable-search-box">
                <span class="hg-searchable-search-icon" aria-hidden="true">⌕</span>
                <input type="search" class="hg-searchable-search-input" autocomplete="off" spellcheck="false">
            </div>
            <div class="hg-searchable-option-list" id="${this.uid}-listbox" role="listbox"></div>
            <div class="hg-searchable-empty" role="status">No matching result found</div>
        `;

        this.wrapper.appendChild(this.trigger);
        this.wrapper.appendChild(this.clearButton);
        this.wrapper.appendChild(this.arrow);
        this.wrapper.appendChild(this.panel);

        this.triggerTitle = this.trigger.querySelector('.hg-searchable-trigger-title');
        this.triggerMeta = this.trigger.querySelector('.hg-searchable-trigger-meta');
        this.panelTitle = this.panel.querySelector('.hg-searchable-panel-title');
        this.closeButton = this.panel.querySelector('.hg-searchable-close');
        this.searchInput = this.panel.querySelector('.hg-searchable-search-input');
        this.optionList = this.panel.querySelector('.hg-searchable-option-list');
        this.emptyState = this.panel.querySelector('.hg-searchable-empty');
    }

    bind() {
        this.trigger.addEventListener('click', () => {
            if (!this.select.disabled) this.toggle();
        });

        this.trigger.addEventListener('keydown', (event) => {
            if (this.select.disabled) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                this.close({ returnFocus: false });
                return;
            }

            if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                this.open();
                return;
            }

            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                this.open({ initialQuery: event.key });
            }
        });

        this.searchInput.addEventListener('input', () => this.renderOptions());
        this.searchInput.addEventListener('keydown', (event) => this.handleSearchKeydown(event));

        this.clearButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (!this.select.disabled) this.selectValue('');
        });

        this.closeButton.addEventListener('click', () => this.close({ returnFocus: true }));

        this.optionList.addEventListener('click', (event) => {
            const option = event.target.closest('[data-hg-searchable-value]');
            if (!option || option.disabled) return;
            this.selectValue(option.dataset.hgSearchableValue || '');
        });

        this.select.addEventListener('input', () => this.refreshSelectionState());
        this.select.addEventListener('change', () => {
            if (this.select.validity.valid) this.hasValidationError = false;
            this.refresh();
        });
        this.select.addEventListener('invalid', (event) => {
            event.preventDefault();
            this.hasValidationError = true;
            this.trigger.setAttribute('aria-invalid', 'true');
            this.trigger.focus({ preventScroll: false });
        });

        document.addEventListener('pointerdown', (event) => {
            if (!this.isOpen || this.wrapper.contains(event.target)) return;
            if (globalBackdrop?.contains(event.target)) return;
            this.close({ returnFocus: false });
        });

        this.select.form?.addEventListener('reset', () => window.setTimeout(() => this.refresh(), 0));
    }

    observe() {
        this.selectObserver = new MutationObserver(() => this.queueRefresh());
        this.selectObserver.observe(this.select, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [
                'disabled', 'hidden', 'label', 'required', 'selected', 'value',
                'aria-invalid', 'data-title', 'data-meta', 'data-status', 'data-search-keywords',
            ],
        });
    }

    queueRefresh() {
        if (this.renderQueued) return;
        this.renderQueued = true;
        window.requestAnimationFrame(() => {
            this.renderQueued = false;
            this.refresh();
        });
    }

    updatePanelCopy() {
        const label = fieldLabel(this.select);
        this.panelTitle.textContent = label;
        this.searchInput.placeholder = this.select.dataset.hgSearchPlaceholder
            || `Search ${label.toLocaleLowerCase()}...`;
        this.emptyState.textContent = this.select.dataset.hgSearchEmpty || 'No matching result found';
        this.clearButton.setAttribute('aria-label', `Clear ${label}`);
        this.trigger.setAttribute('aria-label', label);
    }

    availableOptions() {
        return Array.from(this.select.options || [])
            .map(optionData)
            .filter((item) => item.value && !item.hidden && !item.disabled);
    }

    emptyOption() {
        const option = Array.from(this.select.options || []).find((item) => item.value === '');
        return option ? optionData(option) : null;
    }

    selectedOption() {
        const option = this.select.selectedOptions?.[0];
        return option && option.value ? optionData(option) : null;
    }

    refresh() {
        this.updatePanelCopy();
        this.wrapper.classList.toggle('is-disabled', this.select.disabled);
        this.trigger.disabled = this.select.disabled;
        this.refreshSelectionState();
        if (this.isOpen) this.renderOptions();
    }

    refreshSelectionState() {
        const selected = this.selectedOption();
        const empty = this.emptyOption();
        const placeholder = normalize(this.select.dataset.hgSearchPlaceholderText)
            || empty?.text
            || 'Select an option';

        this.wrapper.classList.toggle('has-value', Boolean(selected));
        this.triggerTitle.textContent = selected?.title || placeholder;
        this.triggerTitle.classList.toggle('is-placeholder', !selected);

        if (selected?.meta) {
            this.triggerMeta.textContent = selected.meta;
            this.triggerMeta.hidden = false;
        } else {
            this.triggerMeta.textContent = '';
            this.triggerMeta.hidden = true;
        }

        this.clearButton.hidden = !selected || this.select.required || this.select.disabled;
        this.trigger.setAttribute('aria-expanded', this.isOpen ? 'true' : 'false');
        this.trigger.toggleAttribute('aria-required', this.select.required);
        const nativeInvalid = this.select.getAttribute('aria-invalid') === 'true';
        this.trigger.toggleAttribute('aria-invalid', this.hasValidationError || nativeInvalid);
    }

    toggle() {
        this.isOpen ? this.close({ returnFocus: false }) : this.open();
    }

    open({ initialQuery = '' } = {}) {
        if (this.select.disabled) return;
        if (activeInstance && activeInstance !== this) activeInstance.close({ returnFocus: false });

        activeInstance = this;
        this.isOpen = true;
        this.refresh();
        this.wrapper.classList.add('open');
        this.trigger.classList.add('active');
        this.trigger.setAttribute('aria-expanded', 'true');
        this.panel.setAttribute('aria-hidden', 'false');
        this.searchInput.value = initialQuery;
        this.activeOptionIndex = -1;
        this.renderOptions();
        this.setDesktopDirection();

        ensureBackdrop().classList.add('show');
        document.body.classList.add('hg-searchable-open');

        window.setTimeout(() => {
            this.searchInput.focus({ preventScroll: true });
            if (initialQuery) this.searchInput.setSelectionRange(initialQuery.length, initialQuery.length);
        }, 40);
    }

    close({ returnFocus = false } = {}) {
        if (!this.isOpen) return;

        this.isOpen = false;
        this.wrapper.classList.remove('open', 'drop-up');
        this.trigger.classList.remove('active');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.panel.setAttribute('aria-hidden', 'true');
        this.searchInput.value = '';
        this.activeOptionIndex = -1;

        if (activeInstance === this) activeInstance = null;
        globalBackdrop?.classList.remove('show');
        document.body.classList.remove('hg-searchable-open');
        this.refreshSelectionState();

        if (returnFocus && !this.select.disabled) {
            window.setTimeout(() => this.trigger.focus({ preventScroll: true }), 0);
        }
    }

    setDesktopDirection() {
        this.wrapper.classList.remove('drop-up');
        if (window.innerWidth <= MOBILE_BREAKPOINT) return;

        const rect = this.trigger.getBoundingClientRect();
        const roomBelow = window.innerHeight - rect.bottom;
        const roomAbove = rect.top;
        if (roomBelow < 340 && roomAbove > roomBelow) this.wrapper.classList.add('drop-up');
    }

    renderOptions() {
        const query = searchableText(this.searchInput.value);
        const selectedValue = normalize(this.select.value);
        const matching = this.availableOptions().filter((item) => {
            if (!query) return true;
            return [item.title, item.meta, item.status, item.keywords, item.text, item.value]
                .some((part) => searchableText(part).includes(query));
        });

        const rows = [];
        const empty = this.emptyOption();
        if (!this.select.required && !query && empty) {
            rows.push(this.optionMarkup({
                value: '',
                title: empty.text || 'Clear selection',
                meta: 'No selection',
                status: '',
            }, selectedValue === ''));
        }
        matching.forEach((item) => rows.push(this.optionMarkup(item, item.value === selectedValue)));

        this.optionList.innerHTML = rows.join('');
        const visibleOptions = this.optionButtons();
        this.emptyState.style.display = visibleOptions.length ? 'none' : 'block';
        const selectedIndex = visibleOptions.findIndex((button) => button.getAttribute('aria-selected') === 'true');
        this.activeOptionIndex = selectedIndex >= 0 ? selectedIndex : (visibleOptions.length ? 0 : -1);
        this.updateActiveOption();
    }

    optionMarkup(item, selected) {
        const badge = item.status
            ? `<span class="hg-searchable-status">${escapeHtml(item.status)}</span>`
            : (selected ? '<span class="hg-searchable-selected-mark" aria-label="Selected">✓</span>' : '');

        return `
            <button type="button"
                class="hg-searchable-option${selected ? ' selected' : ''}"
                role="option"
                aria-selected="${selected ? 'true' : 'false'}"
                data-hg-searchable-value="${escapeHtml(item.value)}">
                <span class="hg-searchable-option-copy">
                    <span class="hg-searchable-option-title">${escapeHtml(item.title)}</span>
                    ${item.meta ? `<span class="hg-searchable-option-meta">${escapeHtml(item.meta)}</span>` : ''}
                </span>
                ${badge}
            </button>
        `;
    }

    optionButtons() {
        return Array.from(this.optionList.querySelectorAll('.hg-searchable-option:not(:disabled)'));
    }

    handleSearchKeydown(event) {
        const buttons = this.optionButtons();

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close({ returnFocus: true });
            return;
        }
        if (!buttons.length) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.activeOptionIndex = (this.activeOptionIndex + 1) % buttons.length;
            this.updateActiveOption();
            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.activeOptionIndex = (this.activeOptionIndex - 1 + buttons.length) % buttons.length;
            this.updateActiveOption();
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            buttons[Math.max(0, this.activeOptionIndex)]?.click();
        }
    }

    updateActiveOption() {
        this.optionButtons().forEach((button, index) => {
            const active = index === this.activeOptionIndex;
            button.classList.toggle('active', active);
            button.tabIndex = active ? 0 : -1;
            if (active) button.scrollIntoView({ block: 'nearest' });
        });
    }

    selectValue(value) {
        const nextValue = normalize(value);
        if (!Array.from(this.select.options).some((option) => normalize(option.value) === nextValue)) return;

        this.select.value = nextValue;
        this.select.dispatchEvent(new Event('input', { bubbles: true }));
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.refresh();
        this.close({ returnFocus: true });
    }
}

function enhance(select) {
    if (!(select instanceof HTMLSelectElement)) return null;
    if (!select.matches(SEARCHABLE_SELECTOR)) return null;
    if (select.hasAttribute(ENHANCED_ATTRIBUTE)) return select.__hisebGhorSearchableSelect || null;

    const instance = new HisebGhorSearchableSelect(select);
    select.__hisebGhorSearchableSelect = instance;
    return instance;
}

function enhanceWithin(root = document) {
    const selects = [];
    if (root instanceof HTMLSelectElement && root.matches(SEARCHABLE_SELECTOR)) selects.push(root);
    root.querySelectorAll?.(SEARCHABLE_SELECTOR).forEach((select) => selects.push(select));
    selects.forEach(enhance);
}

function initialize() {
    enhanceWithin(document);
    if (document.querySelector(SEARCHABLE_SELECTOR)) ensureBackdrop();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) enhanceWithin(node);
            });
            if (mutation.type === 'attributes' && mutation.target instanceof HTMLSelectElement) {
                enhance(mutation.target);
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-hg-searchable'],
    });

    window.addEventListener('resize', () => activeInstance?.setDesktopDirection(), { passive: true });
    window.addEventListener('orientationchange', () => activeInstance?.setDesktopDirection(), { passive: true });
}

window.HisebGhorSearchableSelect = {
    enhance,
    refresh(target) {
        const select = typeof target === 'string' ? document.querySelector(target) : target;
        select?.__hisebGhorSearchableSelect?.refresh();
    },
    refreshAll() {
        document.querySelectorAll(`[${ENHANCED_ATTRIBUTE}]`)
            .forEach((select) => select.__hisebGhorSearchableSelect?.refresh());
    },
    closeAll() {
        activeInstance?.close({ returnFocus: false });
    },
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
} else {
    initialize();
}
