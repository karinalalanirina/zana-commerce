<?php

namespace App\Support;

use App\Exceptions\ZanaUnofficialWhatsAppBlocked;
use Illuminate\Support\Facades\Log;

class ZanaWhatsAppPolicy
{
    /** @var string[] */
    private const UNOFFICIAL_PROVIDERS = ['baileys'];

    public static function allowUnofficial(): bool
    {
        return (bool) config('zana.allow_unofficial_whatsapp', false);
    }

    public static function isUnofficial(?string $provider): bool
    {
        $provider = strtolower(trim((string) $provider));
        return $provider !== '' && in_array($provider, self::UNOFFICIAL_PROVIDERS, true);
    }

    public static function allows(?string $provider): bool
    {
        if (self::allowUnofficial()) {
            return true;
        }

        return ! self::isUnofficial($provider);
    }

    /**
     * @param  array<int, string>  $providers
     * @return array<int, string>
     */
    public static function filterAllowedProviders(array $providers): array
    {
        $providers = array_values(array_unique(array_filter(array_map(
            fn ($provider) => strtolower(trim((string) $provider)),
            $providers
        ))));

        if (self::allowUnofficial()) {
            return $providers;
        }

        $filtered = array_values(array_filter($providers, fn ($provider) => ! self::isUnofficial($provider)));

        return $filtered === [] ? ['waba'] : $filtered;
    }

    /**
     * @param  array<int, string>|null  $allowedProviders
     */
    public static function sanitizeDefaultProvider(?string $provider, ?array $allowedProviders = null): string
    {
        $allowedProviders ??= ['waba', 'twilio'];
        $allowedProviders = self::filterAllowedProviders($allowedProviders);

        $provider = strtolower(trim((string) $provider));
        if ($provider !== '' && in_array($provider, $allowedProviders, true) && self::allows($provider)) {
            return $provider;
        }

        return $allowedProviders[0] ?? 'waba';
    }

    /**
     * @throws ZanaUnofficialWhatsAppBlocked
     */
    public static function assertAllowed(string $provider, string $operation, ?int $workspaceId = null): void
    {
        if (self::allows($provider)) {
            return;
        }

        self::logBlocked($provider, $operation, $workspaceId);

        throw new ZanaUnofficialWhatsAppBlocked(
            provider: $provider,
            operation: $operation,
            workspaceId: $workspaceId,
            message: self::blockedMessage($provider),
        );
    }

    public static function blockedMessage(?string $provider = null): string
    {
        $provider = strtolower(trim((string) $provider));
        $label = $provider !== '' ? strtoupper($provider) : 'This provider';

        return "{$label} is disabled because unofficial WhatsApp providers are not allowed in this environment.";
    }

    public static function logBlocked(string $provider, string $operation, ?int $workspaceId = null): void
    {
        Log::warning('[ZANA-WHATSAPP] unofficial provider blocked', [
            'workspace_id' => $workspaceId,
            'provider' => strtolower(trim($provider)),
            'operation' => $operation,
        ]);
    }
}
