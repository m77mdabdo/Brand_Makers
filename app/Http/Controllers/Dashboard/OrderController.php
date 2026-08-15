<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreOrderEventRequest;
use App\Http\Requests\Dashboard\StoreOrderRequest;
use App\Http\Requests\Dashboard\UpdateOrderRequest;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Package;
use App\Services\Orders\OrderReportService;
use App\Services\Orders\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Where a freshly minted tracking code is flashed to the create
     * screen's redirect.
     *
     * FLASHED, NOT STORED. Only the digest is in the database, so this is
     * the single opportunity anyone has to read the plaintext — and it
     * lasts exactly one request. Putting it in the URL instead would leave
     * a working credential in the admin's browser history and in the next
     * referrer header their browser sends.
     */
    private const CODE_FLASH_KEY = 'issued_tracking_code';

    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderReportService $report,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->report->filtersFrom($request->all());

        return view('dashboard.orders.index', [
            'orders' => $this->report->query($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('dashboard.orders.create', $this->formOptions());
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->validated(), $request->validated('note'));

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', __('dashboard.flash.created'))
            // trackingCode() returns the plaintext only on the object that
            // issued it; every model read back from the database returns
            // null. This is the one moment it exists.
            ->with(self::CODE_FLASH_KEY, $order->trackingCode());
    }

    public function show(Order $order): View
    {
        $order->load(['package', 'lead', 'events']);

        return view('dashboard.orders.show', [
            'order' => $order,
            // Only the moves this order can make, so the screen offers no
            // button that would be refused.
            'allowedTransitions' => OrderService::allowedFor($order),
            'issuedCode' => session(self::CODE_FLASH_KEY),
        ]);
    }

    public function edit(Order $order): View
    {
        return view('dashboard.orders.edit', ['order' => $order] + $this->formOptions());
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->update($order, $request->validated());

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', __('dashboard.flash.updated'));
    }

    /**
     * Moves the order along — or back.
     *
     * The graph allows rollback (see OrderService), because marking an
     * order delivered a day early is an ordinary mistake and refusing to
     * correct it would leave a client looking at a portal that says their
     * work is done when it is not.
     */
    public function transition(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'array'],
            'note.ar' => ['nullable', 'string', 'max:1000'],
            'note.en' => ['nullable', 'string', 'max:1000'],
        ]);

        $moved = $this->orders->transition(
            $order,
            $validated['status'],
            $validated['note'] ?? null,
            $validated['progress'] ?? null,
        );

        if (! $moved) {
            // A stale screen — two admins on the same order, or a back
            // button. A message is more use than a validation error about
            // a field they never saw.
            return back()->with('error', __('dashboard.orders.transition_refused'));
        }

        return back()->with('status', __('dashboard.orders.transition_done'));
    }

    public function updateProgress(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->orders->setProgress($order, $validated['progress']);

        return back()->with('status', __('dashboard.flash.updated'));
    }

    public function storeEvent(StoreOrderEventRequest $request, Order $order): RedirectResponse
    {
        $this->orders->addEvent($order, $request->validated('note'), $request->validated('occurred_at'));

        return back()->with('status', __('dashboard.flash.created'));
    }

    public function updateEvent(StoreOrderEventRequest $request, Order $order, OrderEvent $event): RedirectResponse
    {
        $this->assertBelongsToOrder($order, $event);

        $this->orders->updateEvent($event, $request->validated('note'), $request->validated('occurred_at'));

        return back()->with('status', __('dashboard.flash.updated'));
    }

    public function destroyEvent(Order $order, OrderEvent $event): RedirectResponse
    {
        $this->assertBelongsToOrder($order, $event);

        $this->orders->deleteEvent($event);

        return back()->with('status', __('dashboard.flash.deleted'));
    }

    /**
     * Re-mints the tracking code, invalidating the old one.
     *
     * How a leaked portal link is revoked. The new plaintext is flashed to
     * the detail screen exactly like a newly created order's.
     */
    public function reissueCode(Order $order): RedirectResponse
    {
        $code = $this->orders->reissueTrackingCode($order);

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', __('dashboard.orders.code_reissued'))
            ->with(self::CODE_FLASH_KEY, $code);
    }

    /**
     * Both event routes are nested under an order, so the two ids arrive
     * separately and could name unrelated rows.
     *
     * 404 rather than 403: to this order the event genuinely does not
     * exist, and saying so reveals nothing about what exists elsewhere.
     * The same reasoning as the contact-message inbox.
     */
    private function assertBelongsToOrder(Order $order, OrderEvent $event): void
    {
        abort_unless($event->order_id === $order->getKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'packages' => Package::query()->ordered()->get(),
            // Only leads that could plausibly become an order, newest
            // first — the full table would be a select with thousands of
            // options in it.
            'leads' => Lead::query()->latest('id')->limit(200)->get(),
        ];
    }
}
