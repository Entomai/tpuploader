<?php

namespace Botble\Tpuploader\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Tpuploader\Http\Requests\UploadPluginRequest;
use Botble\Tpuploader\Http\Requests\UploadThemeRequest;
use Botble\Tpuploader\Services\PluginUploadService;
use Botble\Tpuploader\Services\ThemeUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class TPUploaderController extends BaseController
{
    public function uploadTheme(UploadThemeRequest $request, ThemeUploadService $themeUploadService): JsonResponse|RedirectResponse
    {
        $results = [];

        foreach ($this->uploadedFiles($request, 'theme_archive', 'theme_archives') as $file) {
            $results[] = $this->formatUploadResult($file, $themeUploadService->upload(
                $file,
                false,
                $request->boolean('skip_update')
            ));
        }

        return $this->respondToUpload($request, $results, 'theme.index');
    }

    public function uploadPlugin(UploadPluginRequest $request, PluginUploadService $pluginUploadService): JsonResponse|RedirectResponse
    {
        $results = [];

        foreach ($this->uploadedFiles($request, 'plugin_archive', 'plugin_archives') as $file) {
            $results[] = $this->formatUploadResult($file, $pluginUploadService->upload(
                $file,
                $request->boolean('activate'),
                $request->boolean('skip_update')
            ));
        }

        return $this->respondToUpload($request, $results, 'plugins.index');
    }

    protected function uploadedFiles(Request $request, string $singleField, string $multipleField): array
    {
        $files = $request->file($multipleField, []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files) && $files) {
            return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
        }

        $file = $request->file($singleField);

        return $file instanceof UploadedFile ? [$file] : [];
    }

    protected function formatUploadResult(UploadedFile $file, array $result): array
    {
        return [
            'file' => $file->getClientOriginalName(),
            'error' => (bool) ($result['error'] ?? true),
            'message' => (string) ($result['message'] ?? trans('plugins/tpuploader::tpuploader.upload_request_failed')),
        ];
    }

    protected function respondToUpload(Request $request, array $results, string $route): JsonResponse|RedirectResponse
    {
        if (! $results) {
            $results[] = [
                'file' => null,
                'error' => true,
                'message' => trans('plugins/tpuploader::tpuploader.upload_no_files'),
            ];
        }

        $failed = count(array_filter($results, fn (array $result) => $result['error']));
        $successful = count($results) - $failed;
        $hasError = $failed > 0;
        $message = count($results) === 1
            ? $results[0]['message']
            : trans('plugins/tpuploader::tpuploader.upload_batch_finished', [
                'success' => $successful,
                'failed' => $failed,
            ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(count($results) === 1 ? $results[0] : [
                'error' => $hasError,
                'message' => $message,
                'files' => $results,
            ]);
        }

        return redirect()
            ->route($route)
            ->with($hasError ? 'error_msg' : 'success_msg', $message);
    }
}
