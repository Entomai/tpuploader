<?php

namespace Botble\Tpuploader\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Tpuploader\Http\Requests\UploadPluginRequest;
use Botble\Tpuploader\Http\Requests\UploadThemeRequest;
use Botble\Tpuploader\Services\PluginUploadService;
use Botble\Tpuploader\Services\ThemeUploadService;

class TPUploaderController extends BaseController
{
    public function uploadTheme(UploadThemeRequest $request, ThemeUploadService $themeUploadService)
    {
        $result = $themeUploadService->upload(
            $request->file('theme_archive'),
            $request->boolean('activate')
        );

        return redirect()
            ->route('theme.index')
            ->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
    }

    public function uploadPlugin(UploadPluginRequest $request, PluginUploadService $pluginUploadService)
    {
        $result = $pluginUploadService->upload(
            $request->file('plugin_archive'),
            $request->boolean('activate')
        );

        return redirect()
            ->route('plugins.index')
            ->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
    }
}
