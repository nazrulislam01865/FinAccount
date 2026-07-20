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
    const paymentsContainer = root.querySelector('[data-feed-payments]');
    const addPaymentButton = root.querySelector('[data-feed-add-payment]');
    const paymentJournal = root.querySelector('[data-feed-payment-journal]');
    const paymentTotalOutput = root.querySelector('[data-feed-payment-total]');
    const primaryMoneyInput = root.querySelector('#money_account_id');
    const transactionHeadSelect = root.querySelector('[data-feed-transaction-head]');
    const paidInput = root.querySelector('#paid_amount');
    const itemsById = new Map(config.items.map((item) => [String(item.id), item]));
    const moneyAccountsById = new Map((config.moneyAccounts ?? []).map((account) => [String(account.id), account]));
    let rowCounter = 0;
    let paymentCounter = 0;

    const money = (value) => {
        const places = Number.isInteger(config.decimalPlaces) ? config.decimalPlaces : 2;
        return `${config.currencyCode ?? ''} ${numberValue(value).toLocaleString(undefined, {
            minimumFractionDigits: places,
            maximumFractionDigits: places,
        })}`.trim();
    };

    const deductionMoney = (value) => `(-) ${money(value)}`;

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

    const paymentOptions = (selectedId) => {
        const empty = `<option value="">Select ${config.type === 'purchase' ? 'payment' : 'receive'} account</option>`;
        return empty + (config.moneyAccounts ?? []).map((account) => {
            const selected = String(account.id) === String(selectedId ?? '') ? ' selected' : '';
            return `<option value="${escapeHtml(account.id)}"${selected}>${escapeHtml(account.name)} — ${escapeHtml(account.kind)}</option>`;
        }).join('');
    };

    const paymentAccountMeta = (account) => account?.meta
        || [account?.accountCode, account?.name].filter(Boolean).join(' — ');

    const paymentMarkup = (index, values = {}) => `
        <div class="feed-payment-row" data-feed-payment-row>
            <div class="feed-field">
                <label>${config.type === 'purchase' ? 'Payment Account' : 'Receive Account'}</label>
                <select name="payments[${index}][money_account_id]" data-feed-payment-account data-hg-searchable-ignore>
                    ${paymentOptions(values.money_account_id)}
                </select>
                <small data-feed-payment-account-meta>${escapeHtml(paymentAccountMeta(moneyAccountsById.get(String(values.money_account_id ?? ''))))}</small>
            </div>
            <div class="feed-field">
                <label>Reference</label>
                <input name="payments[${index}][reference]" data-feed-payment-reference maxlength="100" value="${escapeHtml(values.reference ?? '')}" placeholder="CHQ / TXN / note">
            </div>
            <div class="feed-field">
                <label>Amount (${escapeHtml(config.currencyCode ?? '')})</label>
                <input name="payments[${index}][amount]" data-feed-payment-amount type="number" min="0" step="${Number(config.decimalPlaces) > 0 ? '0.01' : '1'}" value="${escapeHtml(values.amount ?? '')}" placeholder="0.00">
            </div>
            <button class="feed-icon-btn" type="button" data-feed-remove-payment aria-label="Remove payment method">×</button>
        </div>`;

    const paymentIndexesInDom = () => Array.from(paymentsContainer?.querySelectorAll('[data-feed-payment-account]') ?? [])
        .map((select) => String(select.getAttribute('name') ?? '').match(/^payments\[(\d+)\]\[money_account_id\]$/)?.[1])
        .filter((index) => index !== undefined)
        .map((index) => Number.parseInt(index, 10))
        .filter((index) => Number.isInteger(index));

    const syncPaymentCounterFromDom = () => {
        const indexes = paymentIndexesInDom();
        paymentCounter = indexes.length ? Math.max(...indexes) + 1 : paymentCounter;
    };

    const updatePaymentControls = () => {
        const rows = Array.from(paymentsContainer?.querySelectorAll('[data-feed-payment-row]') ?? []);
        rows.forEach((row) => {
            const button = row.querySelector('[data-feed-remove-payment]');
            if (!button) return;
            button.hidden = rows.length <= 1;
            button.disabled = rows.length <= 1;
        });
        if (addPaymentButton) {
            addPaymentButton.disabled = rows.length >= 10;
        }
    };

    const addPayment = (values = {}) => {
        if (!paymentsContainer || paymentsContainer.querySelectorAll('[data-feed-payment-row]').length >= 10) return;
        paymentsContainer.insertAdjacentHTML('beforeend', paymentMarkup(paymentCounter++, values));
        updatePaymentControls();
    };

    const paymentRows = () => Array.from(paymentsContainer?.querySelectorAll('[data-feed-payment-row]') ?? []).map((row) => {
        const accountId = String(row.querySelector('[data-feed-payment-account]')?.value ?? '');
        const amount = Math.max(0, numberValue(row.querySelector('[data-feed-payment-amount]')?.value));
        const reference = String(row.querySelector('[data-feed-payment-reference]')?.value ?? '').trim();
        return { row, accountId, account: moneyAccountsById.get(accountId), reference, amount };
    });

    const syncPaymentSummary = () => {
        const rows = paymentRows();
        const active = rows.filter((payment) => payment.amount > 0);
        const total = active.reduce((sum, payment) => sum + payment.amount, 0);
        const accountIds = active.map((payment) => payment.accountId).filter(Boolean);
        const duplicates = accountIds.length !== new Set(accountIds).size;

        const incomplete = rows.some((payment) => ((payment.amount > 0 || payment.reference) && !payment.accountId)
            || ((payment.accountId || payment.reference) && payment.amount <= 0));
        rows.forEach((payment) => payment.row.classList.toggle(
            'hg-feed-payment-error',
            ((payment.amount > 0 || payment.reference) && !payment.accountId)
                || ((payment.accountId || payment.reference) && payment.amount <= 0),
        ));

        if (paidInput) paidInput.value = total.toFixed(Number.isInteger(config.decimalPlaces) ? config.decimalPlaces : 2);
        if (primaryMoneyInput) primaryMoneyInput.value = active[0]?.accountId ?? '';
        if (paymentTotalOutput) paymentTotalOutput.textContent = money(total);

        if (paymentJournal) {
            const debitPayment = config.type === 'sale';
            const journalRows = active.length ? active : [{ account: null, amount: 0 }];
            paymentJournal.innerHTML = journalRows.map((payment) => `
                <div class="feed-journal-row">
                    <span>${escapeHtml(payment.account?.name ?? 'Cash / Bank / Digital')}</span>
                    <span>${debitPayment ? money(payment.amount) : ''}</span>
                    <span>${debitPayment ? '' : money(payment.amount)}</span>
                </div>`).join('');
        }

        if (form) {
            form.dataset.feedPaymentDuplicate = duplicates ? '1' : '0';
            form.dataset.feedPaymentIncomplete = incomplete ? '1' : '0';
        }

        return total;
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
        const transportEffect = config.type === 'purchase' ? -transport : transport;
        const extra = transportEffect + other;
        let total = subtotal - totalCommission + extra;

        total = Math.max(0, total);
        const paid = syncPaymentSummary();
        const due = Math.max(0, total - paid);
        const status = paid <= 0.00001
            ? 'Fully due'
            : (paid >= total - 0.005 ? 'Fully paid/received' : 'Partially paid/received');

        summary('subtotal', money(subtotal));
        const formattedCommission = config.type === 'purchase' ? deductionMoney(totalCommission) : money(totalCommission);
        const formattedTransport = config.type === 'purchase' ? deductionMoney(transport) : money(transport);

        summary('commission', formattedCommission);
        root.querySelectorAll('[data-feed-commission-output]').forEach((element) => {
            element.value = formattedCommission;
        });
        summary('transport', formattedTransport);
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

        if (form) {
            form.dataset.feedStockInvalid = hasStockError ? '1' : '0';
            form.dataset.feedPaymentInvalid = paid > total + 0.005 ? '1' : '0';
        }
        paymentTotalOutput?.classList.toggle('feed-negative', paid > total + 0.005);
    };

    if (paymentsContainer?.querySelector('[data-feed-payment-row]')) {
        syncPaymentCounterFromDom();
        updatePaymentControls();
    } else {
        const initialPayments = Array.isArray(config.initialPayments) && config.initialPayments.length
            ? config.initialPayments
            : [{}];
        initialPayments.slice(0, 10).forEach((payment) => addPayment(payment));
    }

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

    addPaymentButton?.addEventListener('click', () => {
        addPayment();
        calculate();
    });

    paymentsContainer?.addEventListener('input', (event) => {
        if (event.target.matches('[data-feed-payment-amount], [data-feed-payment-reference]')) calculate();
    });

    paymentsContainer?.addEventListener('change', (event) => {
        if (event.target.matches('[data-feed-payment-account]')) {
            const row = event.target.closest('[data-feed-payment-row]');
            const account = moneyAccountsById.get(String(event.target.value ?? ''));
            const meta = row?.querySelector('[data-feed-payment-account-meta]');
            if (meta) {
                meta.textContent = paymentAccountMeta(account);
            }
            calculate();
        }
    });

    paymentsContainer?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-feed-remove-payment]');
        if (!button) return;
        if ((paymentsContainer?.querySelectorAll('[data-feed-payment-row]').length ?? 0) <= 1) return;
        button.closest('[data-feed-payment-row]')?.remove();
        updatePaymentControls();
        calculate();
    });

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

        if (form.dataset.feedPaymentInvalid === '1') {
            event.preventDefault();
            window.alert(`Total ${config.type === 'purchase' ? 'paid' : 'received'} cannot be greater than the transaction total.`);
            return;
        }

        if (form.dataset.feedPaymentDuplicate === '1') {
            event.preventDefault();
            window.alert(`Use each ${config.type === 'purchase' ? 'payment' : 'receive'} account only once.`);
            return;
        }

        if (form.dataset.feedPaymentIncomplete === '1') {
            event.preventDefault();
            window.alert(`Complete or remove each ${config.type === 'purchase' ? 'payment' : 'receive'} row.`);
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
