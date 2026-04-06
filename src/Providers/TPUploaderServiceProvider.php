<?php

namespace Botble\Tpuploader\Providers;

use Botble\Base\Supports\ServiceProvider;
use Botble\Base\Traits\LoadAndPublishDataTrait;

class TPUploaderServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/tpuploader')
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->loadAndPublishViews();

        $this->registerViewOverrides();
    }

    protected function registerViewOverrides(): void
    {
        $overrides = [
            'packages/theme' => $this->getPath('resources/views/vendor/packages/theme'),
            'packages/plugin-management' => $this->getPath('resources/views/vendor/packages/plugin-management'),
        ];

        $this->callAfterResolving('view', function ($view) use ($overrides): void {
            foreach ($overrides as $namespace => $path) {
                if (! is_dir($path)) {
                    continue;
                }

                $view->getFinder()->prependNamespace($namespace, $path);
            }
        });
    }
}
