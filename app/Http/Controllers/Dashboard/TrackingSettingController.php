<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateTrackingSettingsRequest;
use App\Models\TrackingSetting;
use App\Services\Cms\TrackingSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrackingSettingController extends Controller
{
    public function __construct(private readonly TrackingSettingService $tracking) {}

    public function edit(): View
    {
        return view('dashboard.tracking-settings.edit', [
            'formats' => TrackingSetting::FORMATS,
            'settings' => TrackingSetting::query()->pluck('value', 'key'),
            'active' => TrackingSetting::query()->pluck('is_active', 'key'),
        ]);
    }

    public function update(UpdateTrackingSettingsRequest $request): RedirectResponse
    {
        // The service iterates FORMATS rather than the payload, so an
        // unknown key cannot create a row — the same defence the settings
        // editor uses, and it matters more here because these values are
        // interpolated into inline <script> blocks.
        $this->tracking->update($request->validated());

        return redirect()
            ->route('dashboard.tracking.edit')
            ->with('status', __('dashboard.flash.tracking_updated'));
    }
}
