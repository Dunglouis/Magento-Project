<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Controller\Hello;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

/**
 * Trang in ra chữ "Hello World".
 *
 * URL: /<frontName>/hello/helloword (frontName khai báo trong etc/frontend/routes.xml)
 */
class HelloWord implements HttpGetActionInterface
{
    public function __construct(
        private readonly RawFactory $rawFactory
    ) {
    }

    /**
     * Magento gọi hàm này khi có request tới URL của controller.
     */
    public function execute(): ResultInterface
    {
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setContents('Hello World');

        return $result;
    }
}
