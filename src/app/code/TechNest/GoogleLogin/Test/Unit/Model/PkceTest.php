<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use TechNest\GoogleLogin\Model\Pkce;

class PkceTest extends TestCase
{
    public function testChallengeMatchesRfc7636Example(): void
    {
        // RFC 7636 Appendix B
        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            (new Pkce())->challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')
        );
    }

    public function testVerifierIsUrlSafeAndLongEnough(): void
    {
        $verifier = (new Pkce())->createVerifier();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]{43,128}$/', $verifier);
    }

    public function testStatesAreRandom(): void
    {
        $pkce = new Pkce();

        $this->assertNotSame($pkce->createState(), $pkce->createState());
        $this->assertSame(32, strlen($pkce->createState()));
    }
}
