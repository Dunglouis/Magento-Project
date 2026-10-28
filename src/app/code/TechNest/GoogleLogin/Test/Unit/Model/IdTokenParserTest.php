<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\GoogleLogin\Model\IdTokenParser;

class IdTokenParserTest extends TestCase
{
    private const CLIENT_ID = 'client-123.apps.googleusercontent.com';
    private const NOW = 1_800_000_000;

    /**
     * Unsigned JWT with the given claims on top of valid defaults
     *
     * @param array<string,mixed> $claims
     * @return string
     */
    private function token(array $claims): string
    {
        $claims = array_filter($claims + [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'exp' => self::NOW + 3600,
            'sub' => '1098765',
            'email' => 'an.nguyen@gmail.com',
            'email_verified' => true,
            'given_name' => 'An',
            'family_name' => 'Nguyễn',
        ], fn ($value) => $value !== null);
        $part = fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        return $part(['alg' => 'RS256']) . '.' . $part($claims) . '.signature';
    }

    private function parser(): IdTokenParser
    {
        return new IdTokenParser(new Json());
    }

    public function testValidTokenMapsToProfile(): void
    {
        $profile = $this->parser()->parse($this->token([]), self::CLIENT_ID, self::NOW);

        $this->assertSame('1098765', $profile->sub);
        $this->assertSame('an.nguyen@gmail.com', $profile->email);
        $this->assertSame('An', $profile->firstname);
        $this->assertSame('Nguyễn', $profile->lastname);
    }

    public function testNameIsSplitWhenGivenAndFamilyNamesAreMissing(): void
    {
        $token = $this->token(['given_name' => null, 'family_name' => null, 'name' => 'Trần Thị Bình']);
        $profile = $this->parser()->parse($token, self::CLIENT_ID, self::NOW);

        $this->assertSame('Trần', $profile->firstname);
        $this->assertSame('Thị Bình', $profile->lastname);
    }

    public function testSingleWordNameFillsBothNames(): void
    {
        $token = $this->token(['given_name' => 'Minh', 'family_name' => null]);
        $profile = $this->parser()->parse($token, self::CLIENT_ID, self::NOW);

        $this->assertSame('Minh', $profile->firstname);
        $this->assertSame('Minh', $profile->lastname);
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function invalidClaimsProvider(): array
    {
        return [
            'other client' => [['aud' => 'someone-else']],
            'other issuer' => [['iss' => 'https://evil.example.com']],
            'expired' => [['exp' => self::NOW - 3600]],
            'no subject' => [['sub' => '']],
            'unverified email' => [['email_verified' => false]],
            'no email' => [['email' => null]],
        ];
    }

    /**
     * @param array<string,mixed> $claims
     */
    #[DataProvider('invalidClaimsProvider')]
    public function testInvalidTokensAreRejected(array $claims): void
    {
        $this->expectException(LocalizedException::class);
        $this->parser()->parse($this->token($claims), self::CLIENT_ID, self::NOW);
    }

    public function testMalformedTokenIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->parser()->parse('not-a-jwt', self::CLIENT_ID, self::NOW);
    }
}
