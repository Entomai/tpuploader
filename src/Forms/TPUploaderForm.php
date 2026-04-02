<?php

namespace Botble\Tpuploader\Forms;

use Botble\Base\Forms\FieldOptions\NameFieldOption;
use Botble\Base\Forms\FieldOptions\StatusFieldOption;
use Botble\Base\Forms\Fields\SelectField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\Tpuploader\Http\Requests\TPUploaderRequest;
use Botble\Tpuploader\Models\TPUploader;

class TPUploaderForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(TPUploader::class)
            ->setValidatorClass(TPUploaderRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->required())
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->setBreakFieldPoint('status');
    }
}
