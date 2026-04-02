<?php

namespace Botble\Tpuploader\Services;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Supports\Zipper;
use Botble\PluginManagement\Services\PluginService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class PluginUploadService
{
    public function __construct(
        protected Filesystem $files,
        protected PluginService $pluginService
    ) {}

    public function upload(UploadedFile $archive, bool $activate = false): array
    {
        $workingPath = storage_path('app/tpuploader/plugin-imports/'.Str::uuid());
        $archivePath = $workingPath.'/plugin.zip';
        $extractPath = $workingPath.'/extract';
        $pluginPath = null;
        $removeInstalledPluginOnError = false;

        try {
            $this->files->ensureDirectoryExists($workingPath);
            $archive->move($workingPath, 'plugin.zip');

            $this->guardArchive($archivePath);

            if (! (new Zipper)->extract($archivePath, $extractPath)) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_invalid'));
            }

            $this->deleteArchiveArtifacts($extractPath);

            $pluginRoot = $this->findPluginRoot($extractPath);
            $pluginData = BaseHelper::getFileData($pluginRoot.'/plugin.json');

            if (empty($pluginData)) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_invalid'));
            }

            $plugin = $this->resolvePluginFolderName($pluginRoot, $extractPath, $pluginData);
            $pluginPath = plugin_path($plugin);

            if ($this->files->isDirectory($pluginPath)) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_already_exists', ['name' => $plugin]));
            }

            if (! $this->files->moveDirectory($pluginRoot, $pluginPath)) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_move_failed'));
            }

            $removeInstalledPluginOnError = true;

            $this->pluginService->validatePlugin($plugin, true);

            $removeInstalledPluginOnError = false;

            $pluginName = Arr::get($pluginData, 'name', $plugin);

            if (! $activate) {
                return [
                    'error' => false,
                    'message' => trans('plugins/tpuploader::tpuploader.plugin_upload_success', ['name' => $pluginName]),
                ];
            }

            $result = $this->pluginService->activate($plugin);

            if ($result['error']) {
                return [
                    'error' => true,
                    'message' => trans('plugins/tpuploader::tpuploader.plugin_upload_activate_failed', [
                        'name' => $pluginName,
                        'message' => $result['message'],
                    ]),
                ];
            }

            return [
                'error' => false,
                'message' => trans('plugins/tpuploader::tpuploader.plugin_upload_and_activate_success', ['name' => $pluginName]),
            ];
        } catch (Throwable $exception) {
            if ($pluginPath && $removeInstalledPluginOnError && $this->files->isDirectory($pluginPath)) {
                $this->files->deleteDirectory($pluginPath);
            }

            if (! $exception instanceof RuntimeException) {
                BaseHelper::logError($exception);
            }

            return [
                'error' => true,
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : trans('plugins/tpuploader::tpuploader.plugin_upload_failed'),
            ];
        } finally {
            $this->files->deleteDirectory($workingPath);
        }
    }

    protected function guardArchive(string $archivePath): void
    {
        if (! class_exists('ZipArchive', false)) {
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_invalid'));
        }

        try {
            if ($zip->numFiles === 0) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_invalid'));
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (! is_string($name) || $name === '') {
                    continue;
                }

                $path = str_replace('\\', '/', $name);

                if ($this->isUnsafePath($path)) {
                    throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_unsafe'));
                }
            }
        } finally {
            $zip->close();
        }
    }

    protected function isUnsafePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '../'])
            || str_contains($path, '/../')
            || preg_match('/^[A-Za-z]:\//', $path);
    }

    protected function deleteArchiveArtifacts(string $extractPath): void
    {
        $this->files->deleteDirectory($extractPath.DIRECTORY_SEPARATOR.'__MACOSX');

        foreach ($this->files->allFiles($extractPath) as $file) {
            if ($file->getFilename() === '.DS_Store') {
                $this->files->delete($file->getPathname());
            }
        }
    }

    protected function findPluginRoot(string $extractPath): string
    {
        $pluginJsonFiles = [];

        foreach ($this->files->allFiles($extractPath) as $file) {
            if ($file->getFilename() !== 'plugin.json') {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'__MACOSX'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $pluginJsonFiles[] = $file->getPathname();
        }

        if (! count($pluginJsonFiles)) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_missing'));
        }

        if (count($pluginJsonFiles) > 1) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_multiple'));
        }

        return dirname($pluginJsonFiles[0]);
    }

    protected function resolvePluginFolderName(string $pluginRoot, string $extractPath, array $pluginData): string
    {
        $candidates = [
            realpath($pluginRoot) !== realpath($extractPath) ? basename($pluginRoot) : null,
            Arr::last(explode('/', (string) Arr::get($pluginData, 'id'))),
            Arr::get($pluginData, 'name'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = $this->normalizePluginFolderName((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_name_required'));
    }

    protected function normalizePluginFolderName(string $candidate): string
    {
        $candidate = trim(str_replace('\\', '/', $candidate));

        if ($candidate === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $candidate)));
        $candidate = strtolower(end($segments) ?: '');
        $candidate = preg_replace('/[^a-z0-9_-]+/', '-', $candidate) ?: '';

        return trim($candidate, '-_');
    }
}
