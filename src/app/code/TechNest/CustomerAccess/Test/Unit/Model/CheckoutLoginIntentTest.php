<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Test\Unit\Model;

use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

class CheckoutLoginIntentTest extends TestCase
{
    private const BASE = 'https://technest.test/';

    /**
     * @var CustomerSessionStub
     */
    private CustomerSessionStub $customerSession;

    /**
     * @var CheckoutLoginIntent
     */
    private CheckoutLoginIntent $intent;

    protected function setUp(): void
    {
        $this->customerSession = new CustomerSessionStub();
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route): string => self::BASE . trim($route, '/') . '/'
        );
        $this->intent = new CheckoutLoginIntent($this->customerSession, $urlBuilder);
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function cartUrls(): array
    {
        return [
            'cart' => [self::BASE . 'checkout/cart/', true],
            'cart without slash' => [self::BASE . 'checkout/cart', true],
            'cart index action' => [self::BASE . 'checkout/cart/index/', true],
            'cart with store query' => [self::BASE . 'checkout/cart/?___store=vi', true],
            'checkout' => [self::BASE . 'checkout/', false],
            'home' => [self::BASE, false],
            'product page' => [self::BASE . 'catalog/product/view/id/2148/', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('cartUrls')]
    public function testIsCartUrl(string $url, bool $expected): void
    {
        $this->assertSame($expected, $this->intent->isCartUrl($url));
    }

    public function testClearDropsFlagAndCheckoutReturnUrl(): void
    {
        $this->customerSession->data = [
            'technest_checkout_login' => true,
            'before_auth_url' => self::BASE . 'checkout/',
        ];

        $this->intent->clear();

        $this->assertSame([], $this->customerSession->data);
    }

    public function testClearDropsCheckoutReturnUrlLeftWithoutFlag(): void
    {
        $this->customerSession->data = ['before_auth_url' => self::BASE . 'checkout?___store=vi'];

        $this->intent->clear();

        $this->assertSame([], $this->customerSession->data);
    }

    public function testClearKeepsOtherReturnUrls(): void
    {
        $this->customerSession->data = ['before_auth_url' => self::BASE . 'wishlist/index/add/'];

        $this->intent->clear();

        $this->assertSame(['before_auth_url' => self::BASE . 'wishlist/index/add/'], $this->customerSession->data);
    }
}
