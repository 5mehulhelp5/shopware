/**
 * @sw-package framework
 */
import template from './sw-custom-field-type-number.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
            numberTypes: [
                {
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelInt'),
                    value: 'int',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelFloat'),
                    value: 'float',
                },
            ],
        };
    },

    watch: {
        'currentCustomField.config.numberType'(value) {
            this.currentCustomField.type = value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.numberType) {
                this.currentCustomField.config.numberType = 'int';
            }
        },
    },
};
