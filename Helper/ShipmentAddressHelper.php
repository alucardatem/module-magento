<?php

namespace Calcurates\ModuleMagento\Helper;

use Calcurates\ModuleMagento\Client\CalcuratesClient;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\ScopeInterface;

class ShipmentAddressHelper extends AbstractHelper
{
    /**
     * @var Address\Renderer
     */
    private $addressRenderer;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $authSession;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    private $regionFactory;

    /**
     * @var \Magento\Sales\Model\Order\AddressFactory
     */
    private $addressFactory;

    /**
     * @var CalcuratesClient
     */
    private $calcuratesClient;

    /**
     * ShipmentAddressHelper constructor.
     * @param Context $context
     * @param Address\Renderer $addressRenderer
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Sales\Model\Order\AddressFactory $addressFactory
     * @param CalcuratesClient $calcuratesClient
     */
    public function __construct(
        Context $context,
        Address\Renderer $addressRenderer,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Sales\Model\Order\AddressFactory $addressFactory,
        CalcuratesClient $calcuratesClient
    ) {
        parent::__construct($context);
        $this->addressRenderer = $addressRenderer;
        $this->authSession = $authSession;
        $this->regionFactory = $regionFactory;
        $this->addressFactory = $addressFactory;
        $this->calcuratesClient = $calcuratesClient;
    }

    /**
     * Returns string with formatted address
     *
     * @param Address $address
     * @return null|string
     */
    public function getFormattedAddress(Address $address)
    {
        return $this->addressRenderer->format($address, 'html');
    }

    /**
     * @param Shipment $orderShipment
     * @return string|null
     * @throws LocalizedException
     */
    public function getOriginAddressHtml(Shipment $orderShipment)
    {
        $admin = $this->authSession->getUser();
        $shipmentStoreId = $orderShipment->getStoreId();
        $shipperRegionCode = $this->scopeConfig->getValue(
            Shipment::XML_PATH_STORE_REGION_ID,
            ScopeInterface::SCOPE_STORE,
            $shipmentStoreId
        );
        if (is_numeric($shipperRegionCode)) {
            $shipperRegionCode = $this->regionFactory->create()->load($shipperRegionCode)->getCode();
        }

        $originStreet1 = $this->scopeConfig->getValue(
            Shipment::XML_PATH_STORE_ADDRESS1,
            ScopeInterface::SCOPE_STORE,
            $shipmentStoreId
        );
        $originStreet2 = $this->scopeConfig->getValue(
            Shipment::XML_PATH_STORE_ADDRESS2,
            ScopeInterface::SCOPE_STORE,
            $shipmentStoreId
        );
        $storeInfo = new DataObject(
            (array)$this->scopeConfig->getValue(
                'general/store_information',
                ScopeInterface::SCOPE_STORE,
                $shipmentStoreId
            )
        );

        $addressData = [
            'firstname' => $admin->getFirstName(),
            'lastname' => $admin->getLastName(),
            'company' => $storeInfo->getName(),
            'street' => trim($originStreet1 . ' ' . $originStreet2),
            'city' => $this->scopeConfig->getValue(
                Shipment::XML_PATH_STORE_CITY,
                ScopeInterface::SCOPE_STORE,
                $shipmentStoreId
            ),
            'postcode' => $this->scopeConfig->getValue(
                Shipment::XML_PATH_STORE_ZIP,
                ScopeInterface::SCOPE_STORE,
                $shipmentStoreId
            ),
            'region' => $shipperRegionCode,
            'country_id' => $this->scopeConfig->getValue(
                Shipment::XML_PATH_STORE_COUNTRY_ID,
                ScopeInterface::SCOPE_STORE,
                $shipmentStoreId
            ),
            'email' => $admin->getEmail(),
            'telephone' => $storeInfo->getPhone(),
        ];

        /** @var Address $address */
        $address = $this->addressFactory->create(['data' => $addressData]);
        $address->setAddressType(Address::TYPE_SHIPPING);

        return $this->getFormattedAddress($address);
    }

    /**
     * @param Order $order
     * @return array
     */
    public function getShippingServices(Order $order)
    {
        $method = $order->getShippingMethod(true)->getMethod();
        $methodId = current(explode('_', str_replace('carrier_', '', $method)));
        $shippingServices = $this->calcuratesClient->getShippingServices($methodId, $order->getStoreId());

        if (empty($shippingServices)) {
            $shippingServiceLabel = explode('-', $order->getShippingDescription());
            $shippingServiceLabel = end($shippingServiceLabel);
            $shippingServiceValue = $this->getShippingServiceId($order);
            $shippingServices[] = [
                'value' => $shippingServiceValue,
                'label' => $shippingServiceLabel
            ];
        }

        return $shippingServices;
    }

    /**
     * @param Order $order
     * @return mixed
     */
    public function getShippingServiceId(Order $order)
    {
        $method = explode('_', $order->getShippingMethod(true)->getMethod());
        return end($method);
    }
}
