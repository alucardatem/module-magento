/**
 * @author Calcurates Team
 * @copyright Copyright © 2019 Calcurates (https://www.calcurates.com)
 * @license https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @package Calcurates_ModuleMagento
 */

define([
    'jquery',
    'mageUtils',
    '../shipping-rates-validation-rules/calcurates',
    'mage/translate'
], function ($, utils, validationRules, $t) {
    'use strict';

    return {
        validationErrors: [],

        /**
         * @param {Object} address
         * @return {Boolean}
         */
        validate: function (address) {
            var self = this;

            this.validationErrors = [];
            $.each(validationRules.getRules(), function (field, rule) {

                if (rule.required && utils.isEmpty(address[field])) {
                    var message = $t('Field ') + field + $t(' is required.'),
                        regionFields = ['region', 'region_id', 'region_id_input'];

                    if (
                        $.inArray(field, regionFields) === -1 ||
                        utils.isEmpty(address.region) && utils.isEmpty(address['region_id'])
                    ) {
                        self.validationErrors.push(message);
                    }
                }
            });

            return !this.validationErrors.length;
        }
    };
});
