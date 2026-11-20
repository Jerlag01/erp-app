<?php

namespace App\Filament\Admin\Resources\CertificateTypes\Pages;

use App\Filament\Admin\Pages\TrainingMatrix;
use App\Filament\Admin\Resources\CertificateTypes\CertificateTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCertificateTypes extends ManageRecords
{
    protected static string $resource = CertificateTypeResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [TrainingMatrix::getUrl() => __('erp.training_matrix.title'), __('erp.certificate_types.plural')];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
