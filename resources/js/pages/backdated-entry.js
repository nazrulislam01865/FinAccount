const dateOnly = /^\d{4}-\d{2}-\d{2}$/;

const parseRanges = (input) => {
    try {
        const parsed = JSON.parse(input.dataset.openPeriodRanges || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const earlierDate = (first, second) => {
    if (!first) return second || '';
    if (!second) return first;
    return first < second ? first : second;
};

const dateInsideRanges = (value, ranges) => {
    if (!dateOnly.test(value)) return true;
    if (ranges.length === 0) return false;

    return ranges.some((range) => value >= String(range.start || '') && value <= String(range.end || ''));
};

const latestAllowedBackdatedDate = (ranges, today) => ranges
    .map((range) => {
        const start = String(range.start || '');
        const end = earlierDate(String(range.end || ''), today);

        return start && end && start <= end ? end : '';
    })
    .filter(Boolean)
    .sort()
    .at(-1) || '';

document.querySelectorAll('[data-backdated-entry]').forEach((container) => {
    const input = container.querySelector('[data-backdated-date]');
    const toggle = container.querySelector('[data-backdated-toggle]');
    const help = container.querySelector('[data-backdated-help]');

    if (!input || !toggle) return;

    const today = input.dataset.today || '';
    const normalDate = input.dataset.normalDate || today || input.value;
    const openMin = input.dataset.openPeriodMin || '';
    const openMax = input.dataset.openPeriodMax || '';
    const backdatedMax = input.dataset.backdatedMax || earlierDate(openMax, today);
    const ranges = parseRanges(input);
    const latestBackdatedDate = latestAllowedBackdatedDate(ranges, today);
    const baseHelp = help?.textContent?.trim() || '';

    const validateDate = () => {
        const value = input.value;

        if (!value || !dateOnly.test(value)) {
            input.setCustomValidity('');
            return;
        }

        if (!toggle.checked && normalDate && value !== normalDate) {
            input.setCustomValidity('Enable Backdated Entry before changing the transaction date.');
            return;
        }

        if (toggle.checked && today && value > today) {
            input.setCustomValidity('A backdated transaction date cannot be later than today.');
            return;
        }

        if (!dateInsideRanges(value, ranges)) {
            input.setCustomValidity('Select a date inside an active open financial year and after its lock date.');
            return;
        }

        input.setCustomValidity('');
    };

    const setDateLocked = (locked) => {
        input.readOnly = locked;
        input.setAttribute('aria-readonly', locked ? 'true' : 'false');
        container.classList.toggle('is-date-locked', locked);
        container.classList.toggle('is-backdated', !locked);
    };

    const applyMode = (resetDate = false, openPicker = false) => {
        const backdatedMode = toggle.checked;

        setDateLocked(!backdatedMode);

        if (backdatedMode) {
            input.min = openMin;
            input.max = backdatedMax;

            if (resetDate && (!dateInsideRanges(input.value, ranges) || (today && input.value > today))) {
                input.value = latestBackdatedDate || normalDate || input.value;
            }
        } else {
            input.min = normalDate;
            input.max = normalDate;

            if (resetDate) {
                input.value = normalDate;
            }
        }

        if (help) {
            help.textContent = backdatedMode
                ? `Backdated mode enabled. You can now change the complete transaction date. ${baseHelp}`
                : `Date is locked. Enable Backdated Entry to change it. ${baseHelp}`;
        }

        validateDate();

        if (openPicker && backdatedMode) {
            if (typeof input.showPicker === 'function') {
                try {
                    input.showPicker();
                } catch {
                    input.focus();
                }
            } else {
                input.focus();
            }
        }
    };

    toggle.addEventListener('change', () => applyMode(true, toggle.checked));
    input.addEventListener('change', validateDate);
    input.addEventListener('input', validateDate);

    input.addEventListener('keydown', (event) => {
        if (!toggle.checked && event.key !== 'Tab' && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
        }
    });

    input.addEventListener('pointerdown', (event) => {
        if (!toggle.checked) {
            event.preventDefault();
            toggle.focus();
        }
    });

    applyMode(false, false);
});
