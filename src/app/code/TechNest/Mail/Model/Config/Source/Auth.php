<?php
declare(strict_types=1);

namespace TechNest\Mail\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use TechNest\Mail\Model\Config;

/**
 * SMTP authentication method
 */
class Auth implements OptionSourceInterface
{
    /**
     * Options for the Admin select
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::AUTH_LOGIN, 'label' => __('Login')],
            ['value' => Config::AUTH_PLAIN, 'label' => __('Plain')],
            ['value' => Config::AUTH_NONE, 'label' => __('None')],
        ];
    }
}
