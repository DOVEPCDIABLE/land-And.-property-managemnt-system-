<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class PlatformSettings
{
    private ?array $stored = null;

    public function refresh(): void
    {
        $this->stored = null;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        if ($this->stored === null) {
            $this->stored = Schema::hasTable('platform_settings') ? (PlatformSetting::find(1)?->values ?? []) : [];
        }
        if (array_key_exists($key, $this->stored)) {
            return $this->stored[$key];
        }
        if ($key === 'payments_enabled') {
            return (bool) config('payments.enabled', false);
        }

        return config('platform.'.$key, $fallback);
    }

    public function all(): array
    {
        $values = [];
        foreach (array_keys(config('platform')) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function revision(): int
    {
        return Schema::hasTable('platform_settings') ? (PlatformSetting::find(1)?->revision ?? 0) : 0;
    }

    public function apply(): void
    {
        config(['app.name' => $this->get('brand_name'), 'mail.from.name' => $this->get('brand_name'), 'ecosystem.display_timezone' => $this->get('timezone'), 'payments.enabled' => $this->get('payments_enabled')]);
        app()->setLocale(strtok(str_replace('_', '-', $this->get('locale')), '-'));
    }

    public function luminance(string $hex): float
    {
        $rgb = sscanf($hex, '#%02x%02x%02x');
        $linear = array_map(function ($value) {
            $c = $value / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
    }

    public function contrast(string $a, string $b): float
    {
        $x = $this->luminance($a);
        $y = $this->luminance($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    public function linkColor(): string
    {
        $hex = $this->get('secondary_color');
        $background = $this->get('background_color');
        for ($i = 0; $i < 8 && $this->contrast($hex, $background) < 4.5; $i++) {
            $rgb = sscanf($hex, '#%02x%02x%02x');
            $hex = sprintf('#%02x%02x%02x', ...(array_map(fn ($c) => (int) round($c * 0.7), $rgb)));
        }

        return $hex;
    }

    public function buttonText(): string
    {
        $a = $this->get('primary_color');
        $b = $this->get('secondary_color');

        return min($this->contrast($a, '#171717'), $this->contrast($b, '#171717')) >= min($this->contrast($a, '#FFFFFF'), $this->contrast($b, '#FFFFFF')) ? '#171717' : '#FFFFFF';
    }

    public function asset(?string $key): ?string
    {
        $path = $key ? $this->get($key) : null;

        return $path ? asset($path) : null;
    }

    public function colors(): array
    {
        return ['--brand-start' => $this->get('primary_color'), '--brand-end' => $this->get('secondary_color'), '--orange' => $this->get('secondary_color'), '--accent' => $this->linkColor(), '--orange-soft' => $this->get('secondary_color').'14', '--paper' => $this->get('background_color'), '--ink' => $this->get('text_color')];
    }
}
