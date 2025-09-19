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
            Event::listen('cms.component.beforeRunAjaxHandler', function ($component) {
                if ($component instanceof $this->componentClass) {
                    $this->findAndBindRecaptchaComponent($component);
                }
            });

            $component->bindEvent('component.beforeRun', function () use ($component) {
                $this->findAndBindRecaptchaComponent($component);
            });
        });
    }

    protected function findAndBindRecaptchaComponent($component): void
    {
        $find = $this->getRecaptchaComponentClass();
        $page = $component->getPage();
        $recaptchaComponent = null;

        //Loop through page components to find the integration fields component
        foreach ($page->components as $pageComponent) {
            if (get_class($pageComponent) == $find) {
                $recaptchaComponent = $pageComponent;
                break;
            }
        }


        if (!$recaptchaComponent) {
            $recaptchaComponent = $component->addComponent($find, 'recaptchaAliased');
        }

        if ($recaptchaComponent) {
            $recaptchaComponent->bindModel($this->getComponentModel($component));
        }
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
