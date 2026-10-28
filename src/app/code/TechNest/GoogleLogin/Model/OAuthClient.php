<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Google OAuth 2.0 / OpenID Connect, authorization code flow with PKCE (plain Guzzle)
 */
class OAuthClient
{
    public const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const SCOPE = 'openid email profile';

    /**
     * @param Config $config
     * @param ClientInterface $httpClient
     * @param IdTokenParser $idTokenParser
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly ClientInterface $httpClient,
        private readonly IdTokenParser $idTokenParser,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Google consent page URL
     *
     * @param string $state
     * @param string $codeChallenge S256 challenge of the verifier kept in the session
     * @return string
     */
    public function getAuthorizationUrl(string $state, string $codeChallenge): string
    {
        return self::AUTHORIZATION_URL . '?' . http_build_query([
            'client_id' => $this->config->getClientId(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the authorization code for tokens and read the user from the ID token
     *
     * @param string $code
     * @param string $codeVerifier
     * @return GoogleProfile
     * @throws LocalizedException
     */
    public function fetchProfile(string $code, string $codeVerifier): GoogleProfile
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'form_params' => [
                    'code' => $code,
                    'client_id' => $this->config->getClientId(),
                    'client_secret' => $this->config->getClientSecret(),
                    'redirect_uri' => $this->config->getRedirectUri(),
                    'grant_type' => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                ],
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => false,
                'timeout' => 10,
            ]);
            $body = (string)$response->getBody();
            $data = $body !== '' ? $this->json->unserialize($body) : null;
        } catch (GuzzleException | \InvalidArgumentException $e) {
            $this->logger->error('TechNest_GoogleLogin: token request failed', ['exception' => $e]);
            throw new LocalizedException(__('Google sign-in failed. Please try again.'), $e);
        }

        if ($response->getStatusCode() !== 200 || !is_array($data) || !is_string($data['id_token'] ?? null)) {
            // Google error codes only (invalid_grant, invalid_client...), never the tokens or the secret
            $this->logger->error('TechNest_GoogleLogin: token endpoint refused the code', [
                'status' => $response->getStatusCode(),
                'error' => is_array($data) ? ($data['error'] ?? '') : '',
                'error_description' => is_array($data) ? ($data['error_description'] ?? '') : '',
            ]);
            throw new LocalizedException(__('Google sign-in failed. Please try again.'));
        }
        return $this->idTokenParser->parse($data['id_token'], $this->config->getClientId(), time());
    }
}
