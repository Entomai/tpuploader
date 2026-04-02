<?php

namespace Botble\Tpuploader\Http\Controllers;

use Botble\Base\Http\Actions\DeleteResourceAction;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Tpuploader\Forms\TPUploaderForm;
use Botble\Tpuploader\Http\Requests\TPUploaderRequest;
use Botble\Tpuploader\Http\Requests\UploadPluginRequest;
use Botble\Tpuploader\Http\Requests\UploadThemeRequest;
use Botble\Tpuploader\Models\TPUploader;
use Botble\Tpuploader\Services\PluginUploadService;
use Botble\Tpuploader\Services\ThemeUploadService;
use Botble\Tpuploader\Tables\TPUploaderTable;

class TPUploaderController extends BaseController
{
    public function __construct()
    {
        $this
            ->breadcrumb()
            ->add(trans('plugins/tpuploader::tpuploader.name'), route('tpuploader.index'));
    }

    public function index(TPUploaderTable $table)
    {
        $this->pageTitle(trans('plugins/tpuploader::tpuploader.name'));

        return $table->renderTable();
    }

    public function create()
    {
        $this->pageTitle(trans('plugins/tpuploader::tpuploader.create'));

        return TPUploaderForm::create()->renderForm();
    }

    public function store(TPUploaderRequest $request)
    {
        $form = TPUploaderForm::create()->setRequest($request);

        $form->save();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('tpuploader.index'))
            ->setNextUrl(route('tpuploader.edit', $form->getModel()->getKey()))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(TPUploader $tPUploader)
    {
        $this->pageTitle(trans('core/base::forms.edit_item', ['name' => $tPUploader->name]));

        return TPUploaderForm::createFromModel($tPUploader)->renderForm();
    }

    public function update(TPUploader $tPUploader, TPUploaderRequest $request)
    {
        TPUploaderForm::createFromModel($tPUploader)
            ->setRequest($request)
            ->save();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('tpuploader.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function uploadTheme(UploadThemeRequest $request, ThemeUploadService $themeUploadService)
    {
        $result = $themeUploadService->upload(
            $request->file('theme_archive'),
            $request->boolean('activate')
        );

        return redirect()
            ->route('theme.index')
            ->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
    }

    public function uploadPlugin(UploadPluginRequest $request, PluginUploadService $pluginUploadService)
    {
        $result = $pluginUploadService->upload(
            $request->file('plugin_archive'),
            $request->boolean('activate')
        );

        return redirect()
            ->route('plugins.index')
            ->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
    }

    public function destroy(TPUploader $tPUploader)
    {
        return DeleteResourceAction::make($tPUploader);
    }
}
