<?php

namespace Sixgweb\Recaptcha\Models;

use Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'sixgweb_recaptcha';

    public $settingsFields = 'fields.yaml';

    public function filterFields($fields, $context = null)
    {
        if (isset($fields->version) && $this->version == 'v3') {
            $fields->theme->hidden = true;
            $fields->size->hidden = true;
        }
    }
}
