import './bootstrap';

window.AccountingUI = (() => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function showToast(message = 'Saved successfully.') {
    const toast = document.getElementById('toast');

    if (!toast) {
      alert(message);
      return;
    }

    toast.textContent = message;
    toast.classList.add('show');

    setTimeout(() => toast.classList.remove('show'), 2600);
  }

  async function getJson(url) {
    const response = await fetch(url, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Request failed: ${url}`);
    }

    return response.json();
  }

  function optionLabel(item, template) {
    if (template === 'currency') {
      return item.display_name || `${item.code || ''} - ${item.name || ''}`.trim();
    }

    if (template === 'timezone') {
      return item.display_name || `${item.utc_offset || ''} - ${item.name || ''}`.trim();
    }

    if (template === 'account') {
      return item.display_name || `${item.account_code || ''} - ${item.account_name || item.name || ''}`.trim();
    }

    if (template === 'bank') {
      return item.display_name || (item.short_name ? `${item.bank_name} (${item.short_name})` : item.bank_name);
    }

    return item.display_name || item.name || item.title || item.bank_name || item.code || `#${item.id}`;
  }

  function selectedValues(select) {
    const raw = String(select.dataset.selected || select.value || '');

    if (!raw) {
      return [];
    }

    try {
      const parsed = JSON.parse(raw);

      if (Array.isArray(parsed)) {
        return parsed.map(String);
      }
    } catch (error) {
      // Ignore JSON parse error and fallback to comma-separated values.
    }

    return raw.split(',').map((value) => value.trim()).filter(Boolean);
  }

  function setOptionDataAttributes(option, item) {
    Object.entries(item).forEach(([key, value]) => {
      if (value === null || value === undefined || typeof value === 'object') {
        return;
      }

      option.setAttribute(`data-${key.replace(/_/g, '-')}`, String(value));
    });
  }

  async function loadSelect(select) {
    const url = select.dataset.dropdown;

    if (!url) {
      return;
    }

    const placeholder = select.dataset.placeholder || 'Select';
    const template = select.dataset.label || 'name';
    const selected = selectedValues(select);
    const isMultiple = select.multiple;

    select.innerHTML = '';

    if (!isMultiple) {
      select.innerHTML = `<option value="">${placeholder}</option>`;
    }

    select.disabled = true;

    try {
      const result = await getJson(url);
      const rows = Array.isArray(result.data) ? result.data : [];

      if (isMultiple && rows.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No options found';
        option.disabled = true;
        select.appendChild(option);
      }

      rows.forEach((item) => {
        const option = document.createElement('option');

        option.value = item.id;
        option.textContent = optionLabel(item, template);

        setOptionDataAttributes(option, item);

        if (selected.includes(String(item.id))) {
          option.selected = true;
        }

        select.appendChild(option);
      });

      select.disabled = false;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (error) {
      console.error(error);

      select.innerHTML = `<option value="">Backend API not ready</option>`;
      select.disabled = false;
    }
  }

  function loadAllDropdowns(scope = document) {
    scope.querySelectorAll('select[data-dropdown]').forEach((select) => {
      loadSelect(select);
    });
  }

  function bindParentAccountDropdown() {
    const accountType = document.querySelector('[data-controls-parent-account]');
    const parent = document.querySelector('[data-parent-account-select]');

    if (!accountType || !parent) {
      return;
    }

    const reloadParent = () => {
      const baseUrl = parent.dataset.baseUrl || '/api/dropdowns/parent-accounts';
      const excludeId = parent.dataset.excludeId || '';
      const selectedValue = parent.dataset.selected || '';

      const params = new URLSearchParams();

      if (accountType.value) {
        params.set('account_type_id', accountType.value);
      }

      if (excludeId) {
        params.set('exclude_id', excludeId);
      }

      parent.dataset.dropdown = params.toString()
        ? `${baseUrl}?${params.toString()}`
        : baseUrl;

      parent.dataset.selected = selectedValue;

      loadSelect(parent);
    };

    accountType.addEventListener('change', reloadParent);
  }

  function bindBankTypeFields() {
    const type = document.getElementById('cashBankType');
    const bankFields = document.getElementById('bankFields');
    const bankSelect = document.getElementById('bankId');

    if (!type || !bankFields) {
      return;
    }

    const toggle = () => {
      const showBankFields = type.value === 'Bank' || type.value === 'Mobile Banking';
      const bankRequired = type.value === 'Bank';

      bankFields.classList.toggle('hidden', !showBankFields);

      if (bankSelect) {
        bankSelect.required = bankRequired;

        if (!showBankFields) {
          bankSelect.value = '';
        }
      }

      bankFields.querySelectorAll('.bank-required').forEach((element) => {
        element.style.display = bankRequired ? '' : 'none';
      });
    };

    type.addEventListener('change', toggle);
    toggle();
  }

  function bindSwitches(scope = document) {
    scope.querySelectorAll('.switch[data-input]').forEach((switchEl) => {
      const input = document.getElementById(switchEl.dataset.input);

      const sync = () => {
        if (input) {
          input.value = switchEl.classList.contains('on') ? '1' : '0';
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      };

      switchEl.addEventListener('click', () => {
        if (switchEl.dataset.locked === 'true') {
          sync();
          return;
        }

        switchEl.classList.toggle('on');
        sync();
      });

      sync();
    });
  }

  function bindMultiSelectChips(scope = document) {
    scope.querySelectorAll('[data-multi-select]').forEach((box) => {
      const input = document.getElementById(box.dataset.input);

      const sync = () => {
        const values = Array.from(box.querySelectorAll('.select-chip.selected')).map((chip) => {
          return chip.dataset.value || chip.textContent.trim();
        });

        if (input) {
          input.value = JSON.stringify(values);
        }

        box.dataset.selectedCount = String(values.length);
      };

      box.querySelectorAll('.select-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          chip.classList.toggle('selected');
          sync();
        });
      });

      sync();
    });
  }

  function validateCustomFields(form) {
    const requiredMultiSelect = form.querySelector('[data-multi-select][data-required="true"]');

    if (requiredMultiSelect) {
      const selectedCount = Number(requiredMultiSelect.dataset.selectedCount || 0);

      if (selectedCount <= 0) {
        const firstChip = requiredMultiSelect.querySelector('.select-chip');

        requiredMultiSelect.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        });

        if (firstChip) {
          firstChip.focus?.({ preventScroll: true });
        }

        showToast('Select at least one settlement type.');
        return false;
      }
    }

    return true;
  }

  function bindTableFilters(scope = document) {
    scope.querySelectorAll('[data-table-filter]').forEach((filterRoot) => {
      const tableSelector = filterRoot.dataset.tableFilter;
      const table = document.querySelector(tableSelector);

      if (!table) {
        return;
      }

      const controls = filterRoot.querySelectorAll('[data-filter-key]');
      const countTarget = document.querySelector(filterRoot.dataset.countTarget || '#resultCount');

      const apply = () => {
        let visible = 0;
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach((row) => {
          if (row.dataset.empty === 'true') {
            row.style.display = rows.length === 1 ? '' : 'none';
            return;
          }

          let show = true;

          controls.forEach((control) => {
            const key = control.dataset.filterKey;
            const value = String(control.value || '').toLowerCase().trim();

            if (!value || value.startsWith('all')) {
              return;
            }

            if (key === 'text') {
              show = show && row.innerText.toLowerCase().includes(value);
            } else if (key === 'role') {
              show = show && String(row.dataset[key] || '').toLowerCase().includes(value);
            } else {
              show = show && String(row.dataset[key] || '').toLowerCase() === value;
            }
          });

          row.style.display = show ? '' : 'none';

          if (show) {
            visible++;
          }
        });

        if (countTarget) {
          countTarget.textContent = `Showing ${visible} of ${rows.length} entries`;
        }

        refreshClientTablePagination(table, true);
      };

      controls.forEach((control) => {
        control.addEventListener('input', apply);
        control.addEventListener('change', apply);
      });

      apply();
    });
  }


  function tableDataRows(table) {
    return Array.from(table.querySelectorAll('tbody tr')).filter((row) => row.dataset.empty !== 'true');
  }

  function tableShouldPaginate(table) {
    if (!table || table.dataset.clientPagination === 'false' || table.dataset.noClientPagination === 'true') {
      return false;
    }

    if (table.closest('[data-no-client-pagination], .no-client-pagination, .transaction-entry-page')) {
      return false;
    }

    if (
      table.classList.contains('financial-table')
      || table.classList.contains('ledger-table')
      || table.classList.contains('audit-table')
      || table.closest('.financial-report-page')
      || table.closest('.report-page')
      || table.closest('.audit-income-page')
    ) {
      return false;
    }

    const isExplicit = table.dataset.clientPagination === 'true' || table.hasAttribute('data-page-size');
    const isDataCardTable = Boolean(table.closest('.table-card'));

    if (!isExplicit && !isDataCardTable) {
      return false;
    }

    const pageSize = Math.max(1, Number(table.dataset.pageSize || 15));

    return tableDataRows(table).length > pageSize;
  }

  function getOrCreatePaginationFooter(table) {
    const card = table.closest('.table-card') || table.parentElement;

    if (!card) {
      return null;
    }

    let footer = card.querySelector(':scope > .table-footer[data-client-pagination-footer]')
      || card.querySelector(':scope > .table-footer');

    if (!footer) {
      footer = document.createElement('div');
      footer.className = 'table-footer';
      card.appendChild(footer);
    }

    footer.dataset.clientPaginationFooter = 'true';

    let info = footer.querySelector('[data-client-pagination-info]');

    if (!info) {
      info = footer.querySelector('#resultCount') || footer.querySelector('[data-pagination-info]') || document.createElement('span');
      info.dataset.clientPaginationInfo = 'true';

      if (!info.parentElement) {
        footer.appendChild(info);
      }
    }

    let controls = footer.querySelector('[data-client-pagination-controls]') || footer.querySelector('.pagination');

    if (!controls) {
      controls = document.createElement('div');
      controls.className = 'pagination';
      footer.appendChild(controls);
    }

    controls.dataset.clientPaginationControls = 'true';
    controls.classList.add('table-client-pagination');

    return { footer, info, controls };
  }

  function pageWindow(currentPage, pageCount) {
    if (pageCount <= 7) {
      return Array.from({ length: pageCount }, (_, index) => index + 1);
    }

    const pages = new Set([1, pageCount, currentPage]);

    for (let offset = -1; offset <= 1; offset++) {
      const page = currentPage + offset;

      if (page > 1 && page < pageCount) {
        pages.add(page);
      }
    }

    return Array.from(pages).sort((a, b) => a - b);
  }

  function renderPaginationButtons(table, controls, currentPage, pageCount) {
    controls.innerHTML = '';

    const makeButton = (label, page, options = {}) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = options.pageNumber ? 'page-btn' : 'button btn-soft table-page-action';
      button.textContent = label;
      button.disabled = Boolean(options.disabled);

      if (options.active) {
        button.classList.add('active');
        button.setAttribute('aria-current', 'page');
      }

      button.addEventListener('click', () => {
        if (button.disabled) {
          return;
        }

        table.dataset.currentPage = String(page);
        refreshClientTablePagination(table, false);
      });

      return button;
    };

    controls.appendChild(makeButton('Prev', Math.max(1, currentPage - 1), { disabled: currentPage <= 1 }));

    let previous = 0;
    pageWindow(currentPage, pageCount).forEach((page) => {
      if (previous && page - previous > 1) {
        const dots = document.createElement('span');
        dots.className = 'table-page-ellipsis';
        dots.textContent = '…';
        controls.appendChild(dots);
      }

      controls.appendChild(makeButton(String(page), page, { active: page === currentPage, pageNumber: true }));
      previous = page;
    });

    controls.appendChild(makeButton('Next', Math.min(pageCount, currentPage + 1), { disabled: currentPage >= pageCount }));
  }

  function refreshClientTablePagination(table, resetPage = false) {
    if (!tableShouldPaginate(table)) {
      tableDataRows(table).forEach((row) => {
        row.hidden = false;
      });

      return;
    }

    const pageSize = Math.max(1, Number(table.dataset.pageSize || 15));
    const rows = tableDataRows(table);
    const visibleRows = rows.filter((row) => row.style.display !== 'none');
    const pageCount = Math.max(1, Math.ceil(visibleRows.length / pageSize));
    const requestedPage = resetPage ? 1 : Number(table.dataset.currentPage || 1);
    const currentPage = Math.min(Math.max(1, Number.isFinite(requestedPage) ? requestedPage : 1), pageCount);
    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    const visiblePageRows = new Set(visibleRows.slice(start, end));
    const parts = getOrCreatePaginationFooter(table);

    table.dataset.clientPagination = 'true';
    table.dataset.paginationReady = '1';
    table.dataset.currentPage = String(currentPage);

    rows.forEach((row) => {
      row.hidden = row.style.display === 'none' || !visiblePageRows.has(row);
    });

    if (!parts) {
      return;
    }

    if (parts.info) {
      const total = visibleRows.length;
      const from = total === 0 ? 0 : start + 1;
      const to = Math.min(end, total);
      parts.info.textContent = `Showing ${from}-${to} of ${total} entries`;
    }

    if (parts.controls) {
      parts.controls.hidden = pageCount <= 1;
      renderPaginationButtons(table, parts.controls, currentPage, pageCount);
    }
  }

  function bindClientTablePagination(scope = document) {
    scope.querySelectorAll('table').forEach((table) => {
      if (!tableShouldPaginate(table)) {
        return;
      }

      refreshClientTablePagination(table, true);
    });
  }

  function firstValidationMessage(errors) {
    if (!errors || typeof errors !== 'object') {
      return null;
    }

    const first = Object.values(errors)[0];

    if (Array.isArray(first)) {
      return first[0];
    }

    return first || null;
  }

  function firstValidationField(errors) {
    if (!errors || typeof errors !== 'object') {
      return null;
    }

    return Object.keys(errors)[0] || null;
  }

  function normalizeFieldName(fieldName) {
    return String(fieldName || '')
      .replace(/\.\d+$/, '')
      .replace(/\[\]$/, '');
  }

  function findField(form, fieldName) {
    if (!fieldName) {
      return null;
    }

    const normalized = normalizeFieldName(fieldName);

    return form.querySelector(`[name="${fieldName}"]`)
      || form.querySelector(`[name="${normalized}"]`)
      || form.querySelector(`[name="${normalized}[]"]`);
  }

  function focusCustomField(form, fieldName) {
    const normalized = normalizeFieldName(fieldName);

    if (normalized === 'settlement_type_ids' || normalized === 'allowed_settlement_types') {
      const box = form.querySelector('[data-multi-select]');

      if (box) {
        box.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        });

        const firstChip = box.querySelector('.select-chip');

        if (firstChip) {
          firstChip.focus?.({ preventScroll: true });
        }

        return true;
      }
    }

    return false;
  }

  function clearInvalidField(form, fieldName) {
    if (focusCustomField(form, fieldName)) {
      return;
    }

    const field = findField(form, fieldName);

    if (!field) {
      return;
    }

    const normalized = normalizeFieldName(fieldName);

    const clearableFields = [
      'account_code',
      'account_name',
      'cash_bank_name',
      'account_number',
      'party_name',
      'mobile',
      'email',
      'name',
      'transaction_head_name',
    ];

    if (clearableFields.includes(normalized)) {
      field.value = '';
    }

    field.focus({ preventScroll: true });
  }

  function setSubmitting(form, isSubmitting) {
    const submitButtons = form.querySelectorAll('button[type="submit"]');

    submitButtons.forEach((button) => {
      button.disabled = isSubmitting;
      button.dataset.originalText = button.dataset.originalText || button.textContent;
      button.textContent = isSubmitting ? 'Saving...' : button.dataset.originalText;
    });
  }

  function bindFrontendForms(scope = document) {
    scope.querySelectorAll('form[data-frontend-form]').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }

        if (!validateCustomFields(form)) {
          return;
        }

        const action = form.dataset.action || form.getAttribute('action');
        const method = form.dataset.method || form.getAttribute('method') || 'POST';
        const success = form.dataset.success || 'Saved successfully.';

        if (!action || action === '#') {
          showToast(success);
          return;
        }

        setSubmitting(form, true);

        try {
          const formData = new FormData(form);

          const response = await fetch(action, {
            method: method.toUpperCase(),
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': csrf(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
          });

          const result = await response.json().catch(() => ({}));

          if (!response.ok) {
            const fieldName = firstValidationField(result.errors);
            const message = firstValidationMessage(result.errors) || result.message || 'Please check validation errors.';

            clearInvalidField(form, fieldName);
            showToast(message);
            setSubmitting(form, false);

            return;
          }

          showToast(result.message || success);

          if (result.redirect) {
            setTimeout(() => {
              window.location.href = result.redirect;
            }, 700);
          } else {
            setSubmitting(form, false);
          }
        } catch (error) {
          console.error(error);
          showToast('Backend save API not ready yet. Frontend form is working.');
          setSubmitting(form, false);
        }
      });
    });
  }


  function bindSidebarNavigation() {
    const app = document.getElementById('appShell') || document.querySelector('.app');
    const sidebar = document.getElementById('appSidebar') || document.querySelector('.sidebar');
    const menuButton = document.querySelector('.menu-button');
    const mobileQuery = window.matchMedia('(max-width: 880px)');
    const scrollStorageKey = 'accounting-sidebar-scroll-top';

    if (!app || !sidebar || !menuButton) {
      return;
    }

    const isMobile = () => mobileQuery.matches;

    const setExpandedState = () => {
      const expanded = isMobile()
        ? app.classList.contains('sidebar-open')
        : !app.classList.contains('sidebar-collapsed');

      menuButton.setAttribute('aria-expanded', String(expanded));
    };

    const saveSidebarScroll = () => {
      sessionStorage.setItem(scrollStorageKey, String(sidebar.scrollTop || 0));
    };

    const ensureActiveMenuVisible = () => {
      const activeItem = sidebar.querySelector('.nav-subitem.active, .nav-item.active');

      if (!activeItem || app.classList.contains('sidebar-collapsed')) {
        return;
      }

      const sidebarRect = sidebar.getBoundingClientRect();
      const activeRect = activeItem.getBoundingClientRect();
      const buffer = 70;
      const isAboveView = activeRect.top < sidebarRect.top + buffer;
      const isBelowView = activeRect.bottom > sidebarRect.bottom - buffer;

      if (isAboveView || isBelowView) {
        activeItem.scrollIntoView({ block: 'center', behavior: 'auto' });
      }
    };

    const restoreSidebarScroll = () => {
      const savedScrollTop = Number(sessionStorage.getItem(scrollStorageKey) || 0);
      const hasSavedScroll = Number.isFinite(savedScrollTop) && savedScrollTop > 0;

      if (hasSavedScroll) {
        sidebar.scrollTop = savedScrollTop;
      }

      window.requestAnimationFrame(() => {
        if (!hasSavedScroll) {
          ensureActiveMenuVisible();
        }

        saveSidebarScroll();
      });
    };

    const closeMobileSidebar = () => {
      saveSidebarScroll();
      app.classList.remove('sidebar-open');
      document.body.classList.remove('sidebar-open');
      setExpandedState();
    };

    const savedCollapsed = localStorage.getItem('accounting-sidebar-collapsed') === '1';

    if (savedCollapsed && !isMobile()) {
      app.classList.add('sidebar-collapsed');
    } else {
      app.classList.remove('sidebar-collapsed');
    }

    sidebar.querySelectorAll('details.nav-group').forEach((group) => {
      const summary = group.querySelector('summary');

      if (!summary) {
        return;
      }

      summary.setAttribute('aria-expanded', String(group.open));

      summary.addEventListener('click', () => {
        if (app.classList.contains('sidebar-collapsed') && !isMobile()) {
          app.classList.remove('sidebar-collapsed');
          localStorage.setItem('accounting-sidebar-collapsed', '0');
          setExpandedState();
        }

        window.requestAnimationFrame(() => {
          summary.setAttribute('aria-expanded', String(group.open));
          saveSidebarScroll();
        });
      });

      group.addEventListener('toggle', () => {
        summary.setAttribute('aria-expanded', String(group.open));
        saveSidebarScroll();
      });
    });

    sidebar.querySelectorAll('[data-sidebar-submenu-toggle]').forEach((button) => {
      const targetId = button.dataset.sidebarSubmenuToggle;
      const submenu = targetId ? document.getElementById(targetId) : null;

      if (!submenu) {
        return;
      }

      button.addEventListener('click', () => {
        if (app.classList.contains('sidebar-collapsed') && !isMobile()) {
          app.classList.remove('sidebar-collapsed');
          localStorage.setItem('accounting-sidebar-collapsed', '0');
          setExpandedState();
        }

        const willOpen = !submenu.classList.contains('is-open');

        submenu.classList.toggle('is-open', willOpen);
        button.setAttribute('aria-expanded', String(willOpen));
        saveSidebarScroll();
      });
    });

    let scrollSaveTimer = null;
    sidebar.addEventListener('scroll', () => {
      if (scrollSaveTimer) {
        window.clearTimeout(scrollSaveTimer);
      }

      scrollSaveTimer = window.setTimeout(saveSidebarScroll, 80);
    }, { passive: true });

    menuButton.addEventListener('click', () => {
      if (isMobile()) {
        const willOpen = !app.classList.contains('sidebar-open');
        app.classList.toggle('sidebar-open', willOpen);
        document.body.classList.toggle('sidebar-open', willOpen);

        if (willOpen) {
          window.requestAnimationFrame(restoreSidebarScroll);
        }
      } else {
        const willCollapse = !app.classList.contains('sidebar-collapsed');
        app.classList.toggle('sidebar-collapsed', willCollapse);
        localStorage.setItem('accounting-sidebar-collapsed', willCollapse ? '1' : '0');

        if (!willCollapse) {
          window.requestAnimationFrame(restoreSidebarScroll);
        }
      }

      setExpandedState();
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((element) => {
      element.addEventListener('click', closeMobileSidebar);
    });

    sidebar.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        saveSidebarScroll();

        const href = link.getAttribute('href') || '';
        const isRealNavigation = href
          && href !== '#'
          && !href.startsWith('javascript:')
          && link.target !== '_blank';

        if (isRealNavigation) {
          document.body.classList.add('sidebar-navigating');
          return;
        }

        if (isMobile()) {
          closeMobileSidebar();
        }
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && isMobile()) {
        closeMobileSidebar();
      }
    });

    mobileQuery.addEventListener?.('change', () => {
      if (!isMobile()) {
        app.classList.remove('sidebar-open');
        document.body.classList.remove('sidebar-open');
      }

      setExpandedState();
      window.requestAnimationFrame(restoreSidebarScroll);
    });

    restoreSidebarScroll();
    setExpandedState();

    window.requestAnimationFrame(() => {
      document.body.classList.remove('sidebar-booting');
    });
  }

  function bindCompanySetupViewMode(scope = document) {
    scope.querySelectorAll('form[data-company-setup-form]').forEach((form) => {
      if (form.dataset.companyViewReady === '1') {
        return;
      }

      form.dataset.companyViewReady = '1';

      const isCompleted = form.dataset.companyCompleted === '1';
      const fields = form.querySelector('[data-company-fields]');
      const editActions = form.querySelector('[data-company-edit-actions]');
      const viewActions = form.querySelector('[data-company-view-actions]');
      const banner = form.querySelector('[data-company-readonly-banner]');
      const editTriggers = document.querySelectorAll('[data-company-edit-trigger]');
      const cancelButton = form.querySelector('[data-company-cancel-edit]');

      if (!fields) {
        return;
      }

      const setMode = (isEditing) => {
        const readonly = isCompleted && !isEditing;

        fields.disabled = readonly;
        form.classList.toggle('is-company-readonly', readonly);
        form.classList.toggle('is-company-editing', !readonly);

        if (editActions) {
          editActions.hidden = readonly;
        }

        if (viewActions) {
          viewActions.hidden = !readonly;
        }

        if (banner) {
          banner.hidden = !readonly;
        }

        editTriggers.forEach((button) => {
          button.hidden = !readonly;
          button.setAttribute('aria-expanded', String(!readonly));
        });

        if (!readonly) {
          window.requestAnimationFrame(() => {
            const firstField = form.querySelector('[name="company_name"]')
              || form.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');

            firstField?.focus?.({ preventScroll: true });
          });
        }
      };

      if (!isCompleted) {
        setMode(true);
        return;
      }

      editTriggers.forEach((button) => {
        if (button.dataset.companyEditBound === '1') {
          return;
        }

        button.dataset.companyEditBound = '1';
        button.addEventListener('click', () => {
          setMode(true);
          showToast('Company setup is now editable.');
        });
      });

      cancelButton?.addEventListener('click', () => {
        form.reset();

        form.querySelectorAll('select[data-dropdown]').forEach((select) => {
          const selected = selectedValues(select);

          Array.from(select.options).forEach((option) => {
            option.selected = selected.includes(String(option.value));
          });
        });

        setMode(false);
        showToast('Edit cancelled. Company setup is back in view mode.');
      });

      setMode(false);
    });
  }

  function bindButtons() {
    document.querySelectorAll('[data-toast]').forEach((button) => {
      button.addEventListener('click', () => {
        showToast(button.dataset.toast);
      });
    });
  }

  function init() {
    bindTableFilters();
    bindClientTablePagination();
    bindParentAccountDropdown();
    bindBankTypeFields();
    bindSwitches();
    bindMultiSelectChips();
    bindFrontendForms();
    bindSidebarNavigation();
    bindCompanySetupViewMode();
    bindButtons();
    loadAllDropdowns();
  }

  document.addEventListener('DOMContentLoaded', init);

  return {
    showToast,
    loadSelect,
    loadAllDropdowns,
    refreshTablePagination: refreshClientTablePagination,
    bindClientTablePagination,
    init,
  };
})();
