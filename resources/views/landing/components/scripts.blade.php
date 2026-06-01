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
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMobileMenu();
    }
  });
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

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const lang = document.documentElement.lang === 'en' ? 'en' : 'bn';

    if (status) {
      status.textContent = lang === 'bn' ? 'পাঠানো হচ্ছে...' : 'Sending...';
      status.classList.remove('error');
      status.style.display = 'block';
    }

    try {
      const response = await fetch(form.action, {
        method: form.method || 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(form)
      });

      if (!response.ok) {
        throw new Error('Request failed');
      }

      form.reset();

      if (status) {
        status.textContent = successText[lang] || (lang === 'bn' ? 'আপনার ডেমো রিকোয়েস্ট পাঠানো হয়েছে।' : 'Your demo request has been sent.');
        status.classList.remove('error');
      }
    } catch (error) {
      if (status) {
        status.textContent = lang === 'bn' ? 'রিকোয়েস্ট পাঠানো যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।' : 'Could not send the request. Please try again.';
        status.classList.add('error');
      }
    }
  });
})();
</script>
