<?php
declare(strict_types=1);

namespace TechNest\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use TechNest\Shipping\Model\FeeCalculator;
use TechNest\Shipping\Model\Provinces;

/**
 * Per-order delivery fee by the zone (North, Central, South) of the destination province.
 * Free with a free shipping coupon, or above the threshold of the Free Shipping method.
 */
class Regional extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'technest_regional';
    private const METHOD = 'regional';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param Provinces $provinces
     * @param FeeCalculator $feeCalculator
     * @param mixed[] $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly Provinces $provinces,
        private readonly FeeCalculator $feeCalculator,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * One rate for the zone of the destination province
     *
     * @param RateRequest $request
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $zone = $this->resolveZone($request);
        $fee = $this->feeCalculator->calculate(
            $zone,
            [
                Provinces::NORTH => (float)$this->getConfigData('price_north'),
                Provinces::CENTRAL => (float)$this->getConfigData('price_central'),
                Provinces::SOUTH => (float)$this->getConfigData('price_south'),
                'default' => (float)$this->getConfigData('price_default'),
            ],
            (bool)$request->getFreeShipping(),
            (float)$request->getPackageValueWithDiscount(),
            $this->getFreeThreshold()
        );

        $name = (string)$this->getConfigData('name');
        if ($zone !== null) {
            $name .= ' (' . $this->getZoneLabel($zone) . ')';
        }

        $method = $this->rateMethodFactory->create();
        $method->setCarrier(self::CODE);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod(self::METHOD);
        $method->setMethodTitle($name);
        $method->setPrice($fee);
        $method->setCost($fee);

        /** @var Result $result */
        $result = $this->rateResultFactory->create();
        $result->append($method);
        return $result;
    }

    /**
     * @inheritdoc
     *
     * @return string[]
     */
    public function getAllowedMethods()
    {
        return [self::METHOD => (string)$this->getConfigData('name')];
    }

    /**
     * Zone from the region dropdown, or from the province typed as text
     *
     * @param RateRequest $request
     * @return string|null
     */
    private function resolveZone(RateRequest $request): ?string
    {
        if ($request->getDestCountryId() !== Provinces::COUNTRY) {
            return null;
        }
        $region = (string)$request->getDestRegionCode();
        return $this->provinces->getZoneByCode($region) ?? $this->provinces->getZoneByName($region);
    }

    /**
     * Minimum order amount of the Free Shipping method, when this carrier follows it
     *
     * @return float|null
     */
    private function getFreeThreshold(): ?float
    {
        $freeShippingActive = $this->_scopeConfig->isSetFlag(
            'carriers/freeshipping/active',
            ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
        if (!$freeShippingActive || !$this->getConfigFlag('free_over_threshold')) {
            return null;
        }
        $threshold = $this->_scopeConfig->getValue(
            'carriers/freeshipping/free_shipping_subtotal',
            ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
        return is_numeric($threshold) ? (float)$threshold : null;
    }

    /**
     * Translated zone name
     *
     * @param string $zone
     * @return string
     */
    private function getZoneLabel(string $zone): string
    {
        return (string)match ($zone) {
            Provinces::NORTH => __('North Vietnam'),
            Provinces::CENTRAL => __('Central Vietnam'),
            default => __('South Vietnam'),
        };
    }
}
