<?php

namespace Botble\Tpuploader\Services;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Supports\Zipper;
use Botble\PluginManagement\PluginManifest;
use Botble\PluginManagement\Services\PluginService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
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
        protected PluginService $pluginService,
        protected PluginManifest $pluginManifest
    ) {}

    public function upload(UploadedFile $archive, bool $activate = false, bool $skipUpdate = false): array
    {
        $workingPath = storage_path('app/tpuploader/plugin-imports/'.Str::uuid());
        $archivePath = $workingPath.'/plugin.zip';
        $extractPath = $workingPath.'/extract';

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
            $pluginName = Arr::get($pluginData, 'name', $plugin);
            $pluginPath = plugin_path($plugin);

            if (! $this->files->isDirectory($pluginPath)) {
                return $this->installPlugin($plugin, $pluginName, $pluginRoot, $pluginPath, $activate);
            }

            return $this->replacePlugin($plugin, $pluginName, $pluginRoot, $pluginPath, $activate, $workingPath, $skipUpdate);
        } catch (Throwable $exception) {
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

    protected function installPlugin(
        string $plugin,
        string $pluginName,
        string $pluginRoot,
        string $pluginPath,
        bool $activate
    ): array {
        if (! $this->files->moveDirectory($pluginRoot, $pluginPath)) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_archive_move_failed'));
        }

        try {
            $this->pluginService->validatePlugin($plugin, true);
        } catch (Throwable $exception) {
            $this->files->deleteDirectory($pluginPath);

            throw $exception;
        }

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
    }

    protected function replacePlugin(
        string $plugin,
        string $pluginName,
        string $pluginRoot,
        string $pluginPath,
        bool $activate,
        string $workingPath,
        bool $skipUpdate
    ): array {
        $backupPath = $workingPath.'/backup/plugin';
        $wasActive = in_array($plugin, get_active_plugins());

        if (! $this->backupExistingDirectory($pluginPath, $backupPath)) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.plugin_replace_backup_failed'));
        }

        if (! $this->files->moveDirectory($pluginRoot, $pluginPath)) {
            $message = trans('plugins/tpuploader::tpuploader.plugin_archive_move_failed');

            if (! $this->restoreBackedUpDirectory($backupPath, $pluginPath)) {
                $message = trans('plugins/tpuploader::tpuploader.plugin_replace_restore_failed', [
                    'message' => $message,
                ]);
            }

            throw new RuntimeException($message);
        }

        try {
            $this->pluginService->validatePlugin($plugin, true);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : trans('plugins/tpuploader::tpuploader.plugin_upload_failed');

            if (! $this->restoreBackedUpDirectory($backupPath, $pluginPath)) {
                $message = trans('plugins/tpuploader::tpuploader.plugin_replace_restore_failed', [
                    'message' => $message,
                ]);
            }

            if (! $exception instanceof RuntimeException) {
                BaseHelper::logError($exception);
            }

            throw new RuntimeException($message);
        }

        if ($skipUpdate) {
            return $this->finishPluginFilesOnlyReplacement($plugin, $pluginName, $backupPath, $activate, $wasActive);
        }

        $migrationsStarted = false;

        $result = $this->pluginService->updatePlugin($plugin, function () use (
            $activate,
            $backupPath,
            $plugin,
            $pluginName,
            $pluginPath,
            &$migrationsStarted,
            $wasActive
        ) {
            try {
                $published = $this->pluginService->publishAssets($plugin);

                if ($published['error']) {
                    throw new RuntimeException($published['message']);
                }

                $this->pluginService->publishTranslations($plugin);

                $migrationsStarted = true;

                $this->pluginService->runMigrations($plugin);

                if ($activate && ! $wasActive) {
                    $activationResult = $this->pluginService->activate($plugin);

                    if ($activationResult['error']) {
                        return [
                            'error' => true,
                            'message' => trans('plugins/tpuploader::tpuploader.plugin_update_activate_failed', [
                                'name' => $pluginName,
                                'message' => $activationResult['message'],
                            ]),
                        ];
                    }

                    $this->files->deleteDirectory($backupPath);

                    return [
                        'error' => false,
                        'message' => trans('plugins/tpuploader::tpuploader.plugin_update_and_activate_success', ['name' => $pluginName]),
                    ];
                }

                $this->files->deleteDirectory($backupPath);

                return [
                    'error' => false,
                    'message' => trans('plugins/tpuploader::tpuploader.plugin_update_success', ['name' => $pluginName]),
                ];
            } catch (Throwable $exception) {
                if (! $exception instanceof RuntimeException) {
                    BaseHelper::logError($exception);
                }

                $message = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : trans('plugins/tpuploader::tpuploader.plugin_upload_failed');

                if (! $migrationsStarted) {
                    if (! $this->restoreBackedUpDirectory($backupPath, $pluginPath)) {
                        $message = trans('plugins/tpuploader::tpuploader.plugin_replace_restore_failed', [
                            'message' => $message,
                        ]);
                    }

                    return [
                        'error' => true,
                        'message' => $message,
                    ];
                }

                return [
                    'error' => true,
                    'message' => trans('plugins/tpuploader::tpuploader.plugin_update_failed_after_replace', [
                        'name' => $pluginName,
                        'message' => $message,
                    ]),
                ];
            }
        });

        return $this->normalizeUpdateResult($result);
    }

    protected function finishPluginFilesOnlyReplacement(
        string $plugin,
        string $pluginName,
        string $backupPath,
        bool $activate,
        bool $wasActive
    ): array {
        $this->pluginService->clearCache();
        $this->pluginManifest->generateManifest();

        if ($activate && ! $wasActive) {
            $activationResult = $this->pluginService->activate($plugin);

            if ($activationResult['error']) {
                return [
                    'error' => true,
                    'message' => trans('plugins/tpuploader::tpuploader.plugin_replace_activate_failed', [
                        'name' => $pluginName,
                        'message' => $activationResult['message'],
                    ]),
                ];
            }

            $this->files->deleteDirectory($backupPath);

            return [
                'error' => false,
                'message' => trans('plugins/tpuploader::tpuploader.plugin_replace_and_activate_success', ['name' => $pluginName]),
            ];
        }

        $this->files->deleteDirectory($backupPath);

        return [
            'error' => false,
            'message' => trans('plugins/tpuploader::tpuploader.plugin_replace_success', ['name' => $pluginName]),
        ];
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

    protected function backupExistingDirectory(string $currentPath, string $backupPath): bool
    {
        $this->files->ensureDirectoryExists(dirname($backupPath));

        return $this->files->moveDirectory($currentPath, $backupPath);
    }

    protected function restoreBackedUpDirectory(string $backupPath, string $targetPath): bool
    {
        if (! $this->files->isDirectory($backupPath)) {
            return false;
        }

        if ($this->files->isDirectory($targetPath)) {
            $this->files->deleteDirectory($targetPath);
        }

        $this->files->ensureDirectoryExists(dirname($targetPath));

        return $this->files->moveDirectory($backupPath, $targetPath);
    }

    protected function normalizeUpdateResult(mixed $result): array
    {
        if ($result instanceof JsonResponse) {
            return $result->getData(true);
        }

        return is_array($result)
            ? $result
            : [
                'error' => true,
                'message' => trans('plugins/tpuploader::tpuploader.plugin_upload_failed'),
            ];
    }
}
