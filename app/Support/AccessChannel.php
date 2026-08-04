<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Origen de acceso del cliente (web portal vs app móvil vs API genérica).
 */
final class AccessChannel
{
    public const WEB = 'web';

    public const MOBILE = 'mobile';

    public const API = 'api';

    /**
     * @return array{access_channel: string, device_name: string|null, user_agent: string|null}
     */
    public static function fromRequest(Request $request, ?string $deviceName = null): array
    {
        $tokenName = $deviceName;
        if ($tokenName === null || $tokenName === '') {
            $tokenName = $request->user()?->currentAccessToken()?->name;
        }
        $tokenName = is_string($tokenName) && $tokenName !== '' ? $tokenName : null;

        $userAgent = $request->userAgent();
        $userAgent = is_string($userAgent) && $userAgent !== ''
            ? mb_substr($userAgent, 0, 255)
            : null;

        return [
            'access_channel' => self::resolve($tokenName, $userAgent),
            'device_name' => $tokenName,
            'user_agent' => $userAgent,
        ];
    }

    public static function resolve(?string $deviceName, ?string $userAgent = null): string
    {
        $needle = mb_strtolower(trim((string) $deviceName));
        if ($needle !== '') {
            if (str_contains($needle, 'field')
                || str_contains($needle, 'mobile')
                || str_contains($needle, 'android')
                || str_contains($needle, 'ios')
                || str_contains($needle, 'flutter')
            ) {
                return self::MOBILE;
            }
            if (str_contains($needle, 'web') || str_contains($needle, 'portal') || str_contains($needle, 'browser')) {
                return self::WEB;
            }
        }

        $ua = mb_strtolower((string) $userAgent);
        if ($ua !== '' && (
            str_contains($ua, 'okhttp')
            || str_contains($ua, 'dart')
            || str_contains($ua, 'flutter')
            || str_contains($ua, 'phoenix-field')
        )) {
            return self::MOBILE;
        }

        if ($needle === null || $needle === '' || $needle === 'phoenix-web') {
            return self::WEB;
        }

        return self::API;
    }

    public static function label(string $channel): string
    {
        return match ($channel) {
            self::WEB => 'Web',
            self::MOBILE => 'App móvil',
            self::API => 'API',
            default => $channel,
        };
    }
}
