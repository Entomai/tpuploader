<?php

namespace Botble\Tpuploader;

use Botble\PluginManagement\Abstracts\PluginOperationAbstract;
use Illuminate\Support\Facades\Schema;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('t_p_uploaders_translations');
        Schema::dropIfExists('t_p_uploaders');
    }
}
