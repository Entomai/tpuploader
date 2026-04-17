<?php

namespace Botble\Tpuploader\Http\Requests;

use Botble\Support\Http\Requests\Request;

class UploadPluginRequest extends Request
{
    public function rules(): array
    {
        return [
            'plugin_archive' => ['required_without:plugin_archives', 'nullable', 'file', 'mimes:zip', 'max:102400'],
            'plugin_archives' => ['required_without:plugin_archive', 'nullable', 'array'],
            'plugin_archives.*' => ['file', 'mimes:zip', 'max:102400'],
            'activate' => ['nullable', 'boolean'],
            'skip_update' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'plugin_archive' => trans('plugins/tpuploader::tpuploader.plugin_archive'),
            'plugin_archives' => trans('plugins/tpuploader::tpuploader.plugin_archive'),
            'plugin_archives.*' => trans('plugins/tpuploader::tpuploader.plugin_archive'),
        ];
    }
}
