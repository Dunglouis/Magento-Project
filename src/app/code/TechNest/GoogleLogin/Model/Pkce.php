<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

/**
 * Random values of the authorization request: the CSRF "state" and the PKCE pair (RFC 7636, S256)
 */
class Pkce
{
    /**
     * Opaque CSRF token sent as "state" and compared on the callback
     *
     * @return string
     */
    public function createState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Code verifier: 64 URL-safe characters (RFC 7636 allows 43-128)
     *
     * @return string
     */
    public function createVerifier(): string
    {
        return $this->base64Url(random_bytes(48));
    }

    /**
     * S256 code challenge of a verifier
     *
     * @param string $verifier
     * @return string
     */
    public function challenge(string $verifier): string
    {
        return $this->base64Url(hash('sha256', $verifier, true));
    }

    /**
     * Base64url without padding
     *
     * @param string $bytes
     * @return string
     */
    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
