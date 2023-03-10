<?php

namespace Sixgweb\Recaptcha;

use App;
use Event;
use Backend;
use System\Classes\PluginBase;
use Sixgweb\Recaptcha\Models\Settings;

/**
 * Recaptcha Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Recaptcha',
            'description' => 'No description provided yet...',
            'author'      => 'Sixgweb',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->addV3Scripts();
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'ReCaptcha',
                'description' => 'ReCaptcha Site/Secret Keys',
                'icon' => 'icon-lock',
                'category' => 'Sixgweb',
                'class' => 'Sixgweb\Recaptcha\Models\Settings',
                'permissions' => ['sixgweb.recaptcha.access_settings'],
            ]
        ];
    }

    public function registerComponents()
    {
        /**
         * We want the Fields component available on the frontend but not listed
         * in the Editor component popup, so we only return on the frontend.
         */
        if (App::runningInBackend()) {
            return [];
        }

        return [
            'Sixgweb\Recaptcha\Components\Recaptcha' => 'recaptchaBase',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate

        return [
            'sixgweb.recaptcha.access_settings' => [
                'tab' => 'Recaptcha',
                'label' => 'Access Settings'
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate

        return [
            'recaptcha' => [
                'label'       => 'Recaptcha',
                'url'         => Backend::url('sixgweb/recaptcha/mycontroller'),
                'icon'        => 'icon-leaf',
                'permissions' => ['sixgweb.recaptcha.*'],
                'order'       => 500,
            ],
        ];
    }

    protected function addV3Scripts(): void
    {
        if (Settings::get('version', 'v2') == 'v3') {
            Event::listen('cms.page.beforeDisplay', function ($controller) {
                $controller->addJS('/plugins/sixgweb/recaptcha/assets/js/recaptcha.js');
                $query = '?onload=recaptchaOnLoad&render=' . Settings::get('site_key');
                $controller->addJs('https://www.google.com/recaptcha/api.js' . $query);
            });
        }
    }
}
