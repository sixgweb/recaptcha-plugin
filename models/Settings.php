<?php

namespace Sixgweb\Recaptcha\Models;

use Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'sixgweb_recaptcha';

    public $settingsFields = 'fields.yaml';
}
