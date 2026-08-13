<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Crm\ContactMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactMessageService $inbox) {}

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['unhandled', 'handled'], true)
            ? $request->query('filter')
            : 'all';

        return view('dashboard.contact-messages.index', [
            'messages' => $this->inbox->paginate($filter),
            'filter' => $filter,
            'unhandledCount' => $this->inbox->unhandledCount(),
        ]);
    }

    public function toggleHandled(Lead $lead): RedirectResponse
    {
        $this->assertIsContactMessage($lead);

        $this->inbox->setHandled($lead, ! $lead->is_handled);

        return back()->with('status', __('dashboard.flash.updated'));
    }

    /**
     * The inbox only ever shows type = 'contact'. Binding by id alone
     * would let this endpoint flip is_handled on ANY lead — a
     * consultation, a package enquiry — from a URL guessed off this
     * screen.
     *
     * 404 rather than 403: to this screen the lead genuinely does not
     * exist, and saying so reveals nothing about what exists elsewhere.
     */
    private function assertIsContactMessage(Lead $lead): void
    {
        if ($lead->type !== Lead::TYPE_CONTACT) {
            throw new NotFoundHttpException;
        }
    }
}
