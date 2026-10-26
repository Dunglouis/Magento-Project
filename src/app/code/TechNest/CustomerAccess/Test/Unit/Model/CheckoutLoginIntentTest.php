<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Test\Unit\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

class CheckoutLoginIntentTest extends TestCase
{
    private const BASE = 'https://technest.test/';

    /**
     * Stub whose magic getters/unsetters (getBeforeAuthUrl, unsTechnestCheckoutLogin, ...) work on its $data
     *
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var CheckoutLoginIntent
     */
    private CheckoutLoginIntent $intent;

    protected function setUp(): void
    {
        $this->customerSession = new class extends CustomerSession {
            /** @var array<string,mixed> */
            public array $data = [];

            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
            public function __construct()
            {
            }

            // phpcs:ignore Magento2.Functions.StaticFunction
            public function getData($key = '', $clear = false)
            {
                return $this->data[$key] ?? null;
            }

            public function __call($method, $args)
            {
                $key = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method, 3)));
                if (str_starts_with($method, 'uns')) {
                    unset($this->data[$key]);
                    return $this;
                }
                return $this->data[$key] ?? null;
            }
        };
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
