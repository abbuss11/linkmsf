<?php

namespace App\Filament\App\Resources\Members\Pages;

use App\Filament\App\Resources\Members\MemberResource;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;
}
