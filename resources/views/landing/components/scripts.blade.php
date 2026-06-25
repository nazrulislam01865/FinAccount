<script>
(function () {
  const bnBtn = document.getElementById('bnBtn');
  const enBtn = document.getElementById('enBtn');
  const messages = {
    success: {
      bn: @json($txt(data_get($landing, 'contact.form.success'), 'bn')),
      en: @json($txt(data_get($landing, 'contact.form.success'), 'en'))
    },
    error: {
      bn: @json($txt(data_get($landing, 'contact.form.error'), 'bn')),
      en: @json($txt(data_get($landing, 'contact.form.error'), 'en'))
    },
    captchaLoading: {
      bn: @json($txt(data_get($landing, 'contact.captcha.loading_message'), 'bn')),
      en: @json($txt(data_get($landing, 'contact.captcha.loading_message'), 'en'))
    },
    captchaInvalid: {
      bn: @json($txt(data_get($landing, 'contact.captcha.invalid_message'), 'bn')),
      en: @json($txt(data_get($landing, 'contact.captcha.invalid_message'), 'en'))
    }
  };

  function currentLang() {
    return document.documentElement.lang === 'en' ? 'en' : 'bn';
  }

  function translated(group) {
    const lang = currentLang();
    return messages[group]?.[lang] || '';
  }

  function setLang(lang) {
    document.documentElement.lang = lang === 'bn' ? 'bn' : 'en';

    document.querySelectorAll('[data-bn][data-en]').forEach((el) => {
      el.textContent = el.getAttribute(`data-${lang}`) || el.textContent;
    });

    document.querySelectorAll('[data-placeholder-bn][data-placeholder-en]').forEach((el) => {
      el.placeholder = el.getAttribute(`data-placeholder-${lang}`) || el.placeholder;
    });

    bnBtn?.classList.toggle('active', lang === 'bn');
    enBtn?.classList.toggle('active', lang === 'en');
    localStorage.setItem('hisebghor-landing-lang', lang);
  }

  bnBtn?.addEventListener('click', () => setLang('bn'));
  enBtn?.addEventListener('click', () => setLang('en'));
  document.querySelectorAll('.faq-q').forEach((q) => q.addEventListener('click', () => q.parentElement.classList.toggle('open')));

  const mobileMenuButton = document.getElementById('landingMenuToggle');
  const mobileMenu = document.getElementById('landingMobileMenu');

  function closeMobileMenu() {
    mobileMenu?.classList.remove('open');
    mobileMenuButton?.classList.remove('open');
    mobileMenuButton?.setAttribute('aria-expanded', 'false');
  }

  mobileMenuButton?.addEventListener('click', () => {
    const isOpen = mobileMenu?.classList.toggle('open') || false;
    mobileMenuButton.classList.toggle('open', isOpen);
    mobileMenuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  mobileMenu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobileMenu));
  window.addEventListener('resize', () => {
    if (window.matchMedia('(min-width: 1001px)').matches) {
      closeMobileMenu();
    }
  });

  const savedLang = localStorage.getItem('hisebghor-landing-lang');
  if (savedLang === 'bn' || savedLang === 'en') {
    setLang(savedLang);
  }

  const form = document.getElementById('demoForm');
  const status = document.getElementById('landingFormStatus');
  const submitButton = form?.querySelector('button[type="submit"]');
  const captchaEnabled = form?.dataset.captchaEnabled === '1';
  const captchaUrl = form?.dataset.captchaUrl || '';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const captchaModal = document.getElementById('landingCaptchaModal');
  const captchaQuestion = document.getElementById('landingCaptchaQuestion');
  const captchaInput = document.getElementById('landingCaptchaInput');
  const captchaTokenInput = document.getElementById('landingCaptchaToken');
  const captchaAnswerInput = document.getElementById('landingCaptchaAnswer');
  const captchaError = document.getElementById('landingCaptchaError');
  const captchaRefresh = document.getElementById('landingCaptchaRefresh');
  const captchaVerify = document.getElementById('landingCaptchaVerify');
  let captchaLoading = false;
  let submitting = false;

  function showStatus(message, isError = false) {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('error', isError);
    status.style.display = 'block';
  }

  function clearStatus() {
    if (!status) return;
    status.textContent = '';
    status.style.display = 'none';
    status.classList.remove('error');
  }

  function showCaptchaError(message) {
    if (!captchaError) return;
    captchaError.textContent = message;
    captchaError.classList.add('show');
  }

  function clearCaptchaError() {
    if (!captchaError) return;
    captchaError.textContent = '';
    captchaError.classList.remove('show');
  }

  function setCaptchaBusy(busy) {
    captchaLoading = busy;
    if (captchaRefresh) captchaRefresh.disabled = busy || submitting;
    if (captchaVerify) captchaVerify.disabled = busy || submitting;
  }

  function openCaptchaModal() {
    if (!captchaModal) return;
    clearCaptchaError();
    captchaModal.classList.add('open');
    captchaModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('captcha-open');
    loadCaptcha();
  }

  function closeCaptchaModal() {
    if (!captchaModal || submitting) return;
    captchaModal.classList.remove('open');
    captchaModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('captcha-open');
    if (captchaInput) captchaInput.value = '';
    if (captchaAnswerInput) captchaAnswerInput.value = '';
    clearCaptchaError();
    submitButton?.focus();
  }

  async function loadCaptcha() {
    if (!captchaUrl || captchaLoading) return;

    setCaptchaBusy(true);
    clearCaptchaError();
    if (captchaQuestion) captchaQuestion.textContent = translated('captchaLoading');
    if (captchaInput) {
      captchaInput.value = '';
      captchaInput.disabled = true;
    }

    try {
      const response = await fetch(captchaUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken
        }
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload?.data?.token || !payload?.data?.challenge) {
        throw new Error('Captcha request failed');
      }

      if (captchaTokenInput) captchaTokenInput.value = payload.data.token;
      if (captchaQuestion) captchaQuestion.textContent = payload.data.challenge;
      if (captchaInput) {
        captchaInput.disabled = false;
        captchaInput.focus();
      }
    } catch (error) {
      if (captchaQuestion) captchaQuestion.textContent = '—';
      showCaptchaError(translated('error'));
    } finally {
      setCaptchaBusy(false);
    }
  }

  function firstValidationMessage(payload) {
    const errors = payload?.errors || {};
    const values = Object.values(errors).flat();
    return values.length ? String(values[0]) : '';
  }

  async function submitInquiry() {
    if (!form || submitting) return;

    submitting = true;
    submitButton?.setAttribute('disabled', 'disabled');
    if (captchaVerify) captchaVerify.disabled = true;
    if (captchaRefresh) captchaRefresh.disabled = true;
    showStatus(currentLang() === 'bn' ? 'পাঠানো হচ্ছে...' : 'Sending...');

    try {
      const response = await fetch(form.action, {
        method: form.method || 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(form)
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        const captchaFailed = Boolean(payload?.errors?.captcha_answer || payload?.errors?.captcha_token);

        if (captchaEnabled && captchaFailed) {
          clearStatus();
          await loadCaptcha();
          showCaptchaError(translated('captchaInvalid'));
          return;
        }

        throw new Error(firstValidationMessage(payload) || 'Request failed');
      }

      form.reset();
      if (captchaTokenInput) captchaTokenInput.value = '';
      if (captchaAnswerInput) captchaAnswerInput.value = '';
      captchaModal?.classList.remove('open');
      captchaModal?.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('captcha-open');
      showStatus(translated('success'));
    } catch (error) {
      showStatus(error?.message && error.message !== 'Request failed' ? error.message : translated('error'), true);
      if (captchaModal?.classList.contains('open')) {
        captchaModal.classList.remove('open');
        captchaModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('captcha-open');
      }
    } finally {
      submitting = false;
      submitButton?.removeAttribute('disabled');
      if (captchaVerify) captchaVerify.disabled = captchaLoading;
      if (captchaRefresh) captchaRefresh.disabled = captchaLoading;
    }
  }

  form?.addEventListener('submit', (event) => {
    event.preventDefault();
    clearStatus();

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    if (captchaEnabled) {
      openCaptchaModal();
      return;
    }

    submitInquiry();
  });

  captchaRefresh?.addEventListener('click', loadCaptcha);
  captchaVerify?.addEventListener('click', () => {
    const answer = captchaInput?.value.trim() || '';

    if (!answer) {
      showCaptchaError(translated('captchaInvalid'));
      captchaInput?.focus();
      return;
    }

    if (captchaAnswerInput) captchaAnswerInput.value = answer;
    clearCaptchaError();
    submitInquiry();
  });

  captchaInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      captchaVerify?.click();
    }
  });

  document.querySelectorAll('[data-captcha-close]').forEach((button) => {
    button.addEventListener('click', closeCaptchaModal);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMobileMenu();
      if (captchaModal?.classList.contains('open')) {
        closeCaptchaModal();
      }
    }
  });
})();
</script>
