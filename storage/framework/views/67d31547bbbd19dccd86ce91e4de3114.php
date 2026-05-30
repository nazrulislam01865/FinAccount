<script>
(function () {
  const bnBtn = document.getElementById('bnBtn');
  const enBtn = document.getElementById('enBtn');
  const loginUrl = <?php echo json_encode($loginUrl, 15, 512) ?>;
  const successText = {
    bn: <?php echo json_encode($txt(data_get($landing, 'contact.form.success'), 'bn')) ?>,
    en: <?php echo json_encode($txt(data_get($landing, 'contact.form.success'), 'en')) ?>
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

  form?.addEventListener('submit', (event) => {
    event.preventDefault();

    if (status) {
      const lang = document.documentElement.lang === 'en' ? 'en' : 'bn';
      status.textContent = lang === 'bn'
        ? 'ডেমো চালু করতে সিস্টেমে লগইন করুন।'
        : 'Please log in to start the demo.';
      status.classList.remove('error');
      status.style.display = 'block';
    }

    setTimeout(() => {
      window.location.assign(loginUrl);
    }, 220);
  });
})();
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/scripts.blade.php ENDPATH**/ ?>