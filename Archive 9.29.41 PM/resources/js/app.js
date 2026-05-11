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

  async function loadSelect(select) {
    const url = select.dataset.dropdown;

    if (!url) {
      return;
    }

    const placeholder = select.dataset.placeholder || 'Select';
    const template = select.dataset.label || 'name';
    const selectedValue = String(select.dataset.selected || select.value || '');

    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;

    try {
      const result = await getJson(url);
      const rows = Array.isArray(result.data) ? result.data : [];

      rows.forEach((item) => {
        const option = document.createElement('option');

        option.value = item.id;
        option.textContent = optionLabel(item, template);

        if (String(item.id) === selectedValue) {
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
        }
      };

      switchEl.addEventListener('click', () => {
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
      };

      controls.forEach((control) => {
        control.addEventListener('input', apply);
        control.addEventListener('change', apply);
      });

      apply();
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

  function clearInvalidField(form, fieldName) {
    if (!fieldName) {
      return;
    }

    const field = form.querySelector(`[name="${fieldName}"]`);

    if (!field) {
      return;
    }

    const clearableFields = [
      'account_code',
      'account_name',
      'cash_bank_name',
      'account_number',
      'party_name',
      'mobile',
      'email',
    ];

    if (clearableFields.includes(fieldName)) {
      field.value = '';
      field.focus();
    }
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

  function bindButtons() {
    document.querySelectorAll('[data-toast]').forEach((button) => {
      button.addEventListener('click', () => {
        showToast(button.dataset.toast);
      });
    });
  }

  function init() {
    bindTableFilters();
    bindParentAccountDropdown();
    bindBankTypeFields();
    bindSwitches();
    bindMultiSelectChips();
    bindFrontendForms();
    bindButtons();
    loadAllDropdowns();
  }

  document.addEventListener('DOMContentLoaded', init);

  return {
    showToast,
    loadSelect,
    loadAllDropdowns,
    init,
  };
})();
