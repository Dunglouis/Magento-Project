<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Test\Unit\Model;

use Magento\Customer\Model\Session as CustomerSession;

/**
 * Customer session without storage: magic getters/unsetters (getBeforeAuthUrl, unsTechnestCheckoutLogin, ...)
 * read and change $data, keyed in snake case like the real session.
 */
class CustomerSessionStub extends CustomerSession
{
    /**
     * @var array<string,mixed>
     */
    public array $data = [];

    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function getData($key = '', $clear = false)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Magic getXxx() / unsXxx()
     *
     * @param string $method
     * @param array<int,mixed> $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $key = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method, 3)));
        if (str_starts_with($method, 'uns')) {
            unset($this->data[$key]);
            return $this;
        }
        return $this->data[$key] ?? null;
    }
}
