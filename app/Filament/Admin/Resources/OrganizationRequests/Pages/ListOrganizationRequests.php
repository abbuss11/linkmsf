<?php

namespace App\Filament\Admin\Resources\OrganizationRequests\Pages;

use App\Filament\Admin\Resources\OrganizationRequests\OrganizationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationRequests extends ListRecords
{
    protected static string $resource = OrganizationRequestResource::class;
}
