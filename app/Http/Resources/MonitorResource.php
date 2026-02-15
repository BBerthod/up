<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'method' => $this->method,
            'expected_status_code' => $this->expected_status_code,
            'keyword' => $this->keyword,
            'interval' => $this->interval,
            'is_active' => $this->is_active,
            'warning_threshold_ms' => $this->warning_threshold_ms,
            'critical_threshold_ms' => $this->critical_threshold_ms,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'latest_check' => $this->whenLoaded('checks', function () {
                $check = $this->checks->first();

                return $check ? new MonitorCheckResource($check) : null;
            }),
            'notification_channels' => NotificationChannelResource::collection($this->whenLoaded('notificationChannels')),
        ];
    }
}
