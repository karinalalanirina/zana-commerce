<?php

namespace App\Support;

use App\Models\WaStorefront;
use App\Models\Workspace;

class ZanaStorefrontCurrency
{
    public static function code(?WaStorefront $storefront = null, ?Workspace $workspace = null): string
    {
        $storefrontCode = strtoupper(trim((string) ($storefront?->currency_code ?? '')));
        if ($storefrontCode !== '') {
            return $storefrontCode;
        }

        $workspaceCode = strtoupper(trim((string) ($workspace?->currency ?? $storefront?->workspace?->currency ?? '')));
        if ($workspaceCode !== '') {
            return $workspaceCode;
        }

        $catalogDefault = strtoupper(trim((string) \App\Models\SystemSetting::get('catalog_default_currency', '')));
        if ($catalogDefault !== '') {
            return $catalogDefault;
        }

        $platformDefault = strtoupper(trim((string) \App\Models\SystemSetting::get('default_currency', '')));

        return $platformDefault !== '' ? $platformDefault : 'USD';
    }

    public static function convertMinor(int $minor, ?string $fromCode, ?string $toCode): int
    {
        $fromCode = strtoupper(trim((string) $fromCode));
        $toCode = strtoupper(trim((string) $toCode));

        if ($minor === 0 || $fromCode === '' || $toCode === '' || $fromCode === $toCode) {
            return $minor;
        }

        $convertedMajor = FormatSettings::convert($minor / 100, $fromCode, $toCode);

        return (int) round($convertedMajor * 100);
    }

    public static function convertMinorForStorefront(
        int $minor,
        ?string $fromCode,
        ?WaStorefront $storefront = null,
        ?Workspace $workspace = null
    ): int {
        return self::convertMinor($minor, $fromCode, self::code($storefront, $workspace));
    }

    public static function formatMinor(int $minor, ?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return \App\Models\WaProduct::formatCurrency($minor, $code !== '' ? $code : 'USD');
    }

    public static function formatStorefrontMinor(
        int $minor,
        ?WaStorefront $storefront = null,
        ?Workspace $workspace = null
    ): string {
        return self::formatMinor($minor, self::code($storefront, $workspace));
    }
}
