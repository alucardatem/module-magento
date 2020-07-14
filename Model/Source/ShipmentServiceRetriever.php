<?php

namespace Calcurates\ModuleMagento\Model\Source;

use Calcurates\ModuleMagento\Api\Data\CustomSalesAttributesInterface;
use Calcurates\ModuleMagento\Model\Carrier\ShippingMethodManager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;

class ShipmentServiceRetriever
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ShippingMethodManager
     */
    private $shippingMethodManager;

    /**
     * ShipmentServiceRetriever constructor.
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer, ShippingMethodManager $shippingMethodManager)
    {
        $this->serializer = $serializer;
        $this->shippingMethodManager = $shippingMethodManager;
    }

    /**
     * @param Order $order
     * @param string $requestedSourceCode
     * @return mixed
     */
    public function retrieve($order, $requestedSourceCode)
    {
        $carrierData = $this->shippingMethodManager->getCarrierData(
            $order->getShippingMethod(),
            $order->getShippingDescription()
        );
        if (!$carrierData) {
            return "";
        }
        $carrierId = $carrierData->getCarrierId();
        $shippingServices = $carrierData->getServiceIdsString();
        $shippingServicesArray = $carrierData->getServiceIds();

        try {
            $carrierServicesToOrigins = $order->getData(CustomSalesAttributesInterface::CARRIER_SOURCE_CODE_TO_SERVICE);
            $carrierServicesToOrigins = $this->serializer->unserialize($carrierServicesToOrigins);
        } catch (\InvalidArgumentException $e) {
            $carrierServicesToOrigins = [];
        }

        $sourceCodesToServices = $carrierServicesToOrigins[$carrierId][$shippingServices] ?? [];

        return $sourceCodesToServices[$requestedSourceCode] ?? current($shippingServicesArray);
    }
}
