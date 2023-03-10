<?php

namespace Sixgweb\Recaptcha\Classes;

use App;
use Event;
use October\Rain\Database\Model;

abstract class AbstractEventHandler
{
    protected string $componentClass;

    abstract protected function getComponentClass(): ?string;
    abstract protected function getComponentModel($component): ?Model;

    public function subscribe()
    {
        $this->componentClass = $this->getComponentClass();
        $this->extendComponentClass();
    }

    protected function extendComponentClass(): void
    {
        if (App::runningInBackend() || !$this->componentClass) {
            return;
        }

        $this->componentClass::extend(function ($component) {

            //By deferring the addComponent to the controller event, we give other plugins the opportunity
            //to manipulate the field models
            $component->getController()->bindEvent('page.initComponents', function ($page, $layout) use ($component) {
                $find = $this->getRecaptchaComponentClass();
                $recaptchaComponent = array_first($page->components, function ($pageComponent) use ($find) {
                    return get_class($pageComponent) == $find;
                });

                if ($recaptchaComponent) {
                    $recaptchaComponent->bindModel($this->getComponentModel($component));
                }
            });
        });
    }

    /**
     * Get class name for integration's Fields component
     *
     * @return string
     */
    protected function getRecaptchaComponentClass(): string
    {
        [$owner, $plugin] = explode('\\', get_class($this));
        return implode('\\', [$owner, $plugin, 'Components', 'Recaptcha']);
    }
}
