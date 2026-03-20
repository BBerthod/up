<?php

namespace App\Http\Requests;

use App\Enums\WarmSiteMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarmSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i',
                Rule::unique('warm_sites')->where('team_id', auth()->user()->team_id)->ignore($this->route('warming')),
            ],
            'mode' => ['sometimes', 'required', Rule::enum(WarmSiteMode::class)],
            'sitemap_url' => 'nullable|url|required_if:mode,sitemap',
            'urls' => 'nullable|array|max:500|required_if:mode,urls',
            'urls.*' => 'url',
            'frequency_minutes' => ['sometimes', 'required', 'integer', Rule::in([15, 30, 60, 120, 360, 720, 1440])],
            'max_urls' => 'sometimes|required|integer|min:1|max:500',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
