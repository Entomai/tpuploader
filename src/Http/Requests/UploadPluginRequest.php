<?php

namespace Botble\Tpuploader\Http\Requests;

use Botble\Support\Http\Requests\Request;

class UploadPluginRequest extends Request
{
    public function rules(): array
    {
        return [
            'plugin_archive' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'activate' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'plugin_archive' => trans('plugins/tpuploader::tpuploader.plugin_archive'),
        ];
    }
}
