<?php

namespace Sixgweb\Recaptcha\Classes;

use App;
use Event;
use WeakMap;
use WeakReference;
use October\Rain\Database\Model;

abstract class AbstractEventHandler
{
    protected string $componentClass;

    /**
     * Target component instances created during this request.
     *
     * @var array<int, WeakReference>
     */
    protected array $trackedComponents = [];

    /**
     * Recaptcha component found or added for each target component.
     */
    protected WeakMap $recaptchaComponents;

    abstract protected function getComponentClass(): ?string;
    abstract protected function getComponentModel($component): ?Model;

    public function subscribe()
    {
        $this->componentClass = (string) $this->getComponentClass();
        $this->recaptchaComponents = new WeakMap;
        $this->extendComponentClass();
    }

    /**
     * Listeners are registered once per request. Each target component instance is
     * tracked, then bound on its page cycle (component.beforeRun) and before any
     * AJAX handler runs (which happens before the page cycle).
     *
     * @return void
     */
    protected function extendComponentClass(): void
    {
        if (App::runningInBackend() || !$this->componentClass) {
            return;
        }

        $this->componentClass::extend(function ($component) {
            $this->trackedComponents[] = WeakReference::create($component);

            $component->bindEvent('component.beforeRun', function () use ($component) {
                $this->findAndBindRecaptchaComponent($component);
            });
        });

        Event::listen('cms.ajax.beforeRunHandler', function ($controller, $handler) {
            foreach ($this->trackedComponents as $reference) {
                //Skip instances not attached to this request's page, e.g. ones Snippet::getComponent() creates to read details
                if (($component = $reference->get()) && $component->getController() === $controller) {
                    $this->findAndBindRecaptchaComponent($component, (string) $handler);
                }
            }
        });
    }

    /**
     * Bind the integration's model to the recaptcha component. With no model, require
     * a verified token when the AJAX handler being run belongs to the target component.
     *
     * @param \Cms\Classes\ComponentBase $component
     * @param string|null $handler AJAX handler about to run, if any
     * @return void
     */
    protected function findAndBindRecaptchaComponent($component, ?string $handler = null): void
    {
        if (!$component->getController()) {
            return;
        }

        $recaptchaComponent = $this->getRecaptchaComponent($component);

        if (!$recaptchaComponent) {
            return;
        }

        if ($model = $this->getComponentModel($component)) {
            $recaptchaComponent->bindModel($model);
            return;
        }

        if ($handler !== null && $this->handlerTargetsComponent($component, $handler)) {
            $recaptchaComponent->bindRequest();
        }
    }

    /**
     * Find the integration's recaptcha component on the page, or add it, once per target component.
     *
     * @param \Cms\Classes\ComponentBase $component
     * @return \Sixgweb\Recaptcha\Components\Recaptcha|null
     */
    protected function getRecaptchaComponent($component)
    {
        if (isset($this->recaptchaComponents[$component])) {
            return $this->recaptchaComponents[$component];
        }

        $find = $this->getRecaptchaComponentClass();
        $recaptchaComponent = null;

        foreach ($component->getPage()->components as $pageComponent) {
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
            $this->recaptchaComponents[$component] = $recaptchaComponent;
        }

        return $recaptchaComponent;
    }

    /**
     * Whether an AJAX handler name ("alias::onHandler" or "onHandler") belongs to the component.
     *
     * @param \Cms\Classes\ComponentBase $component
     * @param string $handler
     * @return bool
     */
    protected function handlerTargetsComponent($component, string $handler): bool
    {
        if (str_contains($handler, '::')) {
            [$alias] = explode('::', $handler, 2);
            return $alias === $component->alias;
        }

        return $component->methodExists($handler);
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

    protected function getComponentProperties($component): array
    {
        return [
            'useSession' => true,
        ];
    }
}
