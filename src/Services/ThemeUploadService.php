<?php

namespace Botble\Tpuploader\Services;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Supports\Zipper;
use Botble\Theme\Facades\Manager as ThemeManager;
use Botble\Theme\Services\ThemeService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ThemeUploadService
{
    public function __construct(
        protected Filesystem $files,
        protected ThemeService $themeService
    ) {}

    public function upload(UploadedFile $archive, bool $activate = false, bool $allowReplace = false): array
    {
        $workingPath = storage_path('app/tpuploader/theme-imports/'.Str::uuid());
        $archivePath = $workingPath.'/theme.zip';
        $extractPath = $workingPath.'/extract';

        try {
            $this->files->ensureDirectoryExists($workingPath);
            $archive->move($workingPath, 'theme.zip');

            $this->guardArchive($archivePath);

            if (! (new Zipper)->extract($archivePath, $extractPath)) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_invalid'));
            }

            $this->deleteArchiveArtifacts($extractPath);

            $themeRoot = $this->findThemeRoot($extractPath);
            $themeData = BaseHelper::getFileData($themeRoot.'/theme.json');

            if (empty($themeData)) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_invalid'));
            }

            $theme = $this->resolveThemeFolderName($themeRoot, $extractPath, $themeData);
            $themeName = Arr::get($themeData, 'name', $theme);
            $themePath = theme_path($theme);

            if (! $this->files->isDirectory($themePath)) {
                return $this->installTheme($theme, $themeName, $themeRoot, $themePath, $activate);
            }

            if (! $allowReplace) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_already_exists', ['name' => $theme]));
            }

            return $this->replaceTheme($theme, $themeName, $themeRoot, $themePath, $activate, $workingPath);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                BaseHelper::logError($exception);
            }

            return [
                'error' => true,
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : trans('plugins/tpuploader::tpuploader.theme_upload_failed'),
            ];
        } finally {
            $this->files->deleteDirectory($workingPath);
        }
    }

    protected function installTheme(
        string $theme,
        string $themeName,
        string $themeRoot,
        string $themePath,
        bool $activate
    ): array {
        if (! $this->files->moveDirectory($themeRoot, $themePath)) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_move_failed'));
        }

        ThemeManager::refreshThemes();

        if (! $activate) {
            return [
                'error' => false,
                'message' => trans('plugins/tpuploader::tpuploader.theme_upload_success', ['name' => $themeName]),
            ];
        }

        $result = $this->themeService->activate($theme);

        if ($result['error']) {
            return [
                'error' => true,
                'message' => trans('plugins/tpuploader::tpuploader.theme_upload_activate_failed', [
                    'name' => $themeName,
                    'message' => $result['message'],
                ]),
            ];
        }

        return [
            'error' => false,
            'message' => trans('plugins/tpuploader::tpuploader.theme_upload_and_activate_success', ['name' => $themeName]),
        ];
    }

    protected function replaceTheme(
        string $theme,
        string $themeName,
        string $themeRoot,
        string $themePath,
        bool $activate,
        string $workingPath
    ): array {
        $backupPath = $workingPath.'/backup/theme';
        $isActiveTheme = setting('theme') === $theme;

        if (! $this->backupExistingDirectory($themePath, $backupPath)) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_replace_backup_failed'));
        }

        if (! $this->files->moveDirectory($themeRoot, $themePath)) {
            $message = trans('plugins/tpuploader::tpuploader.theme_archive_move_failed');

            if (! $this->restoreBackedUpDirectory($backupPath, $themePath)) {
                $message = trans('plugins/tpuploader::tpuploader.theme_replace_restore_failed', [
                    'message' => $message,
                ]);
            }

            throw new RuntimeException($message);
        }

        ThemeManager::refreshThemes();

        try {
            $published = $this->themeService->publishAssets($theme);

            if ($published['error']) {
                throw new RuntimeException($published['message']);
            }
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                BaseHelper::logError($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : trans('plugins/tpuploader::tpuploader.theme_upload_failed');

            if (! $this->restoreBackedUpDirectory($backupPath, $themePath)) {
                $message = trans('plugins/tpuploader::tpuploader.theme_replace_restore_failed', [
                    'message' => $message,
                ]);
            }

            ThemeManager::refreshThemes();

            return [
                'error' => true,
                'message' => $message,
            ];
        }

        if ($activate && ! $isActiveTheme) {
            $result = $this->themeService->activate($theme);

            if ($result['error']) {
                return [
                    'error' => true,
                    'message' => trans('plugins/tpuploader::tpuploader.theme_update_activate_failed', [
                        'name' => $themeName,
                        'message' => $result['message'],
                    ]),
                ];
            }

            $this->files->deleteDirectory($backupPath);

            return [
                'error' => false,
                'message' => trans('plugins/tpuploader::tpuploader.theme_update_and_activate_success', ['name' => $themeName]),
            ];
        }

        $this->files->deleteDirectory($backupPath);

        return [
            'error' => false,
            'message' => trans('plugins/tpuploader::tpuploader.theme_update_success', ['name' => $themeName]),
        ];
    }

    protected function guardArchive(string $archivePath): void
    {
        if (! class_exists('ZipArchive', false)) {
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_invalid'));
        }

        try {
            if ($zip->numFiles === 0) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_invalid'));
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (! is_string($name) || $name === '') {
                    continue;
                }

                $path = str_replace('\\', '/', $name);

                if ($this->isUnsafePath($path)) {
                    throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_unsafe'));
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

    protected function findThemeRoot(string $extractPath): string
    {
        $themeJsonFiles = [];

        foreach ($this->files->allFiles($extractPath) as $file) {
            if ($file->getFilename() !== 'theme.json') {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'__MACOSX'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $themeJsonFiles[] = $file->getPathname();
        }

        if (! count($themeJsonFiles)) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_missing'));
        }

        if (count($themeJsonFiles) > 1) {
            throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_multiple'));
        }

        return dirname($themeJsonFiles[0]);
    }

    protected function resolveThemeFolderName(string $themeRoot, string $extractPath, array $themeData): string
    {
        $candidates = [
            Arr::last(explode('/', (string) Arr::get($themeData, 'id'))),
            realpath($themeRoot) !== realpath($extractPath) ? basename($themeRoot) : null,
            Arr::get($themeData, 'name'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeThemeFolderName((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_name_required'));
    }

    protected function normalizeThemeFolderName(string $candidate): string
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
}
