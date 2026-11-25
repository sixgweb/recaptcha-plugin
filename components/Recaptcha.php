<?php

namespace Sixgweb\Recaptcha\Components;

use Log;
use Lang;
use Model;
use Event;
use Config;
use Request;
use Session;
use WeakMap;
use ValidationException;
use ReCaptcha\ReCaptcha as RecaptchaValidator;
use Cms\Classes\ComponentBase;
use Sixgweb\Recaptcha\Models\Settings;

/**
 * Recaptcha Component
 */
class Recaptcha extends ComponentBase
{
    /**
     * Seconds a stored session pass (from onCheckRecaptcha) stays valid.
     */
    public const SESSION_PASS_TTL = 600;

    protected $model;
    protected $passed = false;
    protected $version;
    protected $method;
    protected $size;
    protected $theme;
    protected $sitekey;
    protected $errorCodes = [];

    /**
     * Models whose validation events are already bound to this component.
     */
    protected ?WeakMap $boundModels = null;

    /**
     * Token hashes verified with Google during this request, shared across components
     * so the same token is never sent to Google twice (Google rejects duplicates).
     *
     * @var array<string, bool>
     */
    protected static array $verifiedTokens = [];

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
            'useSession' => [
                'title' => 'Use Session',
                'description' => 'Store reCaptcha result in session',
                'type' => 'checkbox',
                'default' => true,
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
        $this->sitekey = (string) Settings::get('site_key');
    }

    /**
     * Add reCaptcha scripts. Only pages that use a reCaptcha component load them.
     *
     * @return void
     */
    public function addScripts(): void
    {
        //Works with the October 3.x and 4.x AJAX frameworks (oc.ajax, oc.request, render events)
        $this->addJs('/plugins/sixgweb/recaptcha/assets/js/recaptcha.js');

        $query = ['onload' => 'recaptchaOnLoad'];

        if ($this->getVersion() == 'v3') {
            $query['render'] = $this->getSiteKey();
        }

        $this->addJs('https://www.google.com/recaptcha/api.js?' . http_build_query($query));
    }

    /**
     * Require a valid reCaptcha whenever the integrated model validates.
     *
     * @param \October\Rain\Database\Model $model
     * @return void
     */
    public function bindModel($model): void
    {
        $this->model = $model;
        $this->boundModels ??= new WeakMap;

        if (isset($this->boundModels[$model])) {
            return;
        }

        $this->boundModels[$model] = true;

        $model->bindEvent('model.afterValidate', function () {
            $this->verifyOrFail();
        });
    }

    /**
     * Require a valid reCaptcha for the current request when there is no model to bind.
     *
     * @return void
     */
    public function bindRequest(): void
    {
        $this->verifyOrFail();
    }

    /**
     * Pass if this request already passed, a stored session pass exists, or the
     * posted token verifies with Google. Throws a ValidationException otherwise.
     *
     * @return void
     * @throws ValidationException
     */
    public function verifyOrFail(): void
    {
        if ($this->passed) {
            return;
        }

        if ($this->consumeSessionPass()) {
            $this->passed = true;
            return;
        }

        $token = post('g-recaptcha-response');

        if (!is_string($token) || $token === '') {
            throw new ValidationException(['g-recaptcha-response' => Lang::get('sixgweb.recaptcha::lang.required')]);
        }

        if (!$this->checkRecaptcha($token)) {
            throw new ValidationException(['g-recaptcha-response' => Lang::get('sixgweb.recaptcha::lang.invalid')]);
        }
    }

    /**
     * AJAX handler for the v2 checkbox callback. Verifies the token and stores a
     * single-use session pass so the widget can be removed from the form.
     *
     * @return array|false|null
     */
    public function onCheckRecaptcha()
    {
        if (!$this->property('useSession')) {
            return null;
        }

        $token = post('g-recaptcha-response');

        if (!is_string($token) || $token === '' || !$this->checkRecaptcha($token)) {
            return false;
        }

        $this->storeSessionPass();

        return ['recaptchaId' => $this->alias . 'RecaptchaContainer'];
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
     * Whether the reCaptcha has passed for this request or has an unused session pass.
     * Does not consume the session pass, so it is safe to call from templates.
     *
     * @return bool
     */
    public function getPassed(): bool
    {
        if ($this->passed) {
            return true;
        }

        if (!$this->property('useSession', false)) {
            return false;
        }

        return $this->isFreshPass(Session::get($this->getSessionKey()));
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * Set passed property, storing a single-use session pass when enabled.
     *
     * @param boolean $passed
     * @return void
     */
    public function setPassed(bool $passed): void
    {
        Session::forget($this->getSessionKey());
        $this->passed = $passed;

        if ($passed) {
            $this->storeSessionPass();
        }
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
     * Verify a token with Google. Sets the in-request passed flag on success.
     * Does not store a session pass; only onCheckRecaptcha does that.
     *
     * @param string $value
     * @return boolean
     */
    public function checkRecaptcha($value): bool
    {
        if ($this->passed) {
            return true;
        }

        if (!is_string($value) || $value === '') {
            return false;
        }

        $tokenHash = hash('sha256', $value);

        if (isset(static::$verifiedTokens[$tokenHash])) {
            $this->passed = true;
            return true;
        }

        $recaptcha = (new RecaptchaValidator((string) Settings::get('secret_key')))
            ->setExpectedHostname(Request::getHost());

        if ($this->getVersion() == 'v3') {
            $recaptcha = $recaptcha
                ->setScoreThreshold((float) Settings::get('score', 0.5))
                ->setExpectedAction($this->getExpectedAction());
        }

        $response = $recaptcha->verify($value, Request::ip());

        if ($response->isSuccess()) {
            static::$verifiedTokens[$tokenHash] = true;
            $this->passed = true;
            Event::fire('sixgweb.recaptcha.passed', [$this]);
            return true;
        }

        $this->errorCodes = $response->getErrorCodes();

        Log::info('reCaptcha verification failed', [
            'component' => static::class,
            'alias' => $this->alias,
            'errorCodes' => $this->errorCodes,
            'score' => $response->getScore(),
            'action' => $response->getAction(),
            'hostname' => $response->getHostname(),
        ]);

        return false;
    }

    /**
     * The v3 action the browser sends: the AJAX handler name (letters only), or "submit".
     * Must match the action built in assets/js/recaptcha.js. October 4.x sends the
     * handler in X-AJAX-HANDLER, October 3.x in X-OCTOBER-REQUEST-HANDLER.
     *
     * @return string
     */
    protected function getExpectedAction(): string
    {
        $handler = '';

        if (Request::ajax()) {
            $handler = (string) (Request::header('X-AJAX-HANDLER') ?: Request::header('X-OCTOBER-REQUEST-HANDLER'));
        }

        $action = preg_replace('/[^a-z]/i', '', $handler);

        return $action !== '' ? $action : 'submit';
    }

    /**
     * Store a single-use pass in the session, if sessions are enabled for this component.
     *
     * @return void
     */
    protected function storeSessionPass(): void
    {
        if ($this->property('useSession')) {
            Session::put($this->getSessionKey(), time());
        }
    }

    /**
     * Remove the session pass and return whether it was present and still fresh.
     *
     * @return bool
     */
    protected function consumeSessionPass(): bool
    {
        if (!$this->property('useSession')) {
            return false;
        }

        return $this->isFreshPass(Session::pull($this->getSessionKey()));
    }

    /**
     * @param mixed $passedAt Unix timestamp stored by storeSessionPass()
     * @return bool
     */
    protected function isFreshPass($passedAt): bool
    {
        return is_int($passedAt) && (time() - $passedAt) <= static::SESSION_PASS_TTL;
    }

    /**
     * Session key for this component on this page. Built from server-side values only,
     * so the v2 callback request and the form submit share the same key.
     *
     * @return string
     */
    protected function getSessionKey(): string
    {
        $page = $this->controller ? $this->controller->getPage() : null;
        $pageName = $page ? (string) $page->getBaseFileName() : '';

        return 'sixgweb.recaptcha.passed.' . md5($pageName . '|' . $this->alias);
    }
}
