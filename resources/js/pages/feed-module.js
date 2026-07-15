const numberValue = (value) => {
    const parsed = Number.parseFloat(String(value ?? 0).replaceAll(',', ''));
    return Number.isFinite(parsed) ? parsed : 0;
};

const percentageValue = (value) => Math.max(0, numberValue(String(value ?? 0).replace('%', '')));

const commissionAmount = (gross, percentage) => Math.max(0, numberValue(gross) * (percentageValue(percentage) / 100));

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const initFeedModule = () => {
    const root = document.querySelector('[data-feed-form]');
    const config = window.HISEBGHOR_FEED_PAGE;

    if (!root || !config || !Array.isArray(config.items)) {
        return;
    }

    const form = root.querySelector('[data-feed-post-form]');
    const rowsContainer = root.querySelector('[data-feed-lines]');
    const addButton = root.querySelector('[data-feed-add-line]');
    const warehouseSelect = root.querySelector('[data-feed-warehouse]');
    const moneyAccount = root.querySelector('[data-feed-money-account]');
    const transactionHeadSelect = root.querySelector('[data-feed-transaction-head]');
    const paidInput = root.querySelector('#paid_amount');
    const itemsById = new Map(config.items.map((item) => [String(item.id), item]));
    let rowCounter = 0;

    const money = (value) => {
        const places = Number.isInteger(config.decimalPlaces) ? config.decimalPlaces : 2;
        return `${config.currencyCode ?? ''} ${numberValue(value).toLocaleString(undefined, {
            minimumFractionDigits: places,
            maximumFractionDigits: places,
        })}`.trim();
    };

    const selectedStock = (itemId) => {
        const warehouseId = String(warehouseSelect?.value ?? '');
        const stock = config.stock?.[warehouseId]?.[String(itemId)] ?? {};
        return {
            quantity: numberValue(stock.quantity),
            averageCost: numberValue(stock.average_cost),
        };
    };

    const itemOptions = (selectedId) => {
        const empty = '<option value="">Select feed item</option>';
        return empty + config.items.map((item) => {
            const selected = String(item.id) === String(selectedId ?? '') ? ' selected' : '';
            const meta = [item.code, item.brand, item.category].filter(Boolean).join(' · ');
            return `<option value="${escapeHtml(item.id)}"${selected}>${escapeHtml(item.name)}${meta ? ` — ${escapeHtml(meta)}` : ''}</option>`;
        }).join('');
    };

    const defaultRate = (item, unit) => {
        if (!item) return 0;
        const bagPrice = config.type === 'purchase' ? numberValue(item.purchasePrice) : numberValue(item.salePrice);
        return unit === 'KG' && numberValue(item.packSize) > 0
            ? bagPrice / numberValue(item.packSize)
            : bagPrice;
    };

    const lineMarkup = (index, values = {}) => {
        const item = itemsById.get(String(values.item_id ?? ''));
        const unit = String(values.unit ?? 'BAG').toUpperCase() === 'KG' ? 'KG' : 'BAG';
        const rate = values.rate !== undefined && values.rate !== null && values.rate !== ''
            ? numberValue(values.rate)
            : defaultRate(item, unit);
        const quantity = values.quantity ?? 1;

        if (config.type === 'purchase') {
            return `
                <tr data-feed-line>
                    <td class="hg-feed-item-cell">
                        <select name="lines[${index}][item_id]" data-feed-item required data-hg-searchable-ignore>${itemOptions(values.item_id)}</select>
                        <small data-feed-item-meta></small>
                    </td>
                    <td><select name="lines[${index}][unit]" data-feed-unit data-hg-searchable-ignore><option value="BAG"${unit === 'BAG' ? ' selected' : ''}>Bag</option><option value="KG"${unit === 'KG' ? ' selected' : ''}>KG</option></select></td>
                    <td><input class="right" name="lines[${index}][quantity]" data-feed-quantity type="number" min="0.0001" step="0.0001" value="${escapeHtml(quantity)}" required></td>
                    <td><input class="right" name="lines[${index}][rate]" data-feed-rate type="number" min="0" step="0.01" value="${escapeHtml(rate.toFixed(2))}" required></td>
                    <td class="right"><strong data-feed-line-total>${money(0)}</strong><small data-feed-base-qty></small></td>
                    <td class="hg-feed-batch-cell">
                        <input name="lines[${index}][batch_no]" data-feed-batch placeholder="Batch no." value="${escapeHtml(values.batch_no ?? '')}">
                        <input name="lines[${index}][expiry_date]" data-feed-expiry type="date" value="${escapeHtml(values.expiry_date ?? '')}">
                    </td>
                    <td><button class="feed-icon-btn" type="button" data-feed-remove-line aria-label="Remove feed item">×</button></td>
                </tr>`;
        }

        return `
            <tr data-feed-line>
                <td class="hg-feed-item-cell">
                    <select name="lines[${index}][item_id]" data-feed-item required data-hg-searchable-ignore>${itemOptions(values.item_id)}</select>
                    <small data-feed-item-meta></small>
                </td>
                <td class="right"><strong data-feed-available>0.0000 KG</strong></td>
                <td><select name="lines[${index}][unit]" data-feed-unit data-hg-searchable-ignore><option value="BAG"${unit === 'BAG' ? ' selected' : ''}>Bag</option><option value="KG"${unit === 'KG' ? ' selected' : ''}>KG</option></select></td>
                <td><input class="right" name="lines[${index}][quantity]" data-feed-quantity type="number" min="0.0001" step="0.0001" value="${escapeHtml(quantity)}" required></td>
                <td><input class="right" name="lines[${index}][rate]" data-feed-rate type="number" min="0" step="0.01" value="${escapeHtml(rate.toFixed(2))}" required></td>
                <td class="right"><strong data-feed-line-total>${money(0)}</strong><small data-feed-base-qty></small></td>
                <td><button class="feed-icon-btn" type="button" data-feed-remove-line aria-label="Remove feed item">×</button></td>
            </tr>`;
    };

    const addLine = (values = {}) => {
        rowsContainer.insertAdjacentHTML('beforeend', lineMarkup(rowCounter++, values));
        const row = rowsContainer.lastElementChild;
        syncItemDetails(row, false);
        calculate();
    };

    const syncItemDetails = (row, resetRate = true) => {
        const itemSelect = row.querySelector('[data-feed-item]');
        const unitSelect = row.querySelector('[data-feed-unit]');
        const rateInput = row.querySelector('[data-feed-rate]');
        const item = itemsById.get(String(itemSelect?.value ?? ''));
        const unit = unitSelect?.value ?? 'BAG';
        const meta = row.querySelector('[data-feed-item-meta]');
        const batch = row.querySelector('[data-feed-batch]');
        const expiry = row.querySelector('[data-feed-expiry]');

        if (meta) {
            meta.textContent = item
                ? `${item.code} · ${numberValue(item.packSize).toFixed(2)} KG/bag${item.brand ? ` · ${item.brand}` : ''}`
                : '';
        }

        if (resetRate && rateInput && item) {
            rateInput.value = defaultRate(item, unit).toFixed(2);
        }

        if (batch) {
            batch.disabled = !item?.trackBatch;
            batch.placeholder = item?.trackBatch ? 'Batch no.' : 'Batch not tracked';
        }
        if (expiry) {
            expiry.disabled = !item?.trackExpiry;
        }

        updateAvailable(row);
    };

    const updateAvailable = (row) => {
        if (config.type !== 'sale') return;
        const itemId = row.querySelector('[data-feed-item]')?.value;
        const available = row.querySelector('[data-feed-available]');
        const stock = selectedStock(itemId);
        if (available) {
            available.textContent = `${stock.quantity.toFixed(4)} KG`;
        }
    };

    const summary = (key, value) => {
        root.querySelectorAll(`[data-feed-summary="${key}"]`).forEach((element) => {
            element.textContent = value;

            if (key === 'status' && element.matches('[data-feed-status]')) {
                element.classList.remove('feed-status-green', 'feed-status-amber', 'feed-status-red');
                if (value === 'Fully paid/received') {
                    element.classList.add('feed-status-green');
                } else if (value === 'Partially paid/received') {
                    element.classList.add('feed-status-amber');
                } else {
                    element.classList.add('feed-status-red');
                }
            }
        });
    };

    const syncTransactionHeadSummary = () => {
        const selected = transactionHeadSelect?.selectedOptions?.[0];
        const accountName = selected?.dataset?.accountName || 'Account not configured';
        const accountCode = selected?.dataset?.accountCode || '';
        summary('posting-account', accountCode ? `${accountCode} — ${accountName}` : accountName);
    };

    const calculate = () => {
        let subtotal = 0;
        let totalBaseQuantity = 0;
        let cogs = 0;
        let hasStockError = false;

        rowsContainer.querySelectorAll('[data-feed-line]').forEach((row) => {
            const itemId = row.querySelector('[data-feed-item]')?.value;
            const item = itemsById.get(String(itemId ?? ''));
            const unit = row.querySelector('[data-feed-unit]')?.value ?? 'BAG';
            const quantity = Math.max(0, numberValue(row.querySelector('[data-feed-quantity]')?.value));
            const rate = Math.max(0, numberValue(row.querySelector('[data-feed-rate]')?.value));
            const lineTotal = quantity * rate;
            const baseQuantity = unit === 'BAG' ? quantity * numberValue(item?.packSize) : quantity;
            const totalElement = row.querySelector('[data-feed-line-total]');
            const baseElement = row.querySelector('[data-feed-base-qty]');

            subtotal += lineTotal;
            totalBaseQuantity += baseQuantity;

            if (totalElement) totalElement.textContent = money(lineTotal);
            if (baseElement) baseElement.textContent = `${baseQuantity.toFixed(4)} KG`;

            if (config.type === 'sale') {
                const stock = selectedStock(itemId);
                cogs += baseQuantity * stock.averageCost;
                const availableElement = row.querySelector('[data-feed-available]');
                const insufficient = baseQuantity > stock.quantity + 0.00005;
                hasStockError ||= insufficient;
                row.classList.toggle('hg-feed-stock-error', insufficient);
                if (availableElement) {
                    availableElement.textContent = `${stock.quantity.toFixed(4)} KG${insufficient ? ' — insufficient' : ''}`;
                }
            }
        });

        const totalCommission = commissionAmount(subtotal, root.querySelector('#overall_discount')?.value);
        const transport = numberValue(root.querySelector('#transport_cost')?.value);
        const other = numberValue(root.querySelector('#other_cost')?.value);
        const extra = transport + other;
        let total = subtotal - totalCommission + extra;

        total = Math.max(0, total);
        const paid = Math.max(0, numberValue(paidInput?.value));
        const due = Math.max(0, total - paid);
        const status = paid <= 0.00001
            ? 'Fully due'
            : (paid >= total - 0.005 ? 'Fully paid/received' : 'Partially paid/received');

        summary('subtotal', money(subtotal));
        summary('commission', money(totalCommission));
        root.querySelectorAll('[data-feed-commission-output]').forEach((element) => {
            element.value = money(totalCommission);
        });
        summary('transport', money(transport));
        summary('other', money(other));
        summary('extra', money(extra));
        summary('total', money(total));
        root.querySelectorAll('[data-feed-calculated-total]').forEach((element) => {
            element.value = money(total);
        });
        summary('paid', money(paid));
        summary('due', money(due));
        summary('status', status);
        summary('quantity', `${totalBaseQuantity.toFixed(4)} KG`);
        summary('cogs', money(cogs));
        summary('profit', money(total - cogs));
        syncTransactionHeadSummary();

        const selectedMoneyText = moneyAccount?.selectedOptions?.[0]?.textContent?.trim();
        summary('money-name', moneyAccount?.value ? selectedMoneyText : 'Selected money account / Party due');

        if (form) {
            form.dataset.feedStockInvalid = hasStockError ? '1' : '0';
        }
    };

    const initialLines = Array.isArray(config.initialLines) && config.initialLines.length
        ? config.initialLines
        : [{}];
    initialLines.forEach((line) => addLine(line));

    addButton?.addEventListener('click', () => addLine({
        item_id: config.items[0]?.id ?? '',
        unit: 'BAG',
        quantity: 1,
        rate: config.type === 'purchase' ? config.items[0]?.purchasePrice : config.items[0]?.salePrice,
    }));

    rowsContainer.addEventListener('input', (event) => {
        if (event.target.matches('[data-feed-quantity], [data-feed-rate]')) {
            calculate();
        }
    });

    rowsContainer.addEventListener('change', (event) => {
        const row = event.target.closest('[data-feed-line]');
        if (!row) return;

        if (event.target.matches('[data-feed-item]')) {
            syncItemDetails(row, true);
        }
        if (event.target.matches('[data-feed-unit]')) {
            syncItemDetails(row, true);
        }
        calculate();
    });

    rowsContainer.addEventListener('click', (event) => {
        const button = event.target.closest('[data-feed-remove-line]');
        if (!button) return;
        const rows = rowsContainer.querySelectorAll('[data-feed-line]');
        if (rows.length <= 1) return;
        button.closest('[data-feed-line]')?.remove();
        calculate();
    });

    root.querySelectorAll('[data-feed-money-input]').forEach((input) => input.addEventListener('input', calculate));
    moneyAccount?.addEventListener('change', calculate);
    transactionHeadSelect?.addEventListener('change', calculate);
    warehouseSelect?.addEventListener('change', () => {
        rowsContainer.querySelectorAll('[data-feed-line]').forEach(updateAvailable);
        calculate();
    });

    form?.addEventListener('submit', (event) => {
        calculate();
        if (config.type === 'sale' && form.dataset.feedStockInvalid === '1') {
            event.preventDefault();
            window.alert('One or more sale lines are greater than the available stock in the selected warehouse.');
            return;
        }

        const totalText = root.querySelector('[data-feed-summary="total"]')?.textContent ?? '';
        if (numberValue(totalText.replaceAll(',', '').replace(config.currencyCode ?? '', '')) <= 0) {
            event.preventDefault();
            window.alert('The feed transaction total must be greater than zero.');
            return;
        }

        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.textContent = config.type === 'purchase' ? 'Posting Purchase…' : 'Posting Sale…';
        }
    });

    calculate();
};

document.addEventListener('DOMContentLoaded', initFeedModule);
