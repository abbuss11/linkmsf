<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\SubmitOrganizationRequest;
use App\Exceptions\ActionFailed;
use App\Http\Requests\StoreOrganizationRequestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationRequestController extends Controller
{
    public function create(): View
    {
        return view('organization-requests.create');
    }

    public function store(StoreOrganizationRequestRequest $request, SubmitOrganizationRequest $submit): RedirectResponse
    {
        /** @var array{organization_name: string, contact_name: string, email: string, phone?: string|null, country?: string|null, message?: string|null} $data */
        $data = $request->safe()->only(['organization_name', 'contact_name', 'email', 'phone', 'country', 'message']);

        try {
            $submit->handle($data);
        } catch (ActionFailed $exception) {
            return back()->withInput()->withErrors(['email' => $exception->getMessage()]);
        }

        return redirect()->route('organization-requests.thanks');
    }

    public function thanks(): View
    {
        return view('organization-requests.thanks');
    }
}
