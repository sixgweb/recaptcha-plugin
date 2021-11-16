<?php

namespace Sixgweb\Recaptcha;

use Backend;
use System\Classes\PluginBase;

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
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'ReCaptcha',
                'description' => 'ReCaptcha Site/Secret Keys',
                'icon' => 'icon-lock',
                'category' => 'ReCaptcha',
                'class' => 'Sixgweb\Recaptcha\Models\Settings',
                'permissions' => ['sixgweb.recaptcha.access_settings'],
            ]
        ];
    }

    public function registerComponents()
    {
        return [
            'Sixgweb\Recaptcha\Components\Recaptcha' => 'recaptcha',
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
}
