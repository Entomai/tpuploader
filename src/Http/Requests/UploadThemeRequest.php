<?php

namespace Botble\Tpuploader\Http\Requests;

use Botble\Support\Http\Requests\Request;

class UploadThemeRequest extends Request
{
    public function rules(): array
    {
        return [
            'theme_archive' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'activate' => ['nullable', 'boolean'],
            'allow_replace' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'theme_archive' => trans('plugins/tpuploader::tpuploader.theme_archive'),
        ];
    }
}
