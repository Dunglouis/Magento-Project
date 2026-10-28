<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Test\Unit\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TechNest\GoogleLogin\Model\Config;
use TechNest\GoogleLogin\Model\GoogleProfile;
use TechNest\GoogleLogin\Model\IdTokenParser;
use TechNest\GoogleLogin\Model\OAuthClient;

class OAuthClientTest extends TestCase
{
    /** @var array<int,array<string,mixed>> */
    private array $history = [];

    /**
     * @param Response[] $responses
     * @param IdTokenParser|null $parser
     * @return OAuthClient
     */
    private function client(array $responses, ?IdTokenParser $parser = null): OAuthClient
    {
        $config = $this->createStub(Config::class);
        $config->method('getClientId')->willReturn('client-123');
        $config->method('getClientSecret')->willReturn('secret-456');
        $config->method('getRedirectUri')->willReturn('http://localhost/technest_google/oauth/callback');
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new OAuthClient(
            $config,
            new Client(['handler' => $stack]),
            $parser ?? $this->createStub(IdTokenParser::class),
            new Json(),
            new NullLogger()
        );
    }

    public function testAuthorizationUrlAsksForOpenIdWithPkce(): void
    {
        $url = $this->client([])->getAuthorizationUrl('state-1', 'challenge-1');
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith(OAuthClient::AUTHORIZATION_URL . '?', $url);
        $this->assertSame([
            'client_id' => 'client-123',
            'redirect_uri' => 'http://localhost/technest_google/oauth/callback',
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => 'state-1',
            'code_challenge' => 'challenge-1',
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], $query);
    }

    public function testCodeIsExchangedWithVerifierAndIdTokenParsed(): void
    {
        $profile = new GoogleProfile('sub-1', 'an@gmail.com', 'An', 'Nguyễn');
        $parser = $this->createMock(IdTokenParser::class);
        $parser->expects($this->once())->method('parse')
            ->with('header.payload.sig', 'client-123', $this->isInt())->willReturn($profile);
        $response = new Response(200, [], '{"id_token":"header.payload.sig","access_token":"x"}');
        $client = $this->client([$response], $parser);

        $this->assertSame($profile, $client->fetchProfile('code-1', 'verifier-1'));

        $request = $this->history[0]['request'];
        parse_str((string)$request->getBody(), $form);
        $this->assertSame(OAuthClient::TOKEN_URL, (string)$request->getUri());
        $this->assertSame('authorization_code', $form['grant_type']);
        $this->assertSame('code-1', $form['code']);
        $this->assertSame('verifier-1', $form['code_verifier']);
        $this->assertSame('secret-456', $form['client_secret']);
    }

    public function testRefusedCodeFails(): void
    {
        $client = $this->client([new Response(400, [], '{"error":"invalid_grant"}')]);

        $this->expectException(LocalizedException::class);
        $client->fetchProfile('used-code', 'verifier-1');
    }
}
