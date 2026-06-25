<section class="section contact" id="contact">
  <div class="container">
    <div class="contact-grid">
      <div class="contact-card contact-copy-card">
        <h3 data-bn="{{ $txt(data_get($landing, 'contact.title'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.title'), 'en') }}">{{ $txt(data_get($landing, 'contact.title'), $defaultLang) }}</h3>
        <p data-bn="{{ $txt(data_get($landing, 'contact.body'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.body'), 'en') }}">{{ $txt(data_get($landing, 'contact.body'), $defaultLang) }}</p>
        <div class="contact-methods">
          @if(data_get($landing, 'contact.phone'))
            <a class="contact-method" href="{{ $landingWhatsAppUrl(data_get($landing, 'contact.phone')) }}" target="_blank" rel="noopener">
              <span class="contact-method-icon" aria-hidden="true">☎</span>
              <div>
                <b>{{ data_get($landing, 'contact.phone') }}</b>
                <span data-bn="{{ $txt(data_get($landing, 'contact.phone_note'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.phone_note'), 'en') }}">{{ $txt(data_get($landing, 'contact.phone_note'), $defaultLang) }}</span>
              </div>
            </a>
          @endif
          @if(data_get($landing, 'contact.email'))
            <a class="contact-method" href="mailto:{{ data_get($landing, 'contact.email') }}">
              <span class="contact-method-icon" aria-hidden="true">✉</span>
              <div>
                <b>{{ data_get($landing, 'contact.email') }}</b>
                <span data-bn="{{ $txt(data_get($landing, 'contact.email_note'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.email_note'), 'en') }}">{{ $txt(data_get($landing, 'contact.email_note'), $defaultLang) }}</span>
              </div>
            </a>
          @endif
        </div>
      </div>

      <div class="contact-card contact-form-card">
        <div class="form-status" id="landingFormStatus" role="status" aria-live="polite"></div>
        <form
          class="form"
          id="demoForm"
          method="POST"
          action="{{ route('landing.inquiries.store') }}"
          data-captcha-enabled="{{ data_get($landing, 'contact.captcha.enabled', true) ? '1' : '0' }}"
          data-captcha-url="{{ route('landing.captcha.challenge') }}"
        >
          @csrf
          <input name="name" required autocomplete="name" data-placeholder-bn="{{ $txt(data_get($landing, 'contact.form.name'), 'bn') }}" data-placeholder-en="{{ $txt(data_get($landing, 'contact.form.name'), 'en') }}" placeholder="{{ $txt(data_get($landing, 'contact.form.name'), $defaultLang) }}">
          <input name="business_name" autocomplete="organization" data-placeholder-bn="{{ $txt(data_get($landing, 'contact.form.business_name'), 'bn') }}" data-placeholder-en="{{ $txt(data_get($landing, 'contact.form.business_name'), 'en') }}" placeholder="{{ $txt(data_get($landing, 'contact.form.business_name'), $defaultLang) }}">
          <input name="mobile" inputmode="tel" autocomplete="tel" data-placeholder-bn="{{ $txt(data_get($landing, 'contact.form.mobile'), 'bn') }}" data-placeholder-en="{{ $txt(data_get($landing, 'contact.form.mobile'), 'en') }}" placeholder="{{ $txt(data_get($landing, 'contact.form.mobile'), $defaultLang) }}">
          <input name="email" type="email" autocomplete="email" data-placeholder-bn="{{ $txt(data_get($landing, 'contact.form.email'), 'bn') }}" data-placeholder-en="{{ $txt(data_get($landing, 'contact.form.email'), 'en') }}" placeholder="{{ $txt(data_get($landing, 'contact.form.email'), $defaultLang) }}">
          <textarea name="message" data-placeholder-bn="{{ $txt(data_get($landing, 'contact.form.message'), 'bn') }}" data-placeholder-en="{{ $txt(data_get($landing, 'contact.form.message'), 'en') }}" placeholder="{{ $txt(data_get($landing, 'contact.form.message'), $defaultLang) }}"></textarea>
          <input type="hidden" name="captcha_token" id="landingCaptchaToken">
          <input type="hidden" name="captcha_answer" id="landingCaptchaAnswer">
          <button class="btn btn-primary demo-submit-button" type="submit" data-bn="{{ $txt(data_get($landing, 'contact.form.button'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.form.button'), 'en') }}">{{ $txt(data_get($landing, 'contact.form.button'), $defaultLang) }}</button>
        </form>
      </div>
    </div>
  </div>
</section>

<div class="captcha-modal" id="landingCaptchaModal" aria-hidden="true">
  <div class="captcha-modal-backdrop" data-captcha-close></div>
  <div class="captcha-dialog" role="dialog" aria-modal="true" aria-labelledby="landingCaptchaTitle">
    <button type="button" class="captcha-close" data-captcha-close aria-label="Close">×</button>
    <div class="captcha-shield" aria-hidden="true">✓</div>
    <h3 id="landingCaptchaTitle" data-bn="{{ $txt(data_get($landing, 'contact.captcha.title'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.captcha.title'), 'en') }}">{{ $txt(data_get($landing, 'contact.captcha.title'), $defaultLang) }}</h3>
    <p data-bn="{{ $txt(data_get($landing, 'contact.captcha.instruction'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.captcha.instruction'), 'en') }}">{{ $txt(data_get($landing, 'contact.captcha.instruction'), $defaultLang) }}</p>

    <div class="captcha-question" id="landingCaptchaQuestion" aria-live="polite">—</div>
    <input
      id="landingCaptchaInput"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      data-placeholder-bn="{{ $txt(data_get($landing, 'contact.captcha.placeholder'), 'bn') }}"
      data-placeholder-en="{{ $txt(data_get($landing, 'contact.captcha.placeholder'), 'en') }}"
      placeholder="{{ $txt(data_get($landing, 'contact.captcha.placeholder'), $defaultLang) }}"
    >
    <div class="captcha-error" id="landingCaptchaError" role="alert"></div>

    <div class="captcha-actions">
      <button type="button" class="btn btn-outline captcha-refresh" id="landingCaptchaRefresh" data-bn="{{ $txt(data_get($landing, 'contact.captcha.refresh_button'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.captcha.refresh_button'), 'en') }}">{{ $txt(data_get($landing, 'contact.captcha.refresh_button'), $defaultLang) }}</button>
      <button type="button" class="btn btn-outline" data-captcha-close data-bn="{{ $txt(data_get($landing, 'contact.captcha.cancel_button'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.captcha.cancel_button'), 'en') }}">{{ $txt(data_get($landing, 'contact.captcha.cancel_button'), $defaultLang) }}</button>
      <button type="button" class="btn btn-primary" id="landingCaptchaVerify" data-bn="{{ $txt(data_get($landing, 'contact.captcha.verify_button'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'contact.captcha.verify_button'), 'en') }}">{{ $txt(data_get($landing, 'contact.captcha.verify_button'), $defaultLang) }}</button>
    </div>
  </div>
</div>
