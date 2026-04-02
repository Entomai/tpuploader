<?php

namespace Botble\Tpuploader\Tables;

use Botble\Tpuploader\Models\TPUploader;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\BulkChanges\CreatedAtBulkChange;
use Botble\Table\BulkChanges\NameBulkChange;
use Botble\Table\BulkChanges\StatusBulkChange;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\NameColumn;
use Botble\Table\Columns\StatusColumn;
use Botble\Table\HeaderActions\CreateHeaderAction;
use Illuminate\Database\Eloquent\Builder;

class TPUploaderTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(TPUploader::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('tpuploader.create'))
            ->addActions([
                EditAction::make()->route('tpuploader.edit'),
                DeleteAction::make()->route('tpuploader.destroy'),
            ])
            ->addColumns([
                IdColumn::make(),
                NameColumn::make()->route('tpuploader.edit'),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('tpuploader.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make(),
                StatusBulkChange::make(),
                CreatedAtBulkChange::make(),
            ])
            ->queryUsing(function (Builder $query) {
                $query->select([
                    'id',
                    'name',
                    'created_at',
                    'status',
                ]);
            });
    }
}
