<?php

namespace Botble\Tpuploader\Providers;

use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Supports\ServiceProvider;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\Tpuploader\Models\TPUploader;

class TPUploaderServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/tpuploader')
            ->loadHelpers()
            ->loadAndPublishConfigurations(['permissions'])
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadMigrations();

        $this->registerViewOverrides();

        if (defined('LANGUAGE_ADVANCED_MODULE_SCREEN_NAME')) {
            \Botble\LanguageAdvanced\Supports\LanguageAdvancedManager::registerModule(TPUploader::class, [
                'name',
            ]);
        }

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::registerItem([
                'id' => 'cms-plugins-tpuploader',
                'priority' => 5,
                'parent_id' => null,
                'name' => 'plugins/tpuploader::tpuploader.name',
                'icon' => 'ti ti-box',
                'url' => route('tpuploader.index'),
                'permissions' => ['tpuploader.index'],
            ]);
        });
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
