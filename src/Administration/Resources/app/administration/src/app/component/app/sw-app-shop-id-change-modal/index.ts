/**
 * @sw-package framework
 */

import type { FingerprintComparisonResult } from 'src/core/service/api/shop-id-change.service';
import template from './sw-app-shop-id-change-modal.html.twig';
import './sw-app-shop-id-change-modal.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['shopIdChangeService'],

    emits: ['modal-close'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        comparisonResult: {
            type: Object as PropType<FingerprintComparisonResult>,
            required: true,
        },
    },

    data() {
        return {
            strategies: [],
            selectedStrategy: null,
            isLoading: true,
        };
    },

    created() {
        this.shopIdChangeService
            .getChangeStrategies()
            .then((strategies) => {
                this.strategies = strategies;
                this.selectedStrategy = strategies[0];
            })
            .then(() => {
                this.isLoading = false;
            });
    },

    methods: {
        closeModal() {
            this.$emit('modal-close');
        },

        setSelectedStrategy(strategy) {
            this.selectedStrategy = strategy;
        },

        isSelected({ name }) {
            return !!this.selectedStrategy && this.selectedStrategy.name === name;
        },

        getStrategyLabel({ name }) {
            return this.$tc(`sw-app.component.sw-app-shop-id-change-modal.${name}.name`);
        },

        getStrategyDescription({ name }) {
            return this.$tc(`sw-app.component.sw-app-shop-id-change-modal.${name}.description`);
        },

        getActiveStyle({ name }) {
            return {
                'sw-app-shop-id-change-modal__button-strategy--active': name === this.selectedStrategy.name,
            };
        },

        async confirm() {
            try {
                await this.shopIdChangeService.changeShopId(this.selectedStrategy);

                this.createNotificationSuccess({
                    message: this.$tc('sw-app.component.sw-app-shop-id-change-modal.success'),
                });

                // shop url could have changed
                window.location.reload();
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-app.component.sw-app-shop-id-change-modal.error'),
                });
            }
        },
    },
});
