<?php
/**
 * @author Calcurates Team
 * @copyright Copyright © 2019-2020 Calcurates (https://www.calcurates.com)
 * @license https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @package Calcurates_ModuleMagento
 */

namespace Calcurates\ModuleMagento\Model\Catalog\Product;

use Calcurates\ModuleMagento\Api\Catalog\Product\ProductAttributesListInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;

class AttributesList implements ProductAttributesListInterface
{

    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    private $eavAttributeRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Attribute\CustomDataFactory
     */
    private $customDataFactory;

    /**
     * @var \Calcurates\ModuleMagento\Api\Data\Catalog\Product\AttributeCustomDataOptionInterfaceFactory
     */
    private $customDataOptionFactory;

    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepository,
        \Calcurates\ModuleMagento\Api\Data\Catalog\Product\AttributeCustomDataInterfaceFactory $customDataFactory,
        \Calcurates\ModuleMagento\Api\Data\Catalog\Product\AttributeCustomDataOptionInterfaceFactory $customDataOptionFactory
    ) {
        $this->eavAttributeRepository = $eavAttributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customDataFactory = $customDataFactory;
        $this->customDataOptionFactory = $customDataOptionFactory;
    }

    /**
     * @return \Calcurates\ModuleMagento\Api\Data\Catalog\Product\AttributeCustomDataInterface[]
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getItems()
    {
        /** @var \Magento\Framework\Api\SearchCriteria $searchCriteria */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductAttributeInterface::ATTRIBUTE_ID, 1, 'gteq')
            ->create();

        $attributesItems = $this->eavAttributeRepository->getList(
            ProductAttributeInterface::ENTITY_TYPE_CODE,
            $searchCriteria
        )->getItems();

        $result = [];
        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute\Interceptor $attributesItem */
        foreach ($attributesItems as $attributesItem) {
            $values = [];
            foreach ($attributesItem->getSource()->getAllOptions() as $option) {
                if (empty($option['value'])) {
                    continue;
                }
                $values[] = $this->getCustomDataOptionObject()
                    ->setLabel($option['label'])
                    ->setValue($option['value']);
            }
            $result[] = $this->getCustomDataObject()
                ->setAttributeId($attributesItem->getAttributeId())
                ->setAttributeCode($attributesItem->getAttributeCode())
                ->setAttributeBackendType($attributesItem->getBackendType())
                ->setAttributeFrontendType($attributesItem->getFrontendInput())
                ->setFrontendLabel($attributesItem->getDefaultFrontendLabel())
                ->setValues($values);
        }

        return $result;
    }

    /**
     * @return \Calcurates\ModuleMagento\Api\Data\Catalog\Product\AttributeCustomDataInterface
     */
    private function getCustomDataObject()
    {
        return $this->customDataFactory->create();
    }

    /**
     * @return \Calcurates\ModuleMagento\Api\Data\Catalog\Product\AttributeCustomDataOptionInterface
     */
    private function getCustomDataOptionObject()
    {
        return $this->customDataOptionFactory->create();
    }
}
