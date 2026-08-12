@extends(BaseHelper::getAdminMasterLayoutTemplate())

@php
    $pluginArchiveError = $errors->first('plugin_archive') ?: $errors->first('plugin_archives') ?: $errors->first('plugin_archives.*');
    $isPluginUploaderOpen = $pluginArchiveError !== '';
    $pluginUploaderClasses = $isPluginUploaderOpen ? 'collapse mb-4 show' : 'collapse mb-4';
    $pluginUploaderExpanded = $isPluginUploaderOpen ? 'true' : 'false';
    $canEditPlugins = auth()->user()->hasPermission('plugins.edit');
    $canRemovePlugins = auth()->user()->hasPermission('plugins.remove');
    $canBulkManagePlugins = $canEditPlugins || $canRemovePlugins;
@endphp

@push('header-action')
    <x-core::button
        type="button"
        color="primary"
        icon="ti ti-cloud-upload"
        data-bs-toggle="collapse"
        data-bs-target="#plugin-uploader-collapse"
        aria-controls="plugin-uploader-collapse"
        aria-expanded="{{ $pluginUploaderExpanded }}"
    >
        {{ trans('plugins/tpuploader::tpuploader.upload_plugin') }}
    </x-core::button>

    @if (
        $isEnabledMarketplaceFeature =
            config('packages.plugin-management.general.enable_marketplace_feature') &&
            auth()->user()->hasPermission('plugins.marketplace'))
        <x-core::button
            tag="a"
            :href="route('plugins.new')"
            color="primary"
            icon="ti ti-plus"
            class="ms-auto"
        >
            {{ trans('packages/plugin-management::plugin.plugins_add_new') }}
        </x-core::button>
    @endif

    {!! apply_filters('plugin_management_installed_header_actions', null) !!}
@endpush

@section('content')
    <div
        id="plugin-uploader-collapse"
        class="{{ $pluginUploaderClasses }}"
    >
        <x-core::card class="plugin-upload-card">
            <x-core::card.body>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-xl-8">
                        <h3 class="card-title mb-1">
                            {{ trans('plugins/tpuploader::tpuploader.upload_plugin') }}
                        </h3>
                        <p class="text-secondary mb-0">
                            {{ trans('plugins/tpuploader::tpuploader.upload_plugin_description') }}
                        </p>
                    </div>
                    <div class="col-12">
                        <form
                            action="{{ route('tpuploader.plugins.upload') }}"
                            method="POST"
                            enctype="multipart/form-data"
                            class="row g-3 align-items-end"
                            data-bb-toggle="tpuploader-upload-form"
                            data-upload-field="plugin_archive"
                            data-list-title="{{ trans('plugins/tpuploader::tpuploader.plugin_upload_list_title') }}"
                            data-waiting-label="{{ trans('plugins/tpuploader::tpuploader.upload_waiting') }}"
                            data-uploading-label="{{ trans('plugins/tpuploader::tpuploader.upload_uploading') }}"
                            data-success-label="{{ trans('plugins/tpuploader::tpuploader.upload_success') }}"
                            data-error-label="{{ trans('plugins/tpuploader::tpuploader.upload_error') }}"
                            data-log-label="{{ trans('plugins/tpuploader::tpuploader.upload_log') }}"
                            data-no-files-message="{{ trans('plugins/tpuploader::tpuploader.upload_no_files') }}"
                            data-request-failed-message="{{ trans('plugins/tpuploader::tpuploader.upload_request_failed') }}"
                            data-batch-success-message="{{ trans('plugins/tpuploader::tpuploader.upload_batch_success') }}"
                            data-batch-error-message="{{ trans('plugins/tpuploader::tpuploader.upload_batch_error') }}"
                        >
                            @csrf

                            <div class="col-12 col-lg-7">
                                <label
                                    for="plugin_archive"
                                    class="form-label required"
                                >
                                    {{ trans('plugins/tpuploader::tpuploader.plugin_archive') }}
                                </label>
                                <input
                                    id="plugin_archive"
                                    type="file"
                                    name="plugin_archives[]"
                                    accept=".zip,application/zip"
                                    class="form-control {{ $pluginArchiveError ? 'is-invalid' : '' }}"
                                    data-tpuploader-file-input
                                    multiple
                                    required
                                >
                                @if ($pluginArchiveError)
                                    <div class="invalid-feedback">{{ $pluginArchiveError }}</div>
                                @endif
                                <div class="form-hint">
                                    {{ trans('plugins/tpuploader::tpuploader.plugin_archive_help') }}
                                </div>
                            </div>

                            <div class="col-12 col-lg-auto">
                                <label class="form-check form-switch mt-lg-4 pt-lg-1">
                                    <input
                                        type="checkbox"
                                        name="skip_update"
                                        value="1"
                                        class="form-check-input"
                                        @checked(old('skip_update'))
                                    >
                                    <span class="form-check-label">
                                        {{ trans('plugins/tpuploader::tpuploader.skip_update') }}
                                    </span>
                                </label>
                                <div class="form-hint ps-lg-5">
                                    {{ trans('plugins/tpuploader::tpuploader.skip_update_plugin_help') }}
                                </div>
                            </div>

                            <div class="col-12 col-lg-auto">
                                <label class="form-check form-switch mt-lg-4 pt-lg-1">
                                    <input
                                        type="checkbox"
                                        name="activate"
                                        value="1"
                                        class="form-check-input"
                                        @checked(old('activate'))
                                    >
                                    <span class="form-check-label">
                                        {{ trans('plugins/tpuploader::tpuploader.activate_after_upload') }}
                                    </span>
                                </label>
                            </div>

                            <div class="col-12 col-lg-auto">
                                <label class="form-check form-switch mt-lg-4 pt-lg-1">
                                    <input
                                        type="checkbox"
                                        name="recompile_assets"
                                        value="1"
                                        class="form-check-input"
                                        @checked(old('recompile_assets'))
                                    >
                                    <span class="form-check-label">
                                        Recompile Assets (NPM)
                                    </span>
                                </label>
                            </div>

                            <div class="col-12 col-lg-auto">
                                <label class="form-check form-switch mt-lg-4 pt-lg-1">
                                    <input
                                        type="checkbox"
                                        name="clear_view_cache"
                                        value="1"
                                        class="form-check-input"
                                        checked
                                    >
                                    <span class="form-check-label">
                                        Refresh Compiled Views
                                    </span>
                                </label>
                            </div>

                            <div class="col-12 col-lg-auto">
                                <x-core::button
                                    type="submit"
                                    color="primary"
                                    icon="ti ti-cloud-upload"
                                    class="w-100"
                                >
                                    {{ trans('plugins/tpuploader::tpuploader.upload') }}
                                </x-core::button>
                            </div>

                            <div class="col-12">
                                <div
                                    class="tpuploader-upload-list d-none"
                                    data-tpuploader-upload-list
                                ></div>
                            </div>
                        </form>
                    </div>
                </div>
            </x-core::card.body>
        </x-core::card>
    </div>

    @if ($plugins->isNotEmpty())
        <x-core::card class="mb-4">
            <x-core::card.body class="p-0">
                <div class="p-3 border-bottom">
                    <div class="w-100" style="max-width: 420px;">
                        <x-core::form.text-input
                            type="search"
                            name="search"
                            :placeholder="trans('packages/plugin-management::plugin.search')"
                            :group-flat="true"
                            data-bb-toggle="change-search"
                        >
                            <x-slot:prepend>
                                <span class="input-group-text">
                                    <x-core::icon name="ti ti-search" />
                                </span>
                            </x-slot:prepend>
                        </x-core::form.text-input>
                    </div>
                </div>

                <div class="p-3 d-flex flex-column flex-xl-row gap-3 justify-content-between align-items-xl-center">
                    @if ($canBulkManagePlugins)
                        <div class="d-flex flex-wrap align-items-center gap-3 flex-shrink-0">
                            <label class="form-check mb-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    data-tpuploader-select-visible
                                >
                                <span class="form-check-label">
                                    {{ trans('plugins/tpuploader::tpuploader.select_visible_plugins') }}
                                </span>
                            </label>

                            <div class="dropdown">
                                <x-core::button
                                    type="button"
                                    class="dropdown-toggle"
                                    icon="ti ti-adjustments-horizontal"
                                    data-bs-toggle="dropdown"
                                    data-tpuploader-bulk-actions
                                    disabled
                                >
                                    {{ trans('plugins/tpuploader::tpuploader.actions') }}
                                    (<span data-tpuploader-selected-count>0</span>)
                                </x-core::button>
                                <div class="dropdown-menu dropdown-menu-start">
                                    @if ($canEditPlugins)
                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            data-tpuploader-bulk-action="activate"
                                            data-url="{{ route('tpuploader.plugins.activate') }}"
                                        >
                                            <x-core::icon name="ti ti-player-play" class="me-2 text-success" />
                                            {{ trans('plugins/tpuploader::tpuploader.bulk_activate') }}
                                            (<span data-tpuploader-action-count="activate">0</span>)
                                        </button>
                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            data-tpuploader-bulk-action="deactivate"
                                            data-url="{{ route('tpuploader.plugins.deactivate') }}"
                                        >
                                            <x-core::icon name="ti ti-player-pause" class="me-2 text-warning" />
                                            {{ trans('plugins/tpuploader::tpuploader.bulk_deactivate') }}
                                            (<span data-tpuploader-action-count="deactivate">0</span>)
                                        </button>
                                    @endif
                                    @if ($canRemovePlugins)
                                        <div class="dropdown-divider"></div>
                                        <button
                                            type="button"
                                            class="dropdown-item text-danger"
                                            data-tpuploader-bulk-action="remove"
                                            data-url="{{ route('tpuploader.plugins.remove') }}"
                                        >
                                            <x-core::icon name="ti ti-trash" class="me-2" />
                                            {{ trans('plugins/tpuploader::tpuploader.bulk_remove') }}
                                            (<span data-tpuploader-action-count="remove">0</span>)
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div></div>
                    @endif

                    <div class="flex-shrink-0">
                        <div class="d-block d-sm-none dropdown">
                            <x-core::button
                                class="dropdown-toggle"
                                data-bs-toggle="dropdown"
                            >
                                <span
                                    data-bb-toggle="status-filter-label"
                                    class="ms-1"
                                >
                                    {{ $filterStatuses[array_key_first($filterStatuses)] }}
                                    (<span
                                        data-bb-toggle="plugins-count"
                                        data-status="{{ array_key_first($filterStatuses) }}"
                                    >{{ $plugins->count() }}</span>)
                                </span>
                            </x-core::button>
                            <div
                                class="dropdown-menu dropdown-menu-end"
                                data-popper-placement="bottom-end"
                            >
                                @foreach ($filterStatuses as $key => $value)
                                    <button
                                        @class(['dropdown-item', 'active' => $loop->first])
                                        type="button"
                                        data-value="{{ $key }}"
                                        data-bb-toggle="change-filter-plugin-status"
                                    >
                                        {{ $value }}
                                        (<span
                                            data-bb-toggle="plugins-count"
                                            data-status="{{ $key }}"
                                        >0</span>)
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="d-none d-sm-flex form-selectgroup">
                            @foreach ($filterStatuses as $key => $value)
                                <label class="form-selectgroup-item">
                                    <input
                                        type="radio"
                                        name="status"
                                        value="{{ $key }}"
                                        data-bb-toggle="change-filter-plugin-status"
                                        class="form-selectgroup-input"
                                        @checked($loop->first)
                                    />
                                    <span class="form-selectgroup-label">
                                        {{ $value }}
                                        (<span
                                            data-bb-toggle="plugins-count"
                                            data-status="{{ $key }}"
                                        >0</span>)
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-core::card.body>
        </x-core::card>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4 plugin-list">
            @foreach ($plugins as $plugin)
                <div
                    class="col plugin-item"
                    data-name="{{ $plugin->name }}"
                    data-author="{{ $plugin->author }}"
                    data-description="{{ $plugin->description }}"
                    data-status="{{ $plugin->status ? 'activated' : 'not-activated' }}"
                >
                    <x-core::card class="h-100 plugin-card">
                        <div class="position-relative">
                            @if ($canBulkManagePlugins)
                                <label
                                    class="plugin-selection-control position-absolute top-0 start-0 m-2"
                                    title="{{ trans('plugins/tpuploader::tpuploader.actions') }}: {{ $plugin->name }}"
                                >
                                    <input
                                        type="checkbox"
                                        class="form-check-input m-0"
                                        value="{{ $plugin->path }}"
                                        data-plugin-name="{{ $plugin->name }}"
                                        data-plugin-status="{{ $plugin->status ? 'activated' : 'not-activated' }}"
                                        data-tpuploader-plugin-selection
                                        aria-label="{{ trans('plugins/tpuploader::tpuploader.actions') }}: {{ $plugin->name }}"
                                    >
                                </label>
                            @endif

                            <div
                                @class(['card-img-top d-flex align-items-center justify-content-center', 'plugin-image-placeholder' => !$plugin->image])
                                @style([
                                    'height: 120px',
                                    "background-image: url('$plugin->image'); background-size: cover; background-position: center" => $plugin->image,
                                ])
                            >
                                @if (!$plugin->image)
                                    <div class="avatar avatar-xl rounded plugin-icon-wrapper">
                                        <x-core::icon
                                            name="ti ti-puzzle"
                                            class="text-primary"
                                            style="font-size: 2rem;"
                                        />
                                    </div>
                                @endif
                            </div>
                            <div class="position-absolute top-0 end-0 m-2">
                                <span @class([
                                    'badge',
                                    'bg-green-lt text-green' => $plugin->status,
                                    'bg-secondary-lt text-secondary' => !$plugin->status,
                                ])>
                                    <x-core::icon
                                        :name="$plugin->status ? 'ti ti-circle-check' : 'ti ti-circle-x'"
                                        class="me-1"
                                    />
                                    {{ $plugin->status ? trans('packages/plugin-management::plugin.activated') : trans('packages/plugin-management::plugin.deactivated') }}
                                </span>
                            </div>
                        </div>

                        <x-core::card.body class="d-flex flex-column">
                            <div class="mb-3">
                                <h4 class="card-title mb-1" title="{{ $plugin->name }}">
                                    <span class="text-truncate d-block">{{ $plugin->name }}</span>
                                </h4>
                                @if ($plugin->description)
                                    <p
                                        class="text-secondary small mb-0"
                                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5em;"
                                        title="{{ $plugin->description }}"
                                    >
                                        {{ $plugin->description }}
                                    </p>
                                @endif
                            </div>

                            <div class="mt-auto pt-3 border-top">
                                <div class="d-flex flex-wrap gap-2 text-secondary small">
                                    @if (!config('packages.plugin-management.general.hide_plugin_author', false) && $plugin->author)
                                        <div class="d-flex align-items-center gap-1">
                                            <x-core::icon name="ti ti-user" class="text-muted" />
                                            @if (!empty($plugin->url))
                                                <a
                                                    href="{{ $plugin->url }}"
                                                    target="_blank"
                                                    class="text-reset text-decoration-none"
                                                >{{ $plugin->author }}</a>
                                            @else
                                                <span>{{ $plugin->author }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($plugin->version)
                                        <div class="d-flex align-items-center gap-1 ms-auto">
                                            <x-core::icon name="ti ti-tag" class="text-muted" />
                                            <span>v{{ $plugin->version }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </x-core::card.body>

                        <x-core::card.footer>
                            <div class="btn-list justify-content-center">
                                @if (auth()->user()->hasPermission('plugins.edit'))
                                    <x-core::button
                                        type="button"
                                        size="sm"
                                        :color="$plugin->status ? 'warning' : 'primary'"
                                        class="btn-trigger-change-status"
                                        :icon="$plugin->status ? 'ti ti-player-pause' : 'ti ti-player-play'"
                                        data-plugin="{{ $plugin->path }}"
                                        data-status="{{ $plugin->status }}"
                                        :data-check-requirement-url="route('plugins.check-requirement', ['name' => $plugin->path])"
                                        :data-change-status-url="route('plugins.change.status', ['name' => $plugin->path])"
                                    >
                                        @if ($plugin->status)
                                            {{ trans('packages/plugin-management::plugin.deactivate') }}
                                        @else
                                            {{ trans('packages/plugin-management::plugin.activate') }}
                                        @endif
                                    </x-core::button>
                                @endif

                                @if ($isEnabledMarketplaceFeature)
                                    <x-core::button
                                        class="btn-trigger-update-plugin"
                                        color="success"
                                        size="sm"
                                        icon="ti ti-refresh"
                                        style="display: none;"
                                        data-name="{{ $plugin->path }}"
                                        data-check-update="{{ $plugin->id ?? 'plugin-' . $plugin->path }}"
                                        :data-check-update-url="route('plugins.marketplace.ajax.check-update')"
                                        :data-update-url="route('plugins.marketplace.ajax.update', [
                                            'id' => '__id__',
                                            'name' => $plugin->path,
                                        ])"
                                        data-version="{{ $plugin->version }}"
                                    >
                                        {{ trans('packages/plugin-management::plugin.update') }}
                                    </x-core::button>
                                @endif

                                @if (auth()->user()->hasPermission('plugins.remove'))
                                    <x-core::button
                                        type="button"
                                        size="sm"
                                        color="danger"
                                        :outlined="true"
                                        class="btn-trigger-remove-plugin"
                                        icon="ti ti-trash"
                                        data-plugin="{{ $plugin->path }}"
                                        :data-url="route('plugins.remove', ['plugin' => $plugin->path])"
                                    >
                                        {{ trans('packages/plugin-management::plugin.remove') }}
                                    </x-core::button>
                                @endif
                            </div>
                        </x-core::card.footer>
                    </x-core::card>
                </div>
            @endforeach
        </div>
    @endif

    <x-core::empty-state
        :title="trans('No plugins found')"
        :subtitle="trans('It looks as there are no plugins here.')"
        icon="ti ti-puzzle"
        @style(['display: none' => $plugins->isNotEmpty()])
    />
@stop

@include('plugins/tpuploader::partials.multi-upload')

@if ($canBulkManagePlugins)
    @include('plugins/tpuploader::partials.bulk-actions')
@endif

@push('header')
    <style>
        .plugin-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .plugin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .plugin-card .card-img-top {
            border-bottom: 1px solid var(--bb-border-color);
        }

        .plugin-card .plugin-image-placeholder {
            background: var(--bb-card-cap-bg);
        }

        .plugin-card .plugin-icon-wrapper {
            background: rgba(var(--bb-primary-rgb), 0.1);
        }

        .plugin-card .card-footer {
            background: var(--bb-card-cap-bg);
        }

        .plugin-selection-control {
            z-index: 2;
            display: inline-flex;
            padding: 0.45rem;
            border: 1px solid var(--bb-border-color);
            border-radius: var(--bb-border-radius);
            background: var(--bb-bg-surface);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            cursor: pointer;
        }

        .plugin-selection-control .form-check-input {
            cursor: pointer;
        }

        .tpuploader-bulk-log {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            background: var(--bb-bg-surface-secondary);
        }

        .plugin-upload-card {
            border: 1px solid rgba(14, 165, 233, 0.2);
            background:
                linear-gradient(135deg, rgba(14, 165, 233, 0.08), rgba(255, 255, 255, 0)),
                var(--tblr-bg-surface);
        }
    </style>
@endpush

@push('footer')
    <x-core::modal.action
        id="remove-plugin-modal"
        type="danger"
        :title="trans('packages/plugin-management::plugin.remove_plugin')"
        :description="trans('packages/plugin-management::plugin.remove_plugin_confirm_message')"
        :submit-button-attrs="['id' => 'confirm-remove-plugin-button']"
        :submit-button-label="trans('packages/plugin-management::plugin.remove_plugin_confirm_yes')"
    />

    @if ($isEnabledMarketplaceFeature)
        <x-core::modal
            id="confirm-install-plugin-modal"
            :title="trans('packages/plugin-management::plugin.install_plugin')"
            button-id="confirm-install-plugin-button"
            :button-label="trans('packages/plugin-management::plugin.install')"
        >
            <input
                type="hidden"
                name="plugin_name"
                value=""
            >
            <input
                type="hidden"
                name="ids"
                value=""
            >

            <p id="requirement-message"></p>
        </x-core::modal>
    @endif
@endpush
