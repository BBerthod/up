<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\Response;

class BadgeController extends Controller
{
    public function __invoke(string $hash): Response
    {
        $decoded = @unpack('V', base64_decode(strtr($hash, '-_', '+/')));
        if (! $decoded || ! isset($decoded[1])) {
            abort(404);
        }

        $monitor = Monitor::withoutGlobalScopes()->find($decoded[1]);
        if (! $monitor) {
            abort(404);
        }

        $uptime = (float) ($monitor->checks()
            ->where('checked_at', '>=', now()->subDays(30))
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
            ->value('uptime') ?? 100);

        $isDown = $monitor->checks()
            ->latest('checked_at')
            ->value('status') === 'down';

        $label = 'uptime';
        $value = $isDown ? 'down' : $uptime.'%';
        $color = $isDown ? '#e05d44' : ($uptime > 99 ? '#4c1' : ($uptime > 95 ? '#dfb317' : '#e05d44'));

        $labelWidth = strlen($label) * 6.5 + 10;
        $valueWidth = strlen($value) * 6.5 + 10;
        $totalWidth = $labelWidth + $valueWidth;

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$totalWidth}" height="20">
            <linearGradient id="b" x2="0" y2="100%">
                <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
                <stop offset="1" stop-opacity=".1"/>
            </linearGradient>
            <clipPath id="a"><rect width="{$totalWidth}" height="20" rx="3" fill="#fff"/></clipPath>
            <g clip-path="url(#a)">
                <rect width="{$labelWidth}" height="20" fill="#555"/>
                <rect x="{$labelWidth}" width="{$valueWidth}" height="20" fill="{$color}"/>
                <rect width="{$totalWidth}" height="20" fill="url(#b)"/>
            </g>
            <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">
                <text x="{($labelWidth / 2)}" y="15" fill="#010101" fill-opacity=".3">{$label}</text>
                <text x="{($labelWidth / 2)}" y="14">{$label}</text>
                <text x="{($labelWidth + $valueWidth / 2)}" y="15" fill="#010101" fill-opacity=".3">{$value}</text>
                <text x="{($labelWidth + $valueWidth / 2)}" y="14">{$value}</text>
            </g>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
