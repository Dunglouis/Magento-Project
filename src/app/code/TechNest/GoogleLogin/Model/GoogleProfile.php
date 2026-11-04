<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

/**
 * The Google user from a validated ID token
 */
class GoogleProfile
{
    /**
     * @param string $sub stable Google account ID
     * @param string $email verified email address
     * @param string $firstname given_name, or the first word of name
     * @param string $lastname family_name, or the rest of name
     */
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly string $firstname,
        public readonly string $lastname
    ) {
    }
}
