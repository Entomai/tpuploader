<?php

use Botble\Base\Facades\AdminHelper;
use Botble\Tpuploader\Http\Controllers\TPUploaderController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function () {
    Route::group(['prefix' => 'tpuploaders', 'as' => 'tpuploader.'], function () {
        Route::post('plugins/upload', [
            'as' => 'plugins.upload',
            'uses' => TPUploaderController::class.'@uploadPlugin',
            'middleware' => 'preventDemo',
            'permission' => 'plugins.index',
        ]);

        Route::post('themes/upload', [
            'as' => 'themes.upload',
            'uses' => TPUploaderController::class.'@uploadTheme',
            'middleware' => 'preventDemo',
            'permission' => 'theme.index',
        ]);
    });
});
