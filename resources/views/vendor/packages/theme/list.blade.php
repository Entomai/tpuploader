@extends(BaseHelper::getAdminMasterLayoutTemplate())

@php
    $isThemeUploaderOpen = $errors->has('theme_archive');
    $themeUploaderClasses = $isThemeUploaderOpen ? 'collapse mb-4 show' : 'collapse mb-4';
    $themeUploaderExpanded = $isThemeUploaderOpen ? 'true' : 'false';
@endphp

@push('header-action')
    <x-core::button
        type="button"
        color="primary"
        icon="ti ti-cloud-upload"
        data-bs-toggle="collapse"
        data-bs-target="#theme-uploader-collapse"
        aria-controls="theme-uploader-collapse"
        aria-expanded="{{ $themeUploaderExpanded }}"
    >
        {{ trans('plugins/tpuploader::tpuploader.upload_theme') }}
    </x-core::button>
@endpush

@section('content')
    <div
        id="theme-uploader-collapse"
        class="{{ $themeUploaderClasses }}"
    >
        <x-core::card class="theme-upload-card">
            <x-core::card.body>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-xl-8">
                        <h3 class="card-title mb-1">
                            {{ trans('plugins/tpuploader::tpuploader.upload_theme') }}
                        </h3>
                        <p class="text-secondary mb-0">
                            {{ trans('plugins/tpuploader::tpuploader.upload_theme_description') }}
                        </p>
                    </div>
                    <div class="col-12">
                        <form
                            action="{{ route('tpuploader.themes.upload') }}"
                            method="POST"
                            enctype="multipart/form-data"
                            class="row g-3 align-items-end"
                        >
                            @csrf

                            <div class="col-12 col-lg-7">
                                <label
                                    for="theme_archive"
                                    class="form-label required"
                                >
                                    {{ trans('plugins/tpuploader::tpuploader.theme_archive') }}
                                </label>
                                <input
                                    id="theme_archive"
                                    type="file"
                                    name="theme_archive"
                                    accept=".zip,application/zip"
                                    class="form-control @error('theme_archive') is-invalid @enderror"
                                    required
                                >
                                @error('theme_archive')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-hint">
                                    {{ trans('plugins/tpuploader::tpuploader.theme_archive_help') }}
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
                                <x-core::button
                                    type="submit"
                                    color="primary"
                                    icon="ti ti-cloud-upload"
                                    class="w-100"
                                >
                                    {{ trans('plugins/tpuploader::tpuploader.upload') }}
                                </x-core::button>
                            </div>
                        </form>
                    </div>
                </div>
            </x-core::card.body>
        </x-core::card>
    </div>

    @if (count($themes) > 0)
        <div class="row row-cards mb-5">
            @foreach ($themes as $key => $theme)
                @php
                    $isActive = setting('theme') && Theme::getThemeName() == $key;
                @endphp
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <x-core::card class="theme-card h-100 {{ $isActive ? 'theme-card-active' : '' }}">
                        @if ($isActive)
                            <div class="ribbon ribbon-top bg-green">
                                <x-core::icon name="ti ti-check" />
                            </div>
                        @elseif ($inherit = Arr::get($theme, 'inherit'))
                            <div class="ribbon bg-azure">
                                {{ trans('packages/theme::theme.child_of', ['theme' => Arr::get($themes, $inherit . '.name', $inherit)]) }}
                            </div>
                        @endif

                        <div class="theme-screenshot position-relative">
                            <div
                                class="img-responsive img-responsive-4x3 card-img-top"
                                style="background-image: url('{{ Theme::getThemeScreenshot($key) }}')"
                            ></div>
                            @if ($isActive)
                                <div class="theme-active-overlay">
                                    <span class="badge bg-green text-green-fg d-inline-flex align-items-center gap-1 px-3 py-2">
                                        <x-core::icon name="ti ti-circle-check" />
                                        {{ trans('packages/theme::theme.activated') }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <x-core::card.body class="d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <h4 class="card-title text-truncate mb-0 me-2" title="{{ $theme['name'] }}">
                                    {{ $theme['name'] }}
                                </h4>
                                @if (!empty($theme['version']))
                                    <span class="badge bg-blue text-blue-fg text-nowrap">v{{ $theme['version'] }}</span>
                                @endif
                            </div>

                            @if (!empty($theme['description']))
                                <p class="text-secondary theme-description mb-3" title="{{ $theme['description'] }}">
                                    {{ $theme['description'] }}
                                </p>
                            @endif

                            <div class="mt-auto">
                                @if (!empty($theme['author']))
                                    <div class="d-flex align-items-center text-secondary small">
                                        <x-core::icon name="ti ti-user" class="me-1" />
                                        @if (!empty($theme['url']))
                                            <a
                                                href="{{ $theme['url'] }}"
                                                target="_blank"
                                                class="text-reset"
                                                rel="nofollow,noindex"
                                            >{{ $theme['author'] }}</a>
                                        @else
                                            {{ $theme['author'] }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </x-core::card.body>

                        <x-core::card.footer class="bg-transparent">
                            @if ($isActive)
                                <div class="d-flex align-items-center justify-content-center text-green py-1">
                                    <x-core::icon name="ti ti-circle-check" class="me-2" />
                                    <span class="fw-medium">{{ trans('packages/theme::theme.activated') }}</span>
                                </div>
                            @else
                                <div class="d-flex gap-2">
                                    @if (Auth::guard()->user()->hasPermission('theme.activate'))
                                        <x-core::button
                                            type="button"
                                            color="primary"
                                            icon="ti ti-check"
                                            class="btn-trigger-active-theme flex-fill"
                                            :data-url="route('theme.active', ['theme' => $key])"
                                            data-theme="{{ $key }}"
                                        >
                                            {{ trans('packages/theme::theme.active') }}
                                        </x-core::button>
                                    @endif
                                    @if (Auth::guard()->user()->hasPermission('theme.remove'))
                                        <x-core::button
                                            type="button"
                                            color="danger"
                                            :outlined="true"
                                            icon="ti ti-trash"
                                            class="btn-trigger-remove-theme"
                                            :data-url="route('theme.remove', ['theme' => $key])"
                                            data-theme="{{ $key }}"
                                            data-bs-toggle="tooltip"
                                            :title="trans('packages/theme::theme.remove')"
                                        />
                                    @endif
                                </div>
                            @endif
                        </x-core::card.footer>
                    </x-core::card>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty">
            <div class="empty-icon">
                <x-core::icon name="ti ti-palette" />
            </div>
            <p class="empty-title">{{ trans('packages/theme::theme.no_themes') }}</p>
            <p class="empty-subtitle text-secondary">
                {{ trans('packages/theme::theme.no_themes_description') }}
            </p>
        </div>
    @endif
@stop

@push('header')
    <style>
        .theme-upload-card {
            border: 1px solid rgba(241, 196, 15, 0.18);
            background:
                linear-gradient(135deg, rgba(241, 196, 15, 0.1), rgba(255, 255, 255, 0)),
                var(--tblr-bg-surface);
        }

        .theme-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 2px solid transparent;
        }

        .theme-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .theme-card-active {
            border-color: var(--bb-green);
        }

        .theme-screenshot {
            position: relative;
            overflow: hidden;
        }

        .theme-screenshot .img-responsive {
            transition: transform 0.3s ease;
        }

        .theme-card:hover .theme-screenshot .img-responsive {
            transform: scale(1.02);
        }

        .theme-active-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);
            display: flex;
            justify-content: center;
        }

        .theme-description {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
    </style>
@endpush

@push('footer')
    <x-core::modal.action
        id="remove-theme-modal"
        type="danger"
        :title="trans('packages/theme::theme.remove_theme')"
        :description="trans('packages/theme::theme.remove_theme_confirm_message')"
        :submit-button-attrs="['id' => 'confirm-remove-theme-button']"
        :submit-button-label="trans('packages/theme::theme.remove_theme_confirm_yes')"
    />
@endpush
