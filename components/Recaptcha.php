<?php

namespace Sixgweb\Recaptcha\Components;

use Session;
use Request;
use ReCaptcha\ReCaptcha as RecaptchaValidator;
use Cms\Classes\ComponentBase;
use Sixgweb\Recaptcha\Models\Settings;

/**
 * Recaptcha Component
 */
class Recaptcha extends ComponentBase
{
    protected $model;
    protected $passed = false;

    public function componentDetails()
    {
        return [
            'name' => 'Recaptcha Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function init()
    {
        $this->addJs('https://www.google.com/recaptcha/api.js?hl=en');
        $this->prepareVars();
    }

    public function prepareVars()
    {
        $public = $this->page['site_key'] = Settings::get('site_key', null);
        $this->passed = $this->page['recaptcha_passed'] = Session::get('sixgweb.recaptcha.passed', false);
    }

    public function bindModel($model)
    {
        $model->bindEvent('model.afterSave', function () {
            Session::forget('sixgweb.recaptcha.passed');
        });

        if ($val = post('g-recaptcha-response', null)) {
            if ($this->check($val)) {
                return;
            }
        }

        if ($this->passed) {
            return;
        }

        $this->model = $model;
        $this->model->rules['g-recaptcha-response'] = ['required'];

        $this->model->setValidationAttributeName('g-recaptcha-response', 'ReCaptcha');
    }

    public function onCheckRecaptcha()
    {
        if ($this->passed) {
            return ['#recaptchaContainer' => ''];
        }
    }

    private function check($value)
    {
        $ip = Request::ip();
        $recaptcha = new RecaptchaValidator(Settings::get('secret_key'));

        $response = $recaptcha->verify(
            $value,
            $ip
        );

        if ($response->isSuccess()) {
            Session::put('sixgweb.recaptcha.passed', 1);
            $this->passed = $this->page['recaptcha_passed'] = true;
            return true;
        } else {
            $this->page['captcha_error'] = 'reCAPTCHA error' . ': ' . implode(' / ', $response->getErrorCodes());
            return false;
        }
    }
}
