<?php

namespace Sixgweb\Recaptcha\Components;

use Model;
use Event;
use Config;
use Request;
use ValidationException;
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
    protected $version;
    protected $method;
    protected $size;
    protected $theme;
    protected $sitekey;
    protected $errorCodes = [];

    /**
     * Component Details
     *
     * @return array
     */
    public function componentDetails(): array
    {
        return [
            'name' => 'Recaptcha Component',
            'description' => 'Google Recaptcha v2 and v3'
        ];
    }

    /**
     * Component Properties
     *
     * @return array
     */
    public function defineProperties(): array
    {
        return [
            'wrapperClass' => [
                'title' => 'Wrapper Class',
                'description' => 'CSS class for wrapper',
                'type' => 'string',
                'showExternalParam' => false
            ],
        ];
    }

    /**
     * Component initialized
     *
     * @return void
     */
    public function init(): void
    {
        $this->prepareVars();
        $this->addScripts();
    }

    /**
     * Prepare page variables and component properties
     *
     * @return void
     */
    public function prepareVars(): void
    {
        $this->version = Settings::get('version', 'v2');
        $this->method = Settings::get('method', 'checkbox');
        $this->theme = Settings::get('theme', 'light');
        $this->size = $this->method == 'invisible' ? 'invisible' : Settings::get('size', 'normal');
        $this->sitekey = Settings::get('site_key');
    }

    /**
     * Add reCaptcha scripts
     *
     * @return void
     */
    public function addScripts(): void
    {
        if ($this->getPassed() || $this->getVersion() == 'v3') {
            return;
        }

        $this->addJs('assets/js/recaptcha.js'); //comes first so onload callback is found
        $this->addJs('https://www.google.com/recaptcha/api.js?onload=recaptchaOnLoad');
    }

    /**
     * Add events and validation rules to integrated model
     *
     * @param [type] $model
     * @return void
     */
    public function bindModel($model): void
    {
        $this->model = $model;

        $model->bindEvent('model.afterValidate', function () use ($model) {
            if ($this->getPassed()) {
                return;
            }

            if (!$response = post('g-recaptcha-response', null)) {
                throw new ValidationException([0 => 'Please complete the reCaptcha challenge before submitting the form.']);
            }

            if ($this->checkRecaptcha($response)) {
                return;
            } else {
                if ($this->getVersion() == 'v2') {
                    throw new ValidationException([0 => 'ReCaptcha Response Invalid.  Code:' . implode(' | ', $this->errorCodes)]);
                } else {
                    throw new ValidationException([0 => 'ReCaptcha Score Too Low']);
                }
            }
        });
    }

    /**
     * Override ComponentBase, always returning Recaptcha/Components/Recaptcha path
     * for subclasses.
     *
     * @return string
     */
    public function getPath(): string
    {
        $dirName = '/' . strtolower(str_replace('\\', '/', Recaptcha::class));
        return plugins_path() . $dirName;
    }

    /**
     * Get recaptcha site key
     *
     * @return string
     */
    public function getSiteKey(): string
    {
        return $this->sitekey;
    }

    /**
     * Get recaptcha version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get v2 method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get v2 theme
     *
     * @return string
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * Get v2 size
     *
     * @return string
     */
    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * Get recaptcha passed
     *
     * @return string
     */
    public function getPassed(): bool
    {
        return $this->passed;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * Set passed property
     *
     * @param boolean $passed
     * @return void
     */
    public function setPassed(bool $passed): void
    {
        $this->passed = $passed;
    }

    /**
     * Override ComponentBase, always returning Recaptcha/Components/Recaptcha path
     * for subclasses.
     *
     * @return string
     */
    protected function getComponentAssetPath(): string
    {
        $assetUrl = Config::get('system.plugins_asset_url');

        if (!$assetUrl) {
            $assetUrl = Config::get('app.asset_url') . '/plugins';
        }
        $dirName = '/' . strtolower(str_replace('\\', '/', Recaptcha::class));
        return $assetUrl . dirname(dirname($dirName));
    }

    /**
     * Set passed property, captcha_errors and return boolean
     *
     * @param [type] $value
     * @return boolean
     */
    public function checkRecaptcha($value): bool
    {
        //For events firing more than once
        if ($this->getPassed()) {
            return true;
        }

        $ip = Request::ip();
        $recaptcha = new RecaptchaValidator(Settings::get('secret_key'));

        if ($this->getVersion() == 'v3') {
            $recaptcha = $recaptcha->setScoreThreshold(Settings::get('score', 0.5));
        }

        $response = $recaptcha->verify(
            $value,
            $ip
        );

        if ($response->isSuccess()) {
            $this->setPassed(true);
            Event::fire('sixgweb.recaptcha.passed', [$this]);
            return true;
        } else {
            $this->errorCodes = $response->getErrorCodes();
            return false;
        }
    }
}
