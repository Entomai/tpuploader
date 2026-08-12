<?php

namespace Botble\Tpuploader\Services;

use Botble\Base\Facades\BaseHelper;
use Botble\PluginManagement\Services\PluginService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class BulkPluginActionService
{
    public function __construct(protected PluginService $pluginService) {}

    public function activate(array $plugins): array
    {
        return $this->execute('activate', array_reverse($this->orderDependentsFirst($plugins)));
    }

    public function deactivate(array $plugins): array
    {
        return $this->execute('deactivate', $this->orderDependentsFirst($plugins));
    }

    public function remove(array $plugins): array
    {
        return $this->execute('remove', $this->orderDependentsFirst($plugins));
    }

    protected function execute(string $action, array $plugins): array
    {
        $results = [];

        foreach ($this->normalizePlugins($plugins) as $plugin) {
            $pluginInfo = $this->pluginService->getPluginInfo($plugin);
            $pluginName = (string) (Arr::get($pluginInfo, 'name') ?: Str::headline($plugin));

            try {
                $result = $this->runAction($action, $plugin);
            } catch (Throwable $exception) {
                BaseHelper::logError($exception);

                $result = [
                    'error' => true,
                    'message' => $exception->getMessage()
                        ?: trans('plugins/tpuploader::tpuploader.bulk_action_exception'),
                ];
            }

            $results[] = [
                'plugin' => $plugin,
                'name' => $pluginName,
                'action' => $action,
                'error' => (bool) ($result['error'] ?? true),
                'message' => (string) ($result['message'] ?? trans(
                    'plugins/tpuploader::tpuploader.bulk_action_exception'
                )),
            ];
        }

        return $results;
    }

    protected function runAction(string $action, string $plugin): array
    {
        if ($validationErrors = $this->pluginService->getPluginValidationErrors($plugin)) {
            $details = collect($validationErrors)
                ->map(fn (array $messages, string $field): string => sprintf(
                    '%s (%s)',
                    $field,
                    implode('; ', $messages)
                ))
                ->values()
                ->implode(', ');

            return [
                'error' => true,
                'message' => trans('packages/plugin-management::plugin.invalid_plugin_with_errors', [
                    'errors' => $details,
                ]),
            ];
        }

        if ($action === 'remove' && in_array($plugin, get_active_plugins(), true)) {
            $deactivation = $this->pluginService->deactivate($plugin);

            if ($deactivation['error']) {
                return $deactivation;
            }
        }

        return match ($action) {
            'activate' => $this->pluginService->activate($plugin),
            'deactivate' => $this->pluginService->deactivate($plugin),
            'remove' => $this->pluginService->remove($plugin),
            default => [
                'error' => true,
                'message' => trans('plugins/tpuploader::tpuploader.bulk_action_invalid'),
            ],
        };
    }

    protected function orderDependentsFirst(array $plugins): array
    {
        $plugins = $this->normalizePlugins($plugins);
        $selectedPlugins = array_fill_keys($plugins, true);
        $dependencies = array_fill_keys($plugins, []);
        $incomingEdges = array_fill_keys($plugins, 0);
        $orderedPlugins = [];

        foreach ($plugins as $plugin) {
            $pluginInfo = $this->pluginService->getPluginInfo($plugin);
            $requiredPlugins = array_merge(
                (array) Arr::get($pluginInfo, 'required_plugins', []),
                (array) Arr::get($pluginInfo, 'require', [])
            );

            foreach ($requiredPlugins as $requiredPlugin) {
                if (! is_string($requiredPlugin)) {
                    continue;
                }

                $dependency = Str::afterLast($requiredPlugin, '/');

                if (! isset($selectedPlugins[$dependency]) || in_array($dependency, $dependencies[$plugin], true)) {
                    continue;
                }

                $dependencies[$plugin][] = $dependency;
                $incomingEdges[$dependency]++;
            }
        }

        $queue = array_values(array_filter(
            $plugins,
            fn (string $plugin): bool => $incomingEdges[$plugin] === 0
        ));

        while ($queue) {
            $plugin = array_shift($queue);
            $orderedPlugins[] = $plugin;

            foreach ($dependencies[$plugin] as $dependency) {
                $incomingEdges[$dependency]--;

                if ($incomingEdges[$dependency] === 0) {
                    $queue[] = $dependency;
                }
            }
        }

        foreach ($plugins as $plugin) {
            if (! in_array($plugin, $orderedPlugins, true)) {
                $orderedPlugins[] = $plugin;
            }
        }

        return $orderedPlugins;
    }

    protected function normalizePlugins(array $plugins): array
    {
        return array_values(array_unique(array_filter(
            $plugins,
            fn (mixed $plugin): bool => is_string($plugin) && $plugin !== ''
        )));
    }
}
