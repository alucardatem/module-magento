<?php
/**
 * @author Calcurates Team
 * @copyright Copyright © 2019-2020 Calcurates (https://www.calcurates.com)
 * @license https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @package Calcurates_ModuleMagento
 */

namespace Calcurates\ModuleMagento\Client\Request;

use Calcurates\ModuleMagento\Model\Source\GetSourceCodesPerSkus;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region as RegionResource;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;

class RateRequestBuilder
{
    /**
     * @var array
     */
    private $regionNamesCache = [];

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var RegionResource
     */
    private $regionResource;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var GetSourceCodesPerSkus
     */
    private $getSourceCodesPerSkus;

    /**
     * @var ProductAttributesService
     */
    private $productAttributesService;

    /**
     * RateRequestBuilder constructor.
     * @param RegionFactory $regionFactory
     * @param RegionResource $regionResource
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param GetSourceCodesPerSkus $getSourceCodesPerSkus
     * @param ProductAttributesService $productAttributesService
     */
    public function __construct(
        RegionFactory $regionFactory,
        RegionResource $regionResource,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        GetSourceCodesPerSkus $getSourceCodesPerSkus,
        ProductAttributesService $productAttributesService
    ) {
        $this->regionFactory = $regionFactory;
        $this->regionResource = $regionResource;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->getSourceCodesPerSkus = $getSourceCodesPerSkus;
        $this->productAttributesService = $productAttributesService;
    }

    /**
     * @param RateRequest $request
     * @param Item[] $items
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function build(RateRequest $request, array $items)
    {
        /**
         * @var $quote \Magento\Quote\Model\Quote
         */
        $quote = current($items)->getQuote();
        $customerData = $this->getCustomerData($quote);
        $streetArray = explode("\n", $request->getDestStreet());
        $customer = $quote->getCustomer();

        $apiRequestBody = [
            'shipTo' => [
                'country' => $request->getDestCountryId(),
                'regionCode' => $request->getDestRegionId() ? $request->getDestRegionCode() : null,
                'regionName' => $this->getRegionNameById($request->getDestRegionId()) ?: $request->getDestRegionCode(),
                'postalCode' => $request->getDestPostcode(),
                'city' => $request->getDestCity(),
                'addressLine1' => $streetArray[0],
                'addressLine2' => $streetArray[1] ?? '',
                'contactName' => $customerData['contactName'],
                'companyName' => $customerData['companyName'],
                'contactPhone' => $customerData['contactPhone'],
            ],
            'customerGroup' => $customer->getGroupId() ?: 0,
            'promo' => null,
            'products' => [],
            // storeId in $request - from quote, and not correct if we open store via store url
            // setting "Use store codes in URL"
            'storeView' => $this->storeManager->getStore()->getId(),
            'promoCode' => (string)$quote->getCouponCode(),
        ];

        $itemsSkus = [];
        foreach ($items as $item) {
            $itemsSkus[$item->getSku()] = $item->getSku();
        }

        $itemsSkus = array_values($itemsSkus);
        $itemsSourceCodes = $this->getSourceCodesPerSkus->execute($itemsSkus);

        foreach ($items as $item) {
            $product = $this->productRepository->getById(
                $item->getProductId(),
                false,
                null,
                true
            );

            $apiRequestBody['products'][] = [
                'quoteItemId' => $item->getId(),
                'priceWithTax' => round($item->getBasePriceInclTax(), 2),
                'priceWithoutTax' => round($item->getBasePrice(), 2),
                'discountAmount' => round($item->getBaseDiscountAmount() / $item->getQty(), 2),
                'quantity' => $item->getQty(),
                'weight' => $product->isVirtual() ? 0 : $item->getWeight(),
                'sku' => $item->getSku(),
                'categories' => $product->getCategoryIds(),
                'attributes' => $this->productAttributesService->getAttributes($product),
                'inventories' => $itemsSourceCodes[$item->getSku()] ?? []
            ];
        }

        return $apiRequestBody;
    }

    /**
     * @param string $regionId
     *
     * @return string|null
     */
    private function getRegionNameById($regionId)
    {
        if (!$regionId) {
            return null;
        }

        if (!isset($this->regionNamesCache[$regionId])) {
            $regionInstance = $this->regionFactory->create();
            $this->regionResource->load($regionInstance, $regionId);
            $this->regionNamesCache[$regionId] = $regionInstance->getName();
        }

        return $this->regionNamesCache[$regionId];
    }

    /**
     * Collect customer information from shipping address
     *
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return array
     */
    private function getCustomerData(\Magento\Quote\Model\Quote $quote)
    {
        $customerData = [
            'contactName' => '',
            'companyName' => '',
            'contactPhone' => '',
        ];
        $shipAddress = $quote->getShippingAddress();

        $customerData['contactName'] = $shipAddress->getPrefix() . ' ';
        $customerData['contactName'] .= $shipAddress->getFirstname() ? $shipAddress->getFirstname() . ' ' : '';
        $customerData['contactName'] .= $shipAddress->getMiddlename() ? $shipAddress->getMiddlename() . ' ' : '';
        $customerData['contactName'] = trim($customerData['contactName'] . $shipAddress->getLastname());

        $customerData['companyName'] = $shipAddress->getCompany();
        $customerData['contactPhone'] = $shipAddress->getTelephone();

        return $customerData;
    }
}
