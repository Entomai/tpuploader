<?php

namespace Botble\Tpuploader;

use Illuminate\Support\Facades\Schema;
use Botble\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('TPUploaders');
        Schema::dropIfExists('TPUploaders_translations');
    }
}
