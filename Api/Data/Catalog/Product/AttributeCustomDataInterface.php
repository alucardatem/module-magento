<?php
/**
 * @author Calcurates Team
 * @copyright Copyright (c) 2019 Calcurates (https://www.calcurates.com)
 * @package Calcurates_ModuleMagento
 */

namespace Calcurates\ModuleMagento\Api\Data\Catalog\Product;

/**
 * @api
 */
interface AttributeCustomDataInterface
{
    const ATTRIBUTE_ID = 'attribute_id';
    const ATTRIBUTE_CODE = 'attribute_code';
    const FRONTEND_LABEL = 'frontend_label';

    /**
     * @return int
     */
    public function getAttributeId();

    /**
     * @return string
     */
    public function getAttributeCode();

    /**
     * @return string
     */
    public function getFrontendLabel();

    /**
     * @param int $attributeId
     *
     * @return AttributeCustomDataInterface
     */
    public function setAttributeId($attributeId);

    /**
     * @param string $frontendLabel
     *
     * @return AttributeCustomDataInterface
     */
    public function setFrontendLabel($frontendLabel);

    /**
     * @param string $attributeCode
     *
     * @return AttributeCustomDataInterface
     */
    public function setAttributeCode($attributeCode);
}