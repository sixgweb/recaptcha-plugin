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

            Event::listen('cms.ajax.beforeRunHandler', function ($controller, $handler) use ($component) {
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

        //No recaptcha component found on the page, add it dynamically
        if (!$recaptchaComponent) {
            $recaptchaComponent = $component->addComponent($find, 'recaptchaAliased', $this->getComponentProperties($component));
        }

        if ($recaptchaComponent) {
            $model = $this->getComponentModel($component);
            if ($model) {
                $recaptchaComponent->bindModel($model);
            } else {
                $recaptchaComponent->bindRequest();
            }
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

    protected function getRecaptchaSessionKey(): string
    {
        return 'recaptcha_' . md5(get_class($this));
    }

    protected function getComponentProperties($component): array
    {
        return [];
    }
}
