<?php

namespace App\Http\Requests;

use App\Enums\WarmSiteMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarmSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->mode === 'sitemap') {
            $this->merge(['urls' => null]);
        } elseif (is_array($this->urls)) {
            $this->merge(['urls' => array_values(array_filter($this->urls, fn ($u) => trim($u) !== ''))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i',
                Rule::unique('warm_sites')->where('team_id', auth()->user()->team_id),
            ],
            'mode' => ['required', Rule::enum(WarmSiteMode::class)],
            'sitemap_url' => 'nullable|url|required_if:mode,sitemap',
            'urls' => 'nullable|array|max:500|required_if:mode,urls',
            'urls.*' => 'exclude_if:mode,sitemap|url',
            'frequency_minutes' => ['required', 'integer', Rule::in([15, 30, 60, 120, 360, 720, 1440])],
            'max_urls' => 'required|integer|min:1|max:500',
            'custom_headers' => 'nullable|array|max:10',
            'custom_headers.*.key' => 'required_with:custom_headers|string|max:255',
            'custom_headers.*.value' => 'required_with:custom_headers|string|max:1000',
            'monitor_id' => 'nullable|integer|exists:monitors,id',
        ];
    }
}
