<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateSettingsRequest;
use App\Services\Cms\SettingsRepository;
use App\Services\Cms\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SettingsRepository $settings,
    ) {}

    public function edit(): View
    {
        return view('dashboard.settings.edit', [
            'definition' => SettingsService::DEFINITION,
            'settings' => $this->settings,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // validated() has already dropped anything absent from the
        // definition; the service then iterates the definition rather than
        // this array, so an unknown key cannot reach a write even if the
        // validation above were later loosened.
        $this->settingsService->update(
            $request->validated('settings', []),
            $request->file('site_logo'),
        );

        return redirect()
            ->route('dashboard.settings.edit')
            ->with('status', __('dashboard.flash.settings_updated'));
    }
}
