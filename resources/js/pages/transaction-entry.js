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
    const head = document.getElementById('transaction_head_id');
    const money = document.getElementById('money_account_id');
    const party = document.getElementById('party_id');
    const amount = document.getElementById('amount');
    const moneyField = document.getElementById('money-field');
    const partyField = document.getElementById('party-field');
    const preview = document.getElementById('journal-preview');
    const emptyPreviewTemplate = document.getElementById('journal-preview-empty-template');
    const form = document.querySelector('[data-transaction-form]');
    const previewUrl = preview.dataset.previewUrl;
    let previewTimer;

    const filterPartyOptions = (partyType) => {
        [...party.options].forEach((option) => {
            if (!option.value) return;

            const allowed = partyType === 'Any' || option.dataset.partyType === partyType;
            option.hidden = !allowed;
            option.disabled = !allowed;
        });

        const selected = party.options[party.selectedIndex];

        if (selected && selected.disabled) {
            party.value = '';
            return true;
        }

        return false;
    };

    const refreshPreview = async () => {
        if (!head.value) {
            preview.innerHTML = emptyPreviewTemplate.innerHTML;
            moneyField.classList.add('hidden');
            partyField.classList.add('hidden');
            money.required = false;
            party.required = false;
            return;
        }

        const category = form.querySelector('[name="category"]')?.value || '';
        const params = new URLSearchParams({
            category,
            transaction_head_id: head.value,
            money_account_id: money.value,
            party_id: party.value,
            amount: amount.value || '0',
        });

        try {
            const response = await fetch(`${previewUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Preview request failed.');
            }

            const data = await response.json();
            preview.innerHTML = data.html;
            moneyField.classList.toggle('hidden', !data.moneyRequired);
            partyField.classList.toggle('hidden', !data.partyRequired);
            money.required = data.moneyRequired;
            party.required = data.partyRequired;

            const partySelectionCleared = filterPartyOptions(data.partyType);

            if (partySelectionCleared) {
                await refreshPreview();
            }
        } catch (error) {
            preview.innerHTML = '<div class="hg-notice">The journal preview could not be loaded. You may retry by changing a field.</div>';
        }
    };

    const schedulePreview = () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 180);
    };

    [head, money, party].forEach((element) => element.addEventListener('change', refreshPreview));
    amount.addEventListener('input', schedulePreview);

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
