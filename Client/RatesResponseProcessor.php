<?php
/**
 * @author Calcurates Team
 * @copyright Copyright © 2020 Calcurates (https://www.calcurates.com)
 * @license https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @package Calcurates_ModuleMagento
 */

namespace Calcurates\ModuleMagento\Client;

use Calcurates\ModuleMagento\Api\Data\CustomSalesAttributesInterface;
use Calcurates\ModuleMagento\Model\Carrier;
use Calcurates\ModuleMagento\Model\Config as CalcuratesConfig;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Framework\Serialize\SerializerInterface;

class RatesResponseProcessor
{
    /**
     * @var ResultFactory
     */
    private $rateFactory;

    /**
     * @var ErrorFactory
     */
    private $rateErrorFactory;

    /**
     * @var CalcuratesConfig
     */
    private $calcuratesConfig;

    /**
     * @var RateBuilder
     */
    private $rateBuilder;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * RatesResponseProcessor constructor.
     * @param ResultFactory $rateFactory
     * @param ErrorFactory $rateErrorFactory
     * @param CalcuratesConfig $calcuratesConfig
     * @param RateBuilder $rateBuilder
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ResultFactory $rateFactory,
        ErrorFactory $rateErrorFactory,
        CalcuratesConfig $calcuratesConfig,
        RateBuilder $rateBuilder,
        SerializerInterface $serializer
    ) {
        $this->rateFactory = $rateFactory;
        $this->rateErrorFactory = $rateErrorFactory;
        $this->calcuratesConfig = $calcuratesConfig;
        $this->rateBuilder = $rateBuilder;
        $this->serializer = $serializer;
    }

    /**
     * Prepare shipping rate result based on response
     *
     * @param array $response
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return Result
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function processResponse($response, $quote)
    {
        $result = $this->rateFactory->create();

        // status only for errors
        $status = $response['status'] ?? null;
        if (!$response || empty($response['shippingOptions']) || $status) {
            $this->processFailedRate(
                $this->calcuratesConfig->getTitle($quote->getStoreId()),
                $result,
                $this->calcuratesConfig->getErrorMessage($quote->getStoreId())
            );

            return $result;
        }

        $this->processOrigins($response['origins'], $quote);
        $shippingOptions = $response['shippingOptions'];
        $this->processFreeShipping($shippingOptions['freeShipping'], $result);
        $this->processFlatRates($shippingOptions['flatRates'], $result);
        $this->processTableRates($shippingOptions['tableRates'], $result);
        $this->processCarriers($shippingOptions['carriers'], $result, $quote);

        return $result;
    }

    /**
     * @param string $rateName
     * @param Result $result
     * @param string $message
     */
    public function processFailedRate(string $rateName, Result $result, string $message = '')
    {
        $error = $this->rateErrorFactory->create();
        $error->setCarrier(Carrier::CODE);
        $error->setCarrierTitle($rateName);
        $error->setErrorMessage($message);

        $result->append($error);
    }

    /**
     * @param array $flatRates
     * @param Result $result
     */
    private function processFlatRates(array $flatRates, Result $result)
    {
        foreach ($flatRates as $responseRate) {
            if (!$responseRate['success']) {
                if ($responseRate['message']) {
                    $this->processFailedRate($responseRate['name'], $result, $responseRate['message']);
                }
                continue;
            }

            $rate = $this->rateBuilder->build(
                'flatRates_' . $responseRate['id'],
                $responseRate
            );
            $result->append($rate);
        }
    }

    /**
     * @param array $freeShipping
     * @param Result $result
     */
    private function processFreeShipping(array $freeShipping, Result $result)
    {
        foreach ($freeShipping as $responseRate) {
            if (!$responseRate['success']) {
                if ($responseRate['message']) {
                    $this->processFailedRate($responseRate['name'], $result, $responseRate['message']);
                }
                continue;
            }

            $responseRate['rate'] = [
                'cost' => 0,
                'currency' => null,
            ];

            $rate = $this->rateBuilder->build(
                'freeShipping' . $responseRate['id'],
                $responseRate
            );
            $result->append($rate);
        }
    }

    /**
     * @param array $tableRates
     * @param Result $result
     */
    private function processTableRates(array $tableRates, Result $result)
    {
        foreach ($tableRates as $tableRate) {
            if (!$tableRate['success']) {
                if ($tableRate['message']) {
                    $this->processFailedRate($tableRate['name'], $result, $tableRate['message']);
                }

                continue;
            }

            foreach ($tableRate['methods'] as $responseRate) {
                if (!$responseRate['success']) {
                    if ($responseRate['message']) {
                        $this->processFailedRate($responseRate['name'], $result, $responseRate['message']);
                    }

                    continue;
                }

                $rate = $this->rateBuilder->build(
                    'tableRate_' . $tableRate['id'] . '_' . $responseRate['id'],
                    $responseRate
                );
                $result->append($rate);
            }
        }
    }

    /**
     * @param array $carriers
     * @param Result $result
     * @param \Magento\Quote\Model\Quote $quote
     */
    private function processCarriers(array $carriers, Result $result, $quote)
    {
        $carrierServicesToOrigins = [];
        foreach ($carriers as $carrier) {
            if (!$carrier['success']) {
                if ($carrier['message']) {
                    $this->processFailedRate($carrier['name'], $result, $carrier['message']);
                }

                continue;
            }

            foreach ($carrier['rates'] as $responseRate) {
                if (!$responseRate['success']) {
                    if ($responseRate['message']) {
                        $this->processFailedRate($responseRate['name'], $result, $responseRate['message']);
                    }

                    continue;
                }

                $serviceIds = [];
                $serviceNames = [];
                $sourceToServiceId = [];
                foreach ($responseRate['services'] as $service) {
                    $name = $serviceNames[$service['name']] ?? $service['name'] . ' - ';
                    if (isset($service['package']['name'])) {
                        $name .= $service['package']['name'];
                    }

                    $serviceNames[$service['name']] = $name;
                    $serviceIds[] = $service['id'];

                    $sourceCode = $service['origin']['targetValue']['targetId'] ?? null;

                    if ($sourceCode) {
                        $sourceToServiceId[$sourceCode] = $service['id'];
                    }
                }

                $responseRate['id'] = implode(',', $serviceIds);
                $responseRate['name'] = implode(', ', array_map(static function ($serviceName) {
                    return rtrim($serviceName, ' - ');
                }, $serviceNames));

                $carrierServicesToOrigins[$carrier['id']][$responseRate['id']] = $sourceToServiceId;

                $rate = $this->rateBuilder->build(
                    'carrier_' . $carrier['id'] . '_' . $responseRate['id'],
                    $responseRate,
                    $carrier['name']
                );
                $result->append($rate);
            }
        }

        $quote->setData(CustomSalesAttributesInterface::CARRIER_SOURCE_CODE_TO_SERVICE, $this->serializer->serialize($carrierServicesToOrigins));
    }

    /**
     * @param array $origins
     * @param \Magento\Quote\Model\Quote $quote
     */
    private function processOrigins(array $origins, $quote)
    {
        $quoteItemIdToSourceCode = [];
        foreach ($origins as $origin) {
            $sourceCode = $origin['origin']['targetValue']['targetId'] ?? null;
            if ($sourceCode === null) {
                continue;
            }
            foreach ($origin['products'] as $product) {
                $quoteItemId = $product['quoteItemId'];
                $quoteItemIdToSourceCode[$quoteItemId] = $sourceCode;
            }
        }

        foreach ($quote->getAllItems() as $quoteItem) {
            /** @var Item $quoteItem */
            if (array_key_exists($quoteItem->getId(), $quoteItemIdToSourceCode)) {
                $quoteItem->setData(CustomSalesAttributesInterface::SOURCE_CODE, $quoteItemIdToSourceCode[$quoteItem->getId()]);
            }
        }
    }
}
