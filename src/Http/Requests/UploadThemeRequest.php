<?php

namespace Botble\Tpuploader\Http\Requests;

use Botble\Support\Http\Requests\Request;

class UploadThemeRequest extends Request
{
    public function rules(): array
    {
        return [
            'theme_archive' => ['required_without:theme_archives', 'nullable', 'file', 'mimes:zip', 'max:102400'],
            'theme_archives' => ['required_without:theme_archive', 'nullable', 'array'],
            'theme_archives.*' => ['file', 'mimes:zip', 'max:102400'],
            'skip_update' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'theme_archive' => trans('plugins/tpuploader::tpuploader.theme_archive'),
            'theme_archives' => trans('plugins/tpuploader::tpuploader.theme_archive'),
            'theme_archives.*' => trans('plugins/tpuploader::tpuploader.theme_archive'),
        ];
    }
}
