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

    public function upload(UploadedFile $archive, bool $activate = false): array
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

            if ($this->files->isDirectory(theme_path($theme))) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_already_exists', ['name' => $theme]));
            }

            if (! $this->files->moveDirectory($themeRoot, theme_path($theme))) {
                throw new RuntimeException(trans('plugins/tpuploader::tpuploader.theme_archive_move_failed'));
            }

            ThemeManager::refreshThemes();

            $themeName = Arr::get($themeData, 'name', $theme);

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
}
