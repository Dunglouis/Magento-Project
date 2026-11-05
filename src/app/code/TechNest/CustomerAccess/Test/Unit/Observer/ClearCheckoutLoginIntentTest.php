<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Test\Unit\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Url\DecoderInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;
use TechNest\CustomerAccess\Observer\ClearCheckoutLoginIntent;

class ClearCheckoutLoginIntentTest extends TestCase
{
    private const CART = 'https://technest.test/checkout/cart/';
    private const PRODUCT = 'https://technest.test/catalog/product/view/id/2148/';

    /**
     * @var CheckoutLoginIntent&MockObject
     */
    private CheckoutLoginIntent $intent;

    /**
     * @var Quote&Stub
     */
    private Quote $quote;

    /**
     * @var ClearCheckoutLoginIntent
     */
    private ClearCheckoutLoginIntent $observer;

    protected function setUp(): void
    {
        $this->intent = $this->createMock(CheckoutLoginIntent::class);
        $this->intent->method('isCartUrl')->willReturnCallback(static fn (string $url): bool => $url === self::CART);
        $this->quote = $this->createStub(Quote::class);
        $checkoutSession = $this->createStub(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($this->quote);
        $decoder = $this->createStub(DecoderInterface::class);
        $decoder->method('decode')->willReturnCallback(static fn (string $value): string => base64_decode($value));
        $this->observer = new ClearCheckoutLoginIntent($this->intent, $checkoutSession, $decoder);
    }

    /**
     * @return array<string,array{string,array<string,string>,bool,string}>
     */
    public static function requests(): array
    {
        return [
            'login from checkout redirect' => ['customer_account_login', ['tn_checkout' => '1'], false, 'start'],
            'register from checkout popup' => ['customer_account_create', ['tn_checkout' => '1'], false, 'start'],
            'header login on filled cart' => [
                'customer_account_login', ['referer' => base64_encode(self::CART)], true, 'start'
            ],
            'header login on empty cart' => [
                'customer_account_login', ['referer' => base64_encode(self::CART)], false, 'clear'
            ],
            'header login on product page' => [
                'customer_account_login', ['referer' => base64_encode(self::PRODUCT)], true, 'clear'
            ],
            'login page without referer' => ['customer_account_login', [], true, 'clear'],
            'login post keeps intent' => ['customer_account_loginPost', [], true, 'none'],
            'checkout keeps intent' => ['checkout_index_index', [], true, 'none'],
            'other page drops intent' => ['catalog_product_view', [], true, 'clear'],
        ];
    }

    /**
     * @param string $action
     * @param array<string,string> $params
     * @param bool $cartHasItems
     * @param string $expected start|clear|none
     */
    #[DataProvider('requests')]
    public function testExecute(string $action, array $params, bool $cartHasItems, string $expected): void
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getFullActionName')->willReturn($action);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);
        $this->quote->method('hasItems')->willReturn($cartHasItems);

        $this->intent->expects($expected === 'start' ? $this->once() : $this->never())->method('start');
        $this->intent->expects($expected === 'clear' ? $this->once() : $this->never())->method('clear');

        $this->observer->execute(new Observer(['request' => $request]));
    }
}
