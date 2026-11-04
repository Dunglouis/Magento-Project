<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Reads the Google ID token returned by the token endpoint and checks its claims.
 *
 * The token comes straight from Google's token endpoint over TLS in exchange for our client secret,
 * so its signature does not need checking (OpenID Connect Core 3.1.3.7); issuer, audience, expiry
 * and email_verified still do.
 */
class IdTokenParser
{
    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];
    private const CLOCK_SKEW = 300;

    /**
     * @param Json $json
     */
    public function __construct(
        private readonly Json $json
    ) {
    }

    /**
     * Profile of a valid token for this client
     *
     * @param string $idToken
     * @param string $clientId
     * @param int $now Unix time
     * @return GoogleProfile
     * @throws LocalizedException when the token is malformed, for another client, expired or the email is unverified
     */
    public function parse(string $idToken, string $clientId, int $now): GoogleProfile
    {
        $claims = $this->claims($idToken);

        if (!in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || ($claims['aud'] ?? null) !== $clientId
            || (int)($claims['exp'] ?? 0) + self::CLOCK_SKEW < $now
            || !is_string($claims['sub'] ?? null) || $claims['sub'] === ''
        ) {
            throw new LocalizedException(__('Google sign-in failed. Please try again.'));
        }
        $email = is_string($claims['email'] ?? null) ? trim($claims['email']) : '';
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';
        if ($email === '' || !$verified) {
            throw new LocalizedException(__('Your Google account has no verified email address.'));
        }

        [$firstname, $lastname] = $this->names($claims, $email);
        return new GoogleProfile($claims['sub'], $email, $firstname, $lastname);
    }

    /**
     * Decoded payload (second part) of the JWT
     *
     * @param string $idToken
     * @return array<string,mixed>
     * @throws LocalizedException
     */
    private function claims(string $idToken): array
    {
        $parts = explode('.', $idToken);
        // JWT payload is base64url JSON, not user input to execute
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $payload = count($parts) === 3 ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
        try {
            $claims = $payload !== false ? $this->json->unserialize($payload) : null;
        } catch (\InvalidArgumentException) {
            $claims = null;
        }
        if (!is_array($claims)) {
            throw new LocalizedException(__('Google sign-in failed. Please try again.'));
        }
        return $claims;
    }

    /**
     * First and last name; Google may only send "name" (or nothing), Magento requires both
     *
     * @param array<string,mixed> $claims
     * @param string $email
     * @return string[] [firstname, lastname]
     */
    private function names(array $claims, string $email): array
    {
        $given = trim((string)($claims['given_name'] ?? ''));
        $family = trim((string)($claims['family_name'] ?? ''));
        if ($given === '' || $family === '') {
            $words = preg_split('/\s+/', trim((string)($claims['name'] ?? ''))) ?: [];
            $words = array_values(array_filter($words, fn (string $word): bool => $word !== ''));
            $given = $given ?: (string)array_shift($words);
            $family = $family ?: implode(' ', $words);
        }
        $given = $given ?: strstr($email, '@', true);
        return [(string)$given, $family ?: (string)$given];
    }
}
