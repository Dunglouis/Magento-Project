<?php
declare(strict_types=1);

namespace TechNest\Mail\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use TechNest\Mail\Model\Config;

/**
 * SMTP connection security
 */
class Encryption implements OptionSourceInterface
{
    /**
     * Options for the Admin select
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::ENCRYPTION_TLS, 'label' => __('TLS (STARTTLS, port 587)')],
            ['value' => Config::ENCRYPTION_SSL, 'label' => __('SSL (port 465)')],
            ['value' => Config::ENCRYPTION_NONE, 'label' => __('None (local test server only)')],
        ];
    }
}
