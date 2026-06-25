<?php

namespace App\Http\Requests;

use App\Support\LandingPageCaptcha;
use App\Support\LandingPageContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LandingPageInquiryRequest extends FormRequest
{
    private bool $captchaEnabled = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->captchaEnabled = (bool) data_get(
            LandingPageContent::current(),
            'contact.captcha.enabled',
            true
        );
    }

    public function rules(): array
    {
        $captchaRule = $this->captchaEnabled ? ['required'] : ['nullable'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'message' => ['nullable', 'string', 'max:2000'],
            'captcha_token' => [...$captchaRule, 'string', 'max:100'],
            'captcha_answer' => [...$captchaRule, 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->captchaEnabled) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['captcha_token', 'captcha_answer'])) {
                return;
            }

            if (! LandingPageCaptcha::valid(
                $this,
                $this->input('captcha_token'),
                $this->input('captcha_answer')
            )) {
                $validator->errors()->add(
                    'captcha_answer',
                    'The security answer is incorrect or the challenge has expired.'
                );
            }
        });
    }

    protected function passedValidation(): void
    {
        if ($this->captchaEnabled) {
            LandingPageCaptcha::consume($this, $this->input('captcha_token'));
        }
    }
}
