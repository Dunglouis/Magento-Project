<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TechNest\GoogleLogin\Model\Config;

/**
 * "Sign in with Google" button on the sign-in and register pages
 */
class GoogleButton implements ArgumentInterface
{
    /**
     * @param Config $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Whether Google sign-in is configured
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * URL that starts the Google sign-in
     *
     * @return string
     */
    public function getStartUrl(): string
    {
        return $this->urlBuilder->getUrl('technest_google/oauth/start');
    }
}
