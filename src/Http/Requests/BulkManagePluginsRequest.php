<?php

namespace Botble\Tpuploader\Http\Requests;

use Botble\PluginManagement\Services\PluginService;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class BulkManagePluginsRequest extends Request
{
    public function rules(): array
    {
        $installedPlugins = PluginService::getInstalledPlugins();
        $activePlugins = array_values(array_filter(get_active_plugins(), 'is_string'));
        $allowedPlugins = match ($this->route()?->getName()) {
            'tpuploader.plugins.activate' => array_values(array_diff($installedPlugins, $activePlugins)),
            'tpuploader.plugins.deactivate' => $activePlugins,
            'tpuploader.plugins.remove' => $installedPlugins,
            default => [],
        };

        return [
            'plugins' => ['bail', 'required', 'array', 'min:1', 'max:'.max(count($allowedPlugins), 1)],
            'plugins.*' => ['bail', 'required', 'string', 'distinct', Rule::in($allowedPlugins)],
        ];
    }

    public function attributes(): array
    {
        return [
            'plugins' => trans('plugins/tpuploader::tpuploader.selected_plugins'),
            'plugins.*' => trans('plugins/tpuploader::tpuploader.selected_plugin'),
        ];
    }

    public function messages(): array
    {
        return [
            'plugins.required' => trans('plugins/tpuploader::tpuploader.bulk_action_no_selection'),
            'plugins.*.in' => trans('plugins/tpuploader::tpuploader.bulk_action_invalid_plugin'),
        ];
    }
}
