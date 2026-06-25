const mobileCaptureDevice = typeof window !== 'undefined'
    && (
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent || '')
        || (navigator.maxTouchPoints > 0 && (
            window.matchMedia('(max-width: 1180px)').matches
            || window.matchMedia('(pointer: coarse)').matches
            || window.matchMedia('(hover: none)').matches
        ))
    );

document.documentElement.classList.toggle('hg-mobile-capture-device', mobileCaptureDevice);
document.body?.classList.toggle('hg-mobile-capture-device', mobileCaptureDevice);
window.HisebGhorMobileCaptureDevice = mobileCaptureDevice;

const page = document.querySelector('[data-transaction-entry]');

if (page) {
    const form = page.querySelector('[data-transaction-form]');
    const head = document.getElementById('transaction_head_id');
    const settlement = document.getElementById('settlement_type');
    const money = document.getElementById('money_account_id');
    const party = document.getElementById('party_id');
    const amount = document.getElementById('amount');
    const paidAmount = document.getElementById('paid_amount');
    const dueAmountPreview = document.getElementById('due_amount_preview');
    const moneyField = document.getElementById('money-field');
    const partyField = document.getElementById('party-field');
    const autoPartyNotice = document.getElementById('auto-party-notice');
    const autoPartyLabel = document.getElementById('auto-party-label');
    const autoPartyName = document.getElementById('auto-party-name');
    const paidAmountField = document.getElementById('paid-amount-field');
    const dueAmountField = document.getElementById('due-amount-field');
    const moneyLabel = document.getElementById('money-label');
    const partyLabel = document.getElementById('party-label');
    const paidAmountLabel = document.getElementById('paid-amount-label');
    const paidAmountHelp = document.getElementById('paid-amount-help');
    const preview = document.getElementById('journal-preview');
    const emptyPreviewTemplate = document.getElementById('journal-preview-empty-template');
    const previewUrl = preview?.dataset.previewUrl;
    let previewTimer;
    let autoSyncPaidAmount = form?.dataset.autoSyncPaid === '1';

    const parseJson = (value, fallback = []) => {
        try { return JSON.parse(value || '[]'); } catch (_) { return fallback; }
    };

    const amountScale = () => {
        const step = amount?.getAttribute('step') || '0.01';
        return step.includes('.') ? step.split('.')[1].length : 0;
    };

    const numericValue = (input) => {
        const value = Number.parseFloat(input?.value || '');
        return Number.isFinite(value) ? value : 0;
    };

    const selectedAllowedSettlements = () => {
        const fromHead = parseJson(head?.selectedOptions[0]?.dataset.allowedSettlements, []);
        return fromHead.length > 0
            ? fromHead
            : parseJson(form?.dataset.defaultAllowedSettlements, ['CASH']);
    };

    const usesReceivedWording = () => (moneyLabel?.textContent || '').toLowerCase().includes('receive');

    const updatePaidAmountCopy = () => {
        const received = usesReceivedWording();
        if (paidAmountLabel) paidAmountLabel.textContent = received ? 'Received Now' : 'Paid Now';
        if (paidAmountHelp) {
            paidAmountHelp.textContent = received
                ? 'Enter 0 when the full amount will remain due.'
                : 'Enter 0 when the full amount will remain unpaid.';
        }
    };

    const inferSettlement = () => {
        const allowed = selectedAllowedSettlements();
        const total = numericValue(amount);

        if (allowed.length === 1 && allowed[0] === 'CASH') {
            if (paidAmount) paidAmount.value = total > 0 ? total.toFixed(amountScale()) : '';
            if (settlement) settlement.value = 'CASH';
            return 'CASH';
        }

        if (allowed.length === 1 && allowed[0] === 'CREDIT') {
            if (paidAmount) paidAmount.value = '0';
            if (settlement) settlement.value = 'CREDIT';
            return 'CREDIT';
        }

        if (total <= 0) {
            if (settlement) settlement.value = 'CASH';
            return 'CASH';
        }

        if (autoSyncPaidAmount && paidAmount) {
            paidAmount.value = total.toFixed(amountScale());
        }

        const paid = numericValue(paidAmount);
        let type = 'CASH';
        if (paid <= 0) type = 'CREDIT';
        else if (total > 0 && paid < total) type = 'PARTIAL';

        if (settlement) settlement.value = type;
        return type;
    };

    const syncAmountFields = () => {
        const allowed = selectedAllowedSettlements();
        const type = inferSettlement();
        const total = numericValue(amount);
        const paid = numericValue(paidAmount);
        const due = Math.max(total - Math.min(paid, total), 0);
        const canChoosePaidNow = allowed.length !== 1 || allowed[0] === 'PARTIAL';
        const hasDue = type === 'CREDIT' || type === 'PARTIAL';

        paidAmountField?.classList.toggle('hidden', !canChoosePaidNow);
        if (paidAmount) {
            paidAmount.required = canChoosePaidNow;
            paidAmount.readOnly = !canChoosePaidNow;
            paidAmount.max = amount?.value || '';
        }

        dueAmountField?.classList.toggle('hidden', !hasDue || total <= 0);
        if (dueAmountPreview) {
            dueAmountPreview.value = total > 0 ? due.toFixed(amountScale()) : '';
        }

        return type;
    };

    const matchingPartyOptions = (partyType) => {
        const matches = [];

        Array.from(party?.options || []).forEach((option) => {
            if (!option.value) return;
            const visible = !partyType || partyType === 'Any' || option.dataset.partyType === partyType;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible) matches.push(option);
        });

        if (party?.selectedOptions[0]?.disabled) party.value = '';

        return matches;
    };

    const syncPartyRequirement = (needsParty, partyType) => {
        if (!party) return false;

        const matches = matchingPartyOptions(partyType);
        let changed = false;

        if (!needsParty) {
            partyField?.classList.add('hidden');
            autoPartyNotice?.classList.add('hidden');
            party.required = false;
            return false;
        }

        if (matches.length === 1) {
            if (party.value !== matches[0].value) {
                party.value = matches[0].value;
                changed = true;
            }

            party.required = false;
            partyField?.classList.add('hidden');
            autoPartyNotice?.classList.remove('hidden');
            if (autoPartyLabel) autoPartyLabel.textContent = `${partyLabel?.textContent || 'Party'} selected automatically`;
            if (autoPartyName) autoPartyName.textContent = matches[0].textContent.trim();
            return changed;
        }

        party.required = true;
        partyField?.classList.remove('hidden');
        autoPartyNotice?.classList.add('hidden');

        const emptyOption = party.options[0];
        if (emptyOption) {
            emptyOption.textContent = matches.length === 0
                ? `No active ${partyType && partyType !== 'Any' ? partyType : 'party'} available`
                : `Select ${String(partyLabel?.textContent || 'party').toLowerCase()}`;
        }

        return changed;
    };

    const setPreliminaryRequirements = (type) => {
        const hasSelectedHead = Boolean(head?.value);
        const allowed = selectedAllowedSettlements();
        const totalEntered = numericValue(amount) > 0;

        // Show Receive In / Pay From as soon as a selected head can use a
        // paid amount. Once an amount is entered, the inferred settlement
        // decides whether the money account is actually required.
        const needsMoney = hasSelectedHead && (
            totalEntered
                ? type === 'CASH' || type === 'PARTIAL'
                : allowed.includes('CASH') || allowed.includes('PARTIAL')
        );
        const needsParty = hasSelectedHead && (type === 'CREDIT' || type === 'PARTIAL');
        const expectedPartyType = head?.selectedOptions[0]?.dataset.partyType || 'Any';

        moneyField?.classList.toggle('hidden', !needsMoney);
        if (money) money.required = needsMoney;
        syncPartyRequirement(needsParty, expectedPartyType);
    };

    const refreshPreview = async () => {
        const type = syncAmountFields();
        setPreliminaryRequirements(type);
        updatePaidAmountCopy();

        if (!head?.value || !settlement?.value) {
            if (preview && emptyPreviewTemplate) preview.innerHTML = emptyPreviewTemplate.innerHTML;
            return;
        }

        const params = new URLSearchParams({
            category: form?.querySelector('[name="category"]')?.value || '',
            settlement_type: settlement.value,
            transaction_head_id: head.value,
            money_account_id: money?.value || '',
            party_id: party?.value || '',
            amount: amount?.value || '0',
            paid_amount: paidAmount?.value || '',
        });

        try {
            const response = await fetch(`${previewUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Preview request failed.');

            preview.innerHTML = data.html;
            if (settlement && data.settlementType) settlement.value = data.settlementType;

            const totalEntered = numericValue(amount) > 0;
            const allowed = selectedAllowedSettlements();
            const showMoneyBeforeAmount = Boolean(head?.value)
                && !totalEntered
                && (allowed.includes('CASH') || allowed.includes('PARTIAL'));
            const moneyRequired = Boolean(data.moneyRequired || showMoneyBeforeAmount);

            moneyField?.classList.toggle('hidden', !moneyRequired);
            if (money) money.required = moneyRequired;
            if (moneyLabel && data.moneyLabel) moneyLabel.textContent = data.moneyLabel;
            if (partyLabel && data.partyLabel) partyLabel.textContent = data.partyLabel;
            updatePaidAmountCopy();

            if (data.autoSelectedPartyId && party && !party.value) {
                party.value = String(data.autoSelectedPartyId);
            }

            if (syncPartyRequirement(Boolean(data.partyRequired), data.partyType)) {
                window.setTimeout(refreshPreview, 0);
            }
        } catch (_) {
            preview.innerHTML = '<div class="hg-notice">The summary could not be loaded. Check the selected setup and try again.</div>';
        }
    };

    const schedulePreview = () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 180);
    };

    head?.addEventListener('change', () => {
        const allowed = selectedAllowedSettlements();
        if (allowed.length === 1) autoSyncPaidAmount = true;
        refreshPreview();
    });
    money?.addEventListener('change', refreshPreview);
    party?.addEventListener('change', refreshPreview);
    amount?.addEventListener('input', () => {
        syncAmountFields();
        schedulePreview();
    });
    paidAmount?.addEventListener('input', () => {
        autoSyncPaidAmount = false;
        syncAmountFields();
        schedulePreview();
    });

    refreshPreview();
}

const updateAttachmentSelection = (input) => {
    const form = input.closest('form');
    const output = form?.querySelector('[data-attachment-selected]');
    if (!output) return;

    const files = [...input.files || []];
    if (!files.length) {
        output.hidden = true;
        output.textContent = '';
        return;
    }

    const names = files.map((file) => file.name).join(', ');
    output.textContent = files.length === 1
        ? `Selected: ${names}`
        : `Selected ${files.length} files: ${names}`;
    output.hidden = false;
};

const attachmentInputs = document.querySelectorAll('[data-attachment-input]');

attachmentInputs.forEach((input) => {
    input.addEventListener('change', () => updateAttachmentSelection(input));
});

const cameraWidgets = document.querySelectorAll('[data-camera-widget]');

cameraWidgets.forEach((widget) => {
    const input = widget.querySelector('[data-camera-file-input]');
    const startButton = widget.querySelector('[data-camera-start]');
    const fallbackButton = widget.querySelector('[data-camera-fallback]');
    const panel = widget.querySelector('[data-camera-panel]');
    const video = widget.querySelector('[data-camera-video]');
    const canvas = widget.querySelector('[data-camera-canvas]');
    const preview = widget.querySelector('[data-camera-preview]');
    const captureButton = widget.querySelector('[data-camera-capture]');
    const retakeButton = widget.querySelector('[data-camera-retake]');
    const closeButton = widget.querySelector('[data-camera-close]');
    const message = widget.querySelector('[data-camera-message]');
    let stream = null;
    let cameraAssigningFile = false;

    if (!input || !startButton || !panel || !video || !canvas || !captureButton || !closeButton) {
        return;
    }

    const setMessage = (text, isError = false) => {
        if (!message) return;
        message.textContent = text;
        message.hidden = text === '';
        message.classList.toggle('is-error', isError);
    };

    const showFallback = () => {
        if (fallbackButton) {
            fallbackButton.hidden = false;
        }
    };

    const hideFallback = () => {
        if (fallbackButton) {
            fallbackButton.hidden = true;
        }
    };

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        video.srcObject = null;
    };

    const closeCamera = () => {
        stopCamera();
        panel.hidden = true;
        if (preview) {
            preview.hidden = true;
            preview.innerHTML = '';
        }
        if (retakeButton) retakeButton.hidden = true;
        captureButton.hidden = false;
    };

    const explainCameraUnavailable = (reason = '') => {
        showFallback();
        const secureHint = window.isSecureContext || ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)
            ? ''
            : ' Use HTTPS on the phone/tablet to allow browser camera access.';
        const details = reason ? ` ${reason}` : '';
        setMessage(`Camera could not be opened directly.${details}${secureHint} You can use Choose from Gallery only as a fallback.`, true);
    };

    const startCamera = async () => {
        setMessage('');
        closeCamera();
        hideFallback();

        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            explainCameraUnavailable('This browser does not support direct camera capture.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1600 },
                    height: { ideal: 1200 },
                },
            });

            video.srcObject = stream;
            panel.hidden = false;
            captureButton.hidden = false;
            if (retakeButton) retakeButton.hidden = true;
            if (preview) {
                preview.hidden = true;
                preview.innerHTML = '';
            }
            await video.play();
            setMessage('Camera is open. Place the receipt inside the frame, then tap Use This Photo.');
        } catch (error) {
            stopCamera();
            const errorName = error?.name ? `(${error.name})` : '';
            explainCameraUnavailable(errorName);
        }
    };

    const attachCapturedPhoto = (blob) => {
        if (typeof DataTransfer === 'undefined') {
            explainCameraUnavailable('This browser cannot attach the captured image automatically.');
            return false;
        }

        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const file = new File([blob], `receipt-${timestamp}.jpg`, { type: 'image/jpeg' });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        cameraAssigningFile = true;
        input.files = dataTransfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        cameraAssigningFile = false;
        return true;
    };

    const capturePhoto = () => {
        if (!stream || video.readyState < 2) {
            setMessage('Camera is not ready yet. Please wait a moment and try again.', true);
            return;
        }

        const width = video.videoWidth || 1280;
        const height = video.videoHeight || 960;
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, width, height);

        canvas.toBlob((blob) => {
            if (!blob) {
                setMessage('The photo could not be captured. Please try again.', true);
                return;
            }

            if (!attachCapturedPhoto(blob)) {
                return;
            }

            if (preview) {
                const url = URL.createObjectURL(blob);
                preview.innerHTML = `<img src="${url}" alt="Captured receipt preview">`;
                preview.hidden = false;
            }

            stopCamera();
            captureButton.hidden = true;
            if (retakeButton) retakeButton.hidden = false;
            setMessage('Receipt photo is ready. Submit the transaction to upload it.');
        }, 'image/jpeg', 0.88);
    };

    startButton.addEventListener('click', startCamera);
    captureButton.addEventListener('click', capturePhoto);
    closeButton.addEventListener('click', closeCamera);
    retakeButton?.addEventListener('click', startCamera);

    input.addEventListener('change', () => {
        if (input.files && input.files.length > 0 && !cameraAssigningFile) {
            closeCamera();
            setMessage('Image selected. Submit the transaction to upload it.');
        }
    });
});
