<?php

namespace App\Livewire;

use App\Enums\FormFieldType;
use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\RulesBuilder;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Back-office preview of an application form, drawn with the same Blade
 * components and stylesheet as the candidate wizard, so what recruiters see
 * is what candidates get (§3.1 step 4). Shown in an iframe of the /app panel,
 * whose routes (and therefore authentication, tenant and MFA) it uses.
 * Nothing is stored.
 */
#[Layout('components.layouts.preview')]
class FormPreview extends Component
{
    #[Locked]
    public string $postingUlid;

    #[Locked]
    public ?int $version = null;

    /** @var array<string, mixed> */
    public array $answers = [];

    public bool $valid = false;

    public function mount(string $record): void
    {
        $this->postingUlid = $record;
        $this->version = request()->integer('version') ?: null;
        $this->posting();

        foreach ($this->schema()->inputFields() as $field) {
            $this->answers[$field->key] = $field->type === FormFieldType::CheckboxList ? [] : null;
        }
    }

    public function check(): void
    {
        $this->valid = false;
        $this->resetErrorBag();
        $schema = $this->schema();
        $rules = app(RulesBuilder::class)->rules($schema, $this->answers);

        foreach ($schema->documentFields() as $field) {
            unset($rules['answers.'.$field->key], $rules['answers.'.$field->key.'.*']);
        }

        Validator::make(['answers' => $this->answers], $rules, [], app(RulesBuilder::class)->attributes($schema))->validate();

        $this->valid = true;
    }

    public function render(): View
    {
        $schema = $this->schema();

        return view('livewire.form-preview', [
            'posting' => $this->posting(),
            'fields' => $schema->nonDocumentFields(),
            'documents' => $schema->documentFields(),
            'conditions' => collect($schema->fields)
                ->filter(fn ($field) => $field->visibleIf !== null)
                ->mapWithKeys(fn ($field) => [$field->key => $field->visibleIf])
                ->all(),
        ]);
    }

    private function schema(): FormSchema
    {
        $posting = $this->posting();
        $form = ApplicationForm::queryAcrossOrganizations()
            ->where('organization_id', $posting->organization_id)
            ->where('job_posting_id', $posting->getKey())
            ->first();

        if ($form === null) {
            return new FormSchema([]);
        }

        if ($this->version === null) {
            return $form->draftSchema();
        }

        return ApplicationFormVersion::queryAcrossOrganizations()
            ->where('organization_id', $form->organization_id)
            ->where('application_form_id', $form->getKey())
            ->where('version', $this->version)
            ->firstOrFail()
            ->formSchema();
    }

    private function posting(): JobPosting
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        // The tenant of the /app URL, set by the panel's tenant middleware.
        $organization = app(OrganizationContext::class)->get();
        abort_unless($organization !== null, 404);

        $posting = JobPosting::queryForOrganization($organization)->where('ulid', $this->postingUlid)->firstOrFail();
        abort_unless(Gate::forUser($user)->allows('view', $posting), 403);

        return $posting;
    }
}
