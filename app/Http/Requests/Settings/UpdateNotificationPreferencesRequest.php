<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Unknown keys in the payload are harmless — the controller only
     * ever reads the keys it recognizes (via NotificationPreferenceKey::all())
     * when building what actually gets saved, so there's no need to
     * whitelist key names here too.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ];
    }
}
