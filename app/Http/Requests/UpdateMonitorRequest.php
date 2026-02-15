<?php

namespace App\Http\Requests;

use App\Enums\MonitorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', $this->route('monitor')?->type?->value ?? 'http');

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', 'required', Rule::enum(MonitorType::class)],
            'interval' => 'sometimes|required|integer|min:1|max:60',
            'warning_threshold_ms' => 'nullable|integer|min:1',
            'critical_threshold_ms' => 'nullable|integer|min:1',
            'notification_channels' => 'array',
            'notification_channels.*' => 'exists:notification_channels,id',
        ];

        return match ($type) {
            'ping' => array_merge($rules, [
                'url' => 'sometimes|required|string|max:2048',
            ]),
            'port' => array_merge($rules, [
                'url' => 'sometimes|required|string|max:2048',
                'port' => 'sometimes|required|integer|min:1|max:65535',
            ]),
            'dns' => array_merge($rules, [
                'url' => 'sometimes|required|string|max:2048',
                'dns_record_type' => 'sometimes|required|in:A,AAAA,CNAME,MX,TXT,NS,SOA,SRV',
                'dns_expected_value' => 'sometimes|required|string|max:255',
            ]),
            default => array_merge($rules, [
                'url' => 'sometimes|required|url|max:2048',
                'method' => 'sometimes|required|in:GET,POST,HEAD',
                'expected_status_code' => 'sometimes|required|integer|min:100|max:599',
                'keyword' => 'nullable|string|max:255',
            ]),
        };
    }
}
