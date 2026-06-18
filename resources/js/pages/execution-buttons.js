const executionState = (() => {
    let activeButton = null;
    let lockedElements = [];

    const candidates = () => [
        ...document.querySelectorAll('button:not([data-execution-ignore]), input[type="submit"]:not([data-execution-ignore]), input[type="button"]:not([data-execution-ignore]), a.hg-btn:not([data-execution-ignore])'),
    ];

    const begin = (button) => {
        if (!button || activeButton) return false;

        activeButton = button;
        lockedElements = candidates().map((element) => ({
            element,
            wasDisabled: 'disabled' in element ? element.disabled : false,
            hadAriaDisabled: element.hasAttribute('aria-disabled'),
            previousTabIndex: element.getAttribute('tabindex'),
        }));

        button.classList.add('is-executing');
        button.setAttribute('aria-busy', 'true');

        lockedElements.forEach(({ element }) => {
            if ('disabled' in element) element.disabled = true;
            element.classList.add('is-execution-locked');
            element.setAttribute('aria-disabled', 'true');
            if (element instanceof HTMLAnchorElement) element.setAttribute('tabindex', '-1');
        });

        return true;
    };

    const end = () => {
        lockedElements.forEach(({ element, wasDisabled, hadAriaDisabled, previousTabIndex }) => {
            if ('disabled' in element) element.disabled = wasDisabled;
            element.classList.remove('is-execution-locked');
            if (!hadAriaDisabled) element.removeAttribute('aria-disabled');
            if (element instanceof HTMLAnchorElement) {
                if (previousTabIndex === null) element.removeAttribute('tabindex');
                else element.setAttribute('tabindex', previousTabIndex);
            }
        });

        if (activeButton) {
            activeButton.classList.remove('is-executing');
            activeButton.removeAttribute('aria-busy');
        }

        activeButton = null;
        lockedElements = [];
    };

    return { begin, end, isActive: () => activeButton !== null };
})();

window.HisebGhorExecution = executionState;

const submitters = new WeakMap();

document.addEventListener('click', (event) => {
    const button = event.target.closest('button, input[type="submit"]');
    if (!button || button.type !== 'submit' || !button.form) return;
    submitters.set(button.form, button);
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)
        || form.matches('[data-execution-ignore]')
        || form.hasAttribute('wire:submit')
        || event.defaultPrevented) return;

    const submitter = event.submitter || submitters.get(form) || form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitter) executionState.begin(submitter);
});

window.addEventListener('pageshow', () => executionState.end());
