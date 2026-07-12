<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\Package;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class ZanaPlatformBillingCurrency
{
    public static function code(?Package $package = null): string
    {
        $configured = strtoupper(trim((string) SystemSetting::get('default_currency', '')));
        if ($configured !== '') {
            return $configured;
        }

        $packageCode = strtoupper(trim((string) ($package?->currency ?? '')));

        return $packageCode !== '' ? $packageCode : 'USD';
    }

    public static function formatAmount(float $amount, ?string $code = null): string
    {
        $code = strtoupper(trim((string) ($code ?: self::code())));
        $currency = self::currency($code);
        $precision = $currency?->precision ?? 2;
        if ((float) floor($amount) === $amount) {
            $precision = 0;
        }

        $symbol = $currency?->symbol ?: ($code . ' ');

        return $symbol . number_format($amount, $precision, '.', ',');
    }

    public static function formatPackageAmount(Package $package): string
    {
        $targetCode = self::code($package);
        $amount = (float) $package->chargeableAmount();
        $sourceCode = strtoupper(trim((string) ($package->currency ?? '')));

        if ($sourceCode !== '' && $sourceCode !== $targetCode) {
            $amount = FormatSettings::convert($amount, $sourceCode, $targetCode);
        }

        return self::formatAmount($amount, $targetCode);
    }

    private static function currency(string $code): ?Currency
    {
        return Cache::remember('zana_platform_currency_' . $code, 60, static function () use ($code) {
            return Currency::query()->where('code', $code)->first();
        });
    }
}
