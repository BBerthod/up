<?php

namespace App\Http\Requests;

use App\Enums\MonitorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', 'http');

        $rules = [
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::enum(MonitorType::class)],
            'interval' => 'required|integer|min:1|max:60',
            'warning_threshold_ms' => 'nullable|integer|min:1',
            'critical_threshold_ms' => 'nullable|integer|min:1',
            'notification_channels' => 'array',
            'notification_channels.*' => 'exists:notification_channels,id',
        ];

        return match ($type) {
            'ping' => array_merge($rules, [
                'url' => 'required|string|max:2048',
            ]),
            'port' => array_merge($rules, [
                'url' => 'required|string|max:2048',
                'port' => 'required|integer|min:1|max:65535',
            ]),
            'dns' => array_merge($rules, [
                'url' => 'required|string|max:2048',
                'dns_record_type' => 'required|in:A,AAAA,CNAME,MX,TXT,NS,SOA,SRV',
                'dns_expected_value' => 'required|string|max:255',
            ]),
            default => array_merge($rules, [
                'url' => 'required|url|max:2048',
                'method' => 'required|in:GET,POST,HEAD',
                'expected_status_code' => 'required|integer|min:100|max:599',
                'keyword' => 'nullable|string|max:255',
            ]),
        };
    }
}
