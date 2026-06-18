const uploader = document.querySelector('[data-profile-photo-uploader]');

if (uploader) {
    const input = uploader.querySelector('input[type="file"]');
    const preview = uploader.querySelector('[data-profile-photo-preview]');
    const fileName = uploader.querySelector('[data-profile-photo-name]');
    const chooseButton = uploader.querySelector('[data-profile-photo-choose]');
    let objectUrl = null;

    const selectFile = (file) => {
        if (!file || !file.type.startsWith('image/')) return;

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = () => {
        const file = input.files?.[0];
        if (!file) return;

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${objectUrl}" alt="Selected profile picture preview">`;
        fileName.textContent = file.name;
        uploader.classList.add('has-file');
    };

    chooseButton?.addEventListener('click', () => input.click());
    uploader.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        input.click();
    });
    input.addEventListener('change', render);

    ['dragenter', 'dragover'].forEach((name) => uploader.addEventListener(name, (event) => {
        event.preventDefault();
        uploader.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((name) => uploader.addEventListener(name, (event) => {
        event.preventDefault();
        uploader.classList.remove('is-dragging');
    }));
    uploader.addEventListener('drop', (event) => selectFile(event.dataTransfer?.files?.[0]));

    window.addEventListener('beforeunload', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
}
