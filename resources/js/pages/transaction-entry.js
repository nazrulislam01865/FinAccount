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


const transactionCategoryTabs = document.querySelectorAll('[data-transaction-category-tab]');

transactionCategoryTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        window.HisebGhorSearchableSelect?.closeAll();
        transactionCategoryTabs.forEach((item) => {
            item.classList.remove('active');
            item.removeAttribute('aria-current');
        });
        tab.classList.add('active');
        tab.setAttribute('aria-current', 'page');
    });
});

const page = document.querySelector('[data-transaction-entry]');

if (page) {
    const form = page.querySelector('[data-transaction-form]');
    const head = document.getElementById('transaction_head_id');
    const categoryInput = form?.querySelector('[data-transaction-category-input]');
    const settlement = document.getElementById('settlement_type');
    const money = document.getElementById('money_account_id');
    const transferToMoney = document.getElementById('transfer_to_money_account_id');
    const party = document.getElementById('party_id');
    const amount = document.getElementById('amount');
    const paidAmount = document.getElementById('paid_amount');
    const dueAmountPreview = document.getElementById('due_amount_preview');
    const moneyField = document.getElementById('money-field');
    const transferToField = document.getElementById('transfer-to-field');
    const partyField = document.getElementById('party-field');
    const autoPartyNotice = document.getElementById('auto-party-notice');
    const autoPartyLabel = document.getElementById('auto-party-label');
    const autoPartyName = document.getElementById('auto-party-name');
    const paidAmountField = document.getElementById('paid-amount-field');
    const dueAmountField = document.getElementById('due-amount-field');
    const moneyLabel = document.getElementById('money-label');
    const transferToLabel = document.getElementById('transfer-to-label');
    const partyLabel = document.getElementById('party-label');
    const paidAmountLabel = document.getElementById('paid-amount-label');
    const paidAmountHelp = document.getElementById('paid-amount-help');
    const sellingType = form?.querySelector('[data-sale-selling-type]');
    const saleWarehouseField = form?.querySelector('[data-sale-warehouse-field]');
    const saleWarehouse = form?.querySelector('[data-sale-warehouse]');
    const saleCustomerField = form?.querySelector('[data-sale-customer-field]');
    const saleFeedSection = form?.querySelector('[data-transaction-sale-feed]');
    const saleFeedConfig = window.HISEBGHOR_TRANSACTION_SALE;
    const saleFeedOnlyFields = Array.from(form?.querySelectorAll('[data-sale-feed-only]') || []);
    const saleTotalBillLabel = form?.querySelector('[data-sale-total-bill-label]');
    const saleTotalBillHelp = form?.querySelector('[data-sale-total-bill-help]');
    const preview = document.getElementById('journal-preview');
    const emptyPreviewTemplate = document.getElementById('journal-preview-empty-template');
    const previewUrl = preview?.dataset.previewUrl;
    let previewTimer;
    let previewRequestController = null;
    let previewRequestVersion = 0;
    const dueSettlementMode = form?.dataset.dueSettlement === '1';
    let autoSyncPaidAmount = form?.dataset.autoSyncPaid === '1';

    const refreshSearchable = (select) => {
        window.HisebGhorSearchableSelect?.refresh(select);
    };

    let rememberedWarehouseId = saleWarehouse?.value || '';
    let recalculateSaleFeed = () => {};
    let refreshSaleBusinessItems = () => {};

    const isCurrentSaleCategory = () => String(categoryInput?.value || '').toUpperCase() === 'SALE';
    const currentTransactionFlow = () => head?.selectedOptions[0]?.dataset.direction || form?.dataset.transactionFlow || '';
    const isTransferMode = () => currentTransactionFlow() === 'transfer';

    const syncCategoryFromHead = () => {
        const selectedCategory = head?.selectedOptions[0]?.dataset.category || '';
        const selectedDirection = head?.selectedOptions[0]?.dataset.direction || '';
        if (form && selectedDirection) form.dataset.transactionFlow = selectedDirection;
        if (!selectedCategory || !categoryInput || categoryInput.value === selectedCategory) return false;
        categoryInput.value = selectedCategory;
        return true;
    };

    const isFeedSaleMode = () => {
        if (!isCurrentSaleCategory() || !sellingType || !saleFeedSection) return false;
        const option = sellingType.selectedOptions[0];
        const selected = String(sellingType.value || '').toLowerCase();

        return option?.dataset.feedSaleMode === '1' || (selected !== '' && selected !== 'others');
    };

    const setNestedControlsDisabled = (container, disabled) => {
        container?.querySelectorAll?.('input, select, textarea, button').forEach((control) => {
            control.disabled = disabled;
        });
    };

    const syncSaleMode = () => {
        const feedMode = isFeedSaleMode();

        saleFeedOnlyFields.forEach((field) => {
            field.classList.toggle('hidden', !feedMode);
            setNestedControlsDisabled(field, !feedMode);
        });

        if (saleCustomerField && party) {
            saleCustomerField.classList.toggle('hidden', !feedMode);
            party.required = feedMode;
            autoPartyNotice?.classList.add('hidden');
        }

        if (saleWarehouseField && saleWarehouse) {
            saleWarehouseField.classList.toggle('hidden', !feedMode);
            saleWarehouse.required = feedMode;
            saleWarehouse.disabled = !feedMode;

            const selectedArea = sellingType ? String(sellingType.value || '').toLowerCase() : null;
            let firstValid = null;

            Array.from(saleWarehouse.options).forEach((opt) => {
                if (opt.value === '') return;
                const optArea = String(opt.dataset.businessArea || '').toLowerCase();
                const matches = !selectedArea || selectedArea === 'others' || optArea === '' || optArea === selectedArea;
                opt.hidden = !matches;
                opt.disabled = !matches;
                if (matches && !firstValid) firstValid = opt.value;
            });

            if (feedMode) {
                const currentOpt = saleWarehouse.options[saleWarehouse.selectedIndex];
                if (!currentOpt || currentOpt.hidden) {
                    saleWarehouse.value = firstValid || '';
                } else if (!saleWarehouse.value && rememberedWarehouseId) {
                    const rememberedOpt = Array.from(saleWarehouse.options).find(o => o.value === rememberedWarehouseId);
                    if (rememberedOpt && !rememberedOpt.hidden) {
                        saleWarehouse.value = rememberedWarehouseId;
                    }
                }
            } else {
                if (saleWarehouse.value) rememberedWarehouseId = saleWarehouse.value;
                saleWarehouse.value = '';
            }

            refreshSearchable(saleWarehouse);
        }

        if (amount) {
            amount.readOnly = feedMode;
            amount.classList.toggle('hg-readonly-input', feedMode);
        }

        if (saleTotalBillLabel) saleTotalBillLabel.textContent = feedMode ? 'Total Bill' : 'Amount';
        if (saleTotalBillHelp) {
            saleTotalBillHelp.textContent = feedMode
                ? 'Total Bill = Total Amount + Other Charges.'
                : 'Enter the total sale amount for all other sales.';
        }

        if (form) {
            form.dataset.saleStockInvalid = '0';
            form.dataset.saleHasFeedLine = feedMode ? form.dataset.saleHasFeedLine || '0' : '0';
        }

        if (feedMode) {
            refreshSaleBusinessItems();
            recalculateSaleFeed();
        }
    };

    const syncSaleWarehouseField = syncSaleMode;

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
        if (isTransferMode()) return ['CASH'];
        const fromHead = parseJson(head?.selectedOptions[0]?.dataset.allowedSettlements, []);
        return fromHead.length > 0
            ? fromHead
            : parseJson(form?.dataset.defaultAllowedSettlements, ['CASH']);
    };

    const usesReceivedWording = () => (moneyLabel?.textContent || '').toLowerCase().includes('receive');

    const updatePaidAmountCopy = () => {
        if (isFeedSaleMode()) {
            if (paidAmountLabel) paidAmountLabel.textContent = 'Received Amount';
            if (paidAmountHelp) paidAmountHelp.textContent = 'Enter 0 when the full bill will remain due.';
            return;
        }

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

        if (isTransferMode()) {
            if (paidAmount) paidAmount.value = total > 0 ? total.toFixed(amountScale()) : '';
            if (settlement) settlement.value = 'CASH';
            return 'CASH';
        }

        if (dueSettlementMode) {
            if (paidAmount) paidAmount.value = total > 0 ? total.toFixed(amountScale()) : '';
            if (settlement) settlement.value = 'CASH';
            return 'CASH';
        }

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
        const canChoosePaidNow = !isTransferMode() && (allowed.length !== 1 || allowed[0] === 'PARTIAL');
        const hasDue = !isTransferMode() && (type === 'CREDIT' || type === 'PARTIAL');

        paidAmountField?.classList.toggle('hidden', dueSettlementMode || isTransferMode() || !canChoosePaidNow);
        if (paidAmount) {
            paidAmount.required = !dueSettlementMode && !isTransferMode() && canChoosePaidNow;
            paidAmount.readOnly = dueSettlementMode || isTransferMode() || !canChoosePaidNow;
            paidAmount.max = amount?.value || '';
        }

        dueAmountField?.classList.toggle('hidden', dueSettlementMode || !hasDue || total <= 0);
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
        refreshSearchable(party);

        return matches;
    };

    const syncPartyRequirement = (needsParty, partyType) => {
        if (!party) return false;

        if (saleCustomerField && isFeedSaleMode()) {
            matchingPartyOptions('Customer');
            party.required = true;
            partyField?.classList.remove('hidden');
            autoPartyNotice?.classList.add('hidden');
            refreshSearchable(party);
            return false;
        }

        const matches = matchingPartyOptions(partyType);
        let changed = false;

        if (!needsParty) {
            partyField?.classList.add('hidden');
            autoPartyNotice?.classList.add('hidden');
            party.required = false;
            refreshSearchable(party);
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
            refreshSearchable(party);
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

        refreshSearchable(party);
        return changed;
    };

    const setPreliminaryRequirements = (type) => {
        const hasSelectedHead = Boolean(head?.value);
        const allowed = selectedAllowedSettlements();
        const totalEntered = numericValue(amount) > 0;

        if (dueSettlementMode) {
            moneyField?.classList.toggle('hidden', !hasSelectedHead);
            transferToField?.classList.add('hidden');
            if (money) money.required = hasSelectedHead;
            if (transferToMoney) transferToMoney.required = false;
            partyField?.classList.add('hidden');
            autoPartyNotice?.classList.add('hidden');
            if (party) party.required = false;
            refreshSearchable(money);
            refreshSearchable(transferToMoney);
            refreshSearchable(party);
            return;
        }

        if (isTransferMode()) {
            moneyField?.classList.remove('hidden');
            transferToField?.classList.remove('hidden');
            if (money) money.required = true;
            if (transferToMoney) transferToMoney.required = true;
            if (moneyLabel) moneyLabel.textContent = 'Pay From';
            if (transferToLabel) transferToLabel.textContent = 'Pay To';
            partyField?.classList.add('hidden');
            autoPartyNotice?.classList.add('hidden');
            if (party) party.required = false;
            refreshSearchable(money);
            refreshSearchable(transferToMoney);
            refreshSearchable(party);
            return;
        }

        transferToField?.classList.add('hidden');
        if (transferToMoney) transferToMoney.required = false;

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
        refreshSearchable(money);
        syncPartyRequirement(needsParty, expectedPartyType);
    };

    const refreshPreview = async () => {
        const type = syncAmountFields();
        setPreliminaryRequirements(type);
        updatePaidAmountCopy();

        if ((!isTransferMode() && !head?.value) || !settlement?.value) {
            if (preview && emptyPreviewTemplate) preview.innerHTML = emptyPreviewTemplate.innerHTML;
            return;
        }

        const params = new URLSearchParams({
            category: form?.querySelector('[name="category"]')?.value || '',
            settlement_type: settlement.value,
            transaction_head_id: isTransferMode() ? '' : (head?.value || ''),
            money_account_id: money?.value || '',
            transfer_to_money_account_id: transferToMoney?.value || '',
            party_id: party?.value || '',
            amount: amount?.value || '0',
            paid_amount: paidAmount?.value || '',
        });

        previewRequestController?.abort();
        previewRequestController = new AbortController();
        const requestVersion = ++previewRequestVersion;

        try {
            const response = await fetch(`${previewUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: previewRequestController.signal,
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Preview request failed.');
            if (requestVersion !== previewRequestVersion) return;

            preview.innerHTML = data.html;
            if (settlement && data.settlementType) settlement.value = data.settlementType;

            const totalEntered = numericValue(amount) > 0;
            const allowed = selectedAllowedSettlements();
            const showMoneyBeforeAmount = (isTransferMode() || Boolean(head?.value))
                && !totalEntered
                && (allowed.includes('CASH') || allowed.includes('PARTIAL'));
            const transferRequired = Boolean(data.transferRequired);
            const moneyRequired = Boolean(data.moneyRequired || showMoneyBeforeAmount || transferRequired);

            moneyField?.classList.toggle('hidden', !moneyRequired);
            transferToField?.classList.toggle('hidden', !transferRequired);
            if (money) money.required = moneyRequired;
            if (transferToMoney) transferToMoney.required = transferRequired;
            if (moneyLabel && data.moneyLabel) moneyLabel.textContent = data.moneyLabel;
            if (transferToLabel && data.transferToLabel) transferToLabel.textContent = data.transferToLabel;
            if (partyLabel && data.partyLabel) partyLabel.textContent = data.partyLabel;
            refreshSearchable(money);
            refreshSearchable(transferToMoney);
            refreshSearchable(party);
            updatePaidAmountCopy();

            if (data.autoSelectedPartyId && party && !party.value) {
                party.value = String(data.autoSelectedPartyId);
                refreshSearchable(party);
            }

            if (dueSettlementMode || transferRequired) {
                partyField?.classList.add('hidden');
                autoPartyNotice?.classList.add('hidden');
                if (party) party.required = false;
            } else if (syncPartyRequirement(Boolean(data.partyRequired), data.partyType)) {
                window.setTimeout(refreshPreview, 0);
            }
        } catch (error) {
            if (error?.name === 'AbortError' || requestVersion !== previewRequestVersion) return;
            preview.innerHTML = '<div class="hg-notice">The summary could not be loaded. Check the selected setup and try again.</div>';
        }
    };

    const schedulePreview = () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 180);
    };

    head?.addEventListener('change', () => {
        syncCategoryFromHead();
        syncSaleMode();
        const allowed = selectedAllowedSettlements();
        if (allowed.length === 1) autoSyncPaidAmount = true;
        refreshPreview();
    });
    money?.addEventListener('change', refreshPreview);
    transferToMoney?.addEventListener('change', refreshPreview);
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
    sellingType?.addEventListener('change', () => {
        syncSaleMode();
        refreshSaleBusinessItems();
        updatePaidAmountCopy();
        schedulePreview();
    });
    saleWarehouse?.addEventListener('change', () => {
        if (saleWarehouse.value) rememberedWarehouseId = saleWarehouse.value;
    });

    const initTransactionSaleFeed = () => {
        if (!saleFeedSection || !saleFeedConfig || !Array.isArray(saleFeedConfig.items)) return;

        const rowsContainer = form?.querySelector('[data-transaction-sale-feed-lines]');
        const addButton = form?.querySelector('[data-transaction-sale-feed-add]');
        const itemsTotalInput = form?.querySelector('[data-sale-items-total]');
        const otherChargesInput = form?.querySelector('[data-sale-other-charges]');
        const totalBillInput = form?.querySelector('[data-sale-total-bill]');
        const scale = Number.isInteger(saleFeedConfig.decimalPlaces) ? saleFeedConfig.decimalPlaces : 2;
        const numberValue = (value) => {
            const parsed = Number.parseFloat(String(value ?? 0).replaceAll(',', ''));
            return Number.isFinite(parsed) ? parsed : 0;
        };
        let rowCounter = 0;

        if (!rowsContainer || !addButton || !totalBillInput) return;

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const moneyText = (value) => `${saleFeedConfig.currencyCode ?? ''} ${numberValue(value).toLocaleString(undefined, {
            minimumFractionDigits: scale,
            maximumFractionDigits: scale,
        })}`.trim();

        const selectedBusinessArea = () => String(sellingType?.value || '').toLowerCase();

        const itemsForSelectedArea = () => {
            const area = selectedBusinessArea();
            return saleFeedConfig.items.filter((item) => String(item.businessArea || '').toLowerCase() === area);
        };

        const defaultItemName = () => itemsForSelectedArea()[0]?.name ?? '';

        const itemOptions = (selectedName) => {
            const first = '<option value="">Select item</option>';
            return first + itemsForSelectedArea().map((item) => {
                const selected = String(item.name) === String(selectedName ?? '') ? ' selected' : '';
                const meta = [item.businessUnit, item.location].filter(Boolean).join(' · ');
                return `<option value="${escapeHtml(item.name)}" data-meta="${escapeHtml(meta)}"${selected}>${escapeHtml(item.name)}</option>`;
            }).join('');
        };

        const syncRowItem = (row, resetRate = false) => {
            const itemSelect = row.querySelector('[data-sale-feed-item]');
            const rateInput = row.querySelector('[data-sale-feed-rate]');
            const meta = row.querySelector('[data-sale-feed-meta]');
            const selected = itemSelect?.selectedOptions?.[0];

            if (meta) {
                meta.textContent = selected?.dataset?.meta || '';
            }

            if (resetRate && rateInput) {
                const item = itemsForSelectedArea().find((candidate) => String(candidate.name) === String(itemSelect?.value || ''));
                if (item && item.salePrice !== undefined) {
                    rateInput.value = numberValue(item.salePrice).toFixed(2);
                }
            }
        };

        const lineMarkup = (index, values = {}) => {
            const selectedItemName = values.item_name ?? values.item_id ?? defaultItemName();
            const item = itemsForSelectedArea().find((candidate) => String(candidate.name) === String(selectedItemName));
            const rate = values.rate !== undefined && values.rate !== null && values.rate !== ''
                ? numberValue(values.rate)
                : numberValue(item?.salePrice ?? 0);
            const quantity = values.quantity ?? 1;
            const unit = values.unit ?? 'Unit';

            return `
                <tr data-sale-feed-line>
                    <td class="hg-feed-item-cell">
                        <select name="lines[${index}][item_name]" data-sale-feed-item required data-hg-searchable-ignore>${itemOptions(selectedItemName)}</select>
                        <small data-sale-feed-meta></small>
                    </td>
                    <td><input name="lines[${index}][unit]" data-sale-feed-unit type="text" value="${escapeHtml(unit)}" placeholder="Unit"></td>
                    <td><input class="right" name="lines[${index}][quantity]" data-sale-feed-quantity type="number" min="0.0001" step="0.0001" value="${escapeHtml(quantity)}" required></td>
                    <td><input class="right" name="lines[${index}][rate]" data-sale-feed-rate type="number" min="0" step="0.01" value="${escapeHtml(rate.toFixed(2))}" required></td>
                    <td class="right"><strong data-sale-feed-line-total>${moneyText(0)}</strong></td>
                    <td><button class="hg-btn hg-btn-small hg-btn-danger" type="button" data-sale-feed-remove aria-label="Remove item">×</button></td>
                </tr>`;
        };

        const addLine = (values = {}) => {
            rowsContainer.insertAdjacentHTML('beforeend', lineMarkup(rowCounter++, values));
            const row = rowsContainer.lastElementChild;
            syncRowItem(row, false);
            calculateSaleFeed();
        };

        const setTotalBill = (value) => {
            const formatted = numberValue(value).toFixed(scale);
            if (totalBillInput.value === formatted) return;
            totalBillInput.value = formatted;
            totalBillInput.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const calculateSaleFeed = () => {
            if (!isFeedSaleMode()) {
                form.dataset.saleStockInvalid = '0';
                form.dataset.saleHasFeedLine = '0';
                return;
            }

            let subtotal = 0;
            let hasValidLine = false;

            rowsContainer.querySelectorAll('[data-sale-feed-line]').forEach((row) => {
                const itemName = row.querySelector('[data-sale-feed-item]')?.value;
                const quantity = Math.max(0, numberValue(row.querySelector('[data-sale-feed-quantity]')?.value));
                const rate = Math.max(0, numberValue(row.querySelector('[data-sale-feed-rate]')?.value));
                const lineTotal = quantity * rate;

                subtotal += lineTotal;
                hasValidLine ||= Boolean(itemName) && quantity > 0;

                const total = row.querySelector('[data-sale-feed-line-total]');
                if (total) total.textContent = moneyText(lineTotal);
            });

            const otherCharges = Math.max(0, numberValue(otherChargesInput?.value));
            const totalBill = subtotal + otherCharges;

            if (itemsTotalInput) itemsTotalInput.value = subtotal.toFixed(scale);
            setTotalBill(totalBill);
            form.dataset.saleStockInvalid = '0';
            form.dataset.saleHasFeedLine = hasValidLine ? '1' : '0';
        };

        recalculateSaleFeed = calculateSaleFeed;
        refreshSaleBusinessItems = () => {
            rowsContainer.querySelectorAll('[data-sale-feed-line]').forEach((row) => {
                const select = row.querySelector('[data-sale-feed-item]');
                if (!select) return;

                const current = select.value;
                select.innerHTML = itemOptions(current);
                if (current && !select.value) {
                    select.value = defaultItemName();
                }
                if (!select.value) {
                    select.value = defaultItemName();
                }
                syncRowItem(row, true);
            });
            calculateSaleFeed();
        };

        const initialLines = Array.isArray(saleFeedConfig.initialLines) && saleFeedConfig.initialLines.length
            ? saleFeedConfig.initialLines
            : [{ item_name: defaultItemName(), unit: 'Unit', quantity: 1, rate: 0 }];
        initialLines.forEach((line) => addLine(line));
        refreshSaleBusinessItems();

        addButton.addEventListener('click', () => addLine({
            item_name: defaultItemName(),
            unit: 'Unit',
            quantity: 1,
            rate: 0,
        }));

        rowsContainer.addEventListener('input', (event) => {
            if (event.target.matches('[data-sale-feed-quantity], [data-sale-feed-rate]')) {
                calculateSaleFeed();
            }
        });

        rowsContainer.addEventListener('change', (event) => {
            const row = event.target.closest('[data-sale-feed-line]');
            if (!row) return;

            if (event.target.matches('[data-sale-feed-item]')) {
                syncRowItem(row, true);
            }
            calculateSaleFeed();
        });

        rowsContainer.addEventListener('click', (event) => {
            const button = event.target.closest('[data-sale-feed-remove]');
            if (!button) return;
            const rows = rowsContainer.querySelectorAll('[data-sale-feed-line]');
            if (rows.length <= 1) return;
            button.closest('[data-sale-feed-line]')?.remove();
            calculateSaleFeed();
        });

        otherChargesInput?.addEventListener('input', calculateSaleFeed);

        form?.addEventListener('submit', (event) => {
            if (!isFeedSaleMode()) return;

            calculateSaleFeed();

            if (form.dataset.saleHasFeedLine !== '1') {
                event.preventDefault();
                window.alert('Add at least one item before posting the sale.');
            }
        });

        calculateSaleFeed();
    };

    form?.addEventListener('submit', (event) => {
        if (!isTransferMode()) return;
        if (money?.value && transferToMoney?.value && money.value === transferToMoney.value) {
            event.preventDefault();
            window.alert('Pay From and Pay To must be different accounts.');
        }
    });

    syncCategoryFromHead();
    initTransactionSaleFeed();
    syncSaleMode();
    updatePaidAmountCopy();
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

// Transaction Entry: prevent browser scroll restoration from keeping the page horizontally shifted after direction/type tab navigation.
const resetTransactionEntryHorizontalScroll = () => {
    const transactionEntryPage = document.querySelector('.hg-entry-filter-panel, [data-transaction-entry]');
    if (!transactionEntryPage) return;

    if (document.documentElement) document.documentElement.scrollLeft = 0;
    if (document.body) document.body.scrollLeft = 0;
    if (window.scrollX !== 0) window.scrollTo({ left: 0, top: window.scrollY, behavior: 'auto' });
};

if (document.querySelector('.hg-entry-filter-panel, [data-transaction-entry]')) {
    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    resetTransactionEntryHorizontalScroll();
    window.addEventListener('load', () => window.requestAnimationFrame(resetTransactionEntryHorizontalScroll));
    window.addEventListener('pageshow', () => window.requestAnimationFrame(resetTransactionEntryHorizontalScroll));
    window.setTimeout(resetTransactionEntryHorizontalScroll, 50);
    window.setTimeout(resetTransactionEntryHorizontalScroll, 250);

    document.querySelectorAll('[data-transaction-direction-tab], [data-transaction-category-tab]').forEach((tab) => {
        tab.addEventListener('click', resetTransactionEntryHorizontalScroll, { capture: true });
    });
}
