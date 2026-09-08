<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationPreferenceKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    /**
     * Show the user's notification preferences page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'preferences' => collect(NotificationPreferenceKey::all())
                ->map(fn (NotificationPreferenceKey $key) => [
                    'key' => $key->value,
                    'label' => $key->label(),
                    'description' => $key->description(),
                    'enabled' => $user->wantsNotification($key),
                ]),
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $enabledKeys = collect($request->validated('preferences', []));

        $preferences = collect(NotificationPreferenceKey::all())
            ->mapWithKeys(fn (NotificationPreferenceKey $key) => [
                $key->value => $enabledKeys->get($key->value, true),
            ])
            ->all();

        $request->user()->update(['notification_preferences' => $preferences]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferensi notifikasi diperbarui.')]);

        return back();
    }
}
