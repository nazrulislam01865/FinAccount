const config = window.HISEBGHOR_BUSINESS_TRACKING;

if (config) {
    const unitForm = document.getElementById('businessTrackingUnitForm');
    const businessSelect = document.getElementById('trackingBusiness');
    const unitTypeSelect = document.getElementById('trackingUnitType');
    const unitCode = document.getElementById('trackingUnitCode');
    const unitName = document.getElementById('trackingUnitName');
    const unitNameLabel = document.getElementById('trackingUnitNameLabel');
    const parentSelect = document.getElementById('trackingParent');
    const responsiblePerson = document.getElementById('trackingResponsiblePerson');
    const startDate = document.getElementById('trackingStartDate');
    const statusSelect = document.getElementById('trackingStatus');
    const description = document.getElementById('trackingDescription');
    const methodField = unitForm?.querySelector('[data-form-method]');
    const submitLabel = unitForm?.querySelector('[data-unit-submit-label]');

    const getBusiness = (key) => config.config[key] || config.config.cattle;

    const fillOptions = (select, options, selected = '') => {
        if (!select) return;
        select.innerHTML = options.map((option) => {
            const value = String(option.value ?? option.id ?? '');
            const label = option.label ?? option.name ?? value;
            const isSelected = String(selected ?? '') === value;

            return `<option value="${escapeHtml(value)}" ${isSelected ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');
    };

    const updateUnitFormLabels = (selectedType = '', selectedParent = '') => {
        if (!businessSelect) return;

        const businessKey = businessSelect.value || 'cattle';
        const business = getBusiness(businessKey);
        const unitTypes = (business.unitTypes || []).map((unitType) => ({ value: unitType, label: unitType }));
        const parentOptions = [{ value: '', label: 'No parent' }].concat((business.parents || []).map((parent) => ({ value: parent.id, label: parent.name })));

        fillOptions(unitTypeSelect, unitTypes, selectedType || unitTypeSelect?.dataset.oldValue || unitTypes[0]?.value || '');
        fillOptions(parentSelect, parentOptions, selectedParent || parentSelect?.dataset.oldValue || '');

        if (unitNameLabel) unitNameLabel.innerHTML = `${escapeHtml(business.unitLabel || 'Unit')} Name <span class="feed-req">*</span>`;
        if (unitName) unitName.placeholder = `e.g. ${business.unitLabel || 'Unit'} ${businessKey === 'cattle' ? '03' : businessKey === 'fish' ? 'C' : 'Name'}`;
    };

    const resetUnitForm = () => {
        if (!unitForm) return;

        unitForm.action = config.storeUrl;
        methodField.value = 'POST';
        businessSelect.value = 'cattle';
        updateUnitFormLabels('', '');
        unitCode.value = 'CAT-S03';
        unitName.value = '';
        responsiblePerson.value = '';
        startDate.value = new Date().toISOString().slice(0, 10);
        statusSelect.value = '1';
        description.value = '';
        submitLabel.textContent = 'Save Tracking Unit';
    };

    const applyUnitEdit = (button) => {
        if (!unitForm || !button) return;

        unitForm.action = button.dataset.updateUrl;
        methodField.value = 'PUT';
        businessSelect.value = button.dataset.businessArea || 'cattle';
        updateUnitFormLabels(button.dataset.unitType || '', button.dataset.parentId || '');
        unitCode.value = button.dataset.code || '';
        unitName.value = button.dataset.name || '';
        responsiblePerson.value = button.dataset.responsiblePerson || '';
        startDate.value = button.dataset.startDate || '';
        statusSelect.value = button.dataset.isActive === '0' ? '0' : '1';
        description.value = button.dataset.description || '';
        submitLabel.textContent = 'Update Tracking Unit';
        unitName.focus();
    };

    businessSelect?.addEventListener('change', () => updateUnitFormLabels('', ''));
    document.querySelectorAll('[data-clear-unit-form]').forEach((button) => button.addEventListener('click', resetUnitForm));
    document.querySelectorAll('[data-edit-unit]').forEach((button) => button.addEventListener('click', () => applyUnitEdit(button)));
    document.querySelectorAll('[data-tracking-focus]').forEach((button) => button.addEventListener('click', () => unitName?.focus()));
    document.querySelectorAll('[data-prepare-tracking-unit]').forEach((button) => {
        button.addEventListener('click', () => {
            resetUnitForm();
            businessSelect.value = button.dataset.prepareTrackingUnit || 'cattle';
            updateUnitFormLabels('', '');
            unitName?.focus();
        });
    });

    updateUnitFormLabels(config.oldUnitType || '', config.oldParentId || '');

    const assignmentForm = document.querySelector('[data-default-assignment-form]');
    const sourceType = document.getElementById('assignmentSourceType');
    const sourceId = document.getElementById('assignmentSourceId');
    const sourceSelectWrap = document.querySelector('[data-source-select-wrap]');
    const sourceManualWrap = document.querySelector('[data-source-manual-wrap]');
    const assignmentBusiness = document.getElementById('assignmentBusinessArea');
    const assignmentUnit = document.getElementById('assignmentUnit');

    const refreshSourceOptions = () => {
        if (!sourceType || !sourceId) return;

        const activeSourceType = sourceType.value;
        const isManual = activeSourceType === 'manual';
        if (sourceSelectWrap) sourceSelectWrap.hidden = isManual;
        if (sourceManualWrap) sourceManualWrap.hidden = !isManual;
        sourceId.required = !isManual;

        [...sourceId.options].forEach((option) => {
            option.hidden = option.dataset.sourceType !== activeSourceType;
        });

        const firstVisible = [...sourceId.options].find((option) => !option.hidden);
        sourceId.value = firstVisible ? firstVisible.value : '';
    };

    const refreshAssignmentUnits = () => {
        if (!assignmentBusiness || !assignmentUnit) return;

        const business = getBusiness(assignmentBusiness.value || 'cattle');
        const options = [{ value: '', label: 'Ask during entry' }].concat((business.parents || []).map((parent) => ({ value: parent.id, label: parent.name })));
        fillOptions(assignmentUnit, options, assignmentUnit.value || '');
    };

    document.querySelectorAll('[data-toggle-default-assignment]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!assignmentForm) return;
            assignmentForm.hidden = !assignmentForm.hidden;
            if (!assignmentForm.hidden) {
                refreshSourceOptions();
                refreshAssignmentUnits();
                assignmentForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });

    sourceType?.addEventListener('change', refreshSourceOptions);
    assignmentBusiness?.addEventListener('change', refreshAssignmentUnits);
    refreshSourceOptions();
    refreshAssignmentUnits();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
