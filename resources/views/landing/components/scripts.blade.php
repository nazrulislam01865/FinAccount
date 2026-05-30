<script>
(function () {
  const bnBtn = document.getElementById('bnBtn');
  const enBtn = document.getElementById('enBtn');
  const successText = {
    bn: @json($txt(data_get($landing, 'contact.form.success'), 'bn')),
    en: @json($txt(data_get($landing, 'contact.form.success'), 'en'))
  };

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

  const savedLang = localStorage.getItem('hisebghor-landing-lang');
  if (savedLang === 'bn' || savedLang === 'en') {
    setLang(savedLang);
  }

  const form = document.getElementById('demoForm');
  const status = document.getElementById('landingFormStatus');

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const lang = document.documentElement.lang === 'en' ? 'en' : 'bn';
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton?.textContent;

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = '...';
    }

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: new FormData(form),
        credentials: 'same-origin',
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Request failed.');
      }

      form.reset();
      if (status) {
        status.textContent = successText[lang];
        status.classList.remove('error');
        status.style.display = 'block';
      } else {
        alert(successText[lang]);
      }
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Request failed.';
        status.classList.add('error');
        status.style.display = 'block';
      } else {
        alert(error.message || 'Request failed.');
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      }
    }
  });
})();
</script>
