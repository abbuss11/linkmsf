<?php

namespace App\Filament\App\Resources\JobPostings\Pages;

use App\Filament\App\Resources\JobPostings\JobPostingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobPostings extends ListRecords
{
    protected static string $resource = JobPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('job_postings.actions.create')),
        ];
    }
}
