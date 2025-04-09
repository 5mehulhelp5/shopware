import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';

const { Component, Mixin } = Shopware;

const ITEMS = [
    {
        id: 'dashboard',
        name: 'Dashboard',
        icon: 'inbox',
        position: 0,
        to: 'sw.dashboard.index',
    },
    {
        id: 'catalogues',
        name: 'Catalogues',
        icon: 'tag',
        position: 1,
        children: [
            {
                id: 'products',
                name: 'Products',
                to: 'sw.product.index',
                position: 0
            },
            {
                id: 'reviews',
                name: 'Reviews',
                to: 'sw.review.index',
                position: 0
            },
            {
                id: 'categories',
                name: 'Categories',
                to: 'sw.category.index',
                position: 0
            },
            {
                id: 'dynamic-product-groups',
                name: 'Dynamic Product Groups',
                to: 'sw.product.stream.index',
                position: 0
            },
            {
                id: 'properties',
                name: 'Properties',
                to: 'sw.property.index',
                position: 0
            },
            {
                id: 'manufacturers',
                name: 'Manufacturers',
                to: 'sw.manufacturer.index',
                position: 0
            },
        ]
    },
    {
        id: 'orders',
        name: 'Orders',
        icon: 'shopping-bag',
        to: 'sw.order.index',
        position: 2,
    },
    {
        id: 'customers',
        name: 'Customers',
        icon: 'users',
        to: 'sw.customer.index',
        position: 2,
    },
    {
        id: 'content',
        name: 'Content',
        icon: 'image-text',
        position: 2,
        children: [
            {
                id: 'cms',
                name: 'Shopping Experiences',
                to: 'sw.cms.index',
                position: 0
            },
            {
                id: 'media',
                name: 'Media',
                to: 'sw.media.index',
                position: 1
            }
        ]
    },
    {
        id: 'marketing',
        name: 'Marketing',
        icon: 'megaphone',
        position: 2,
        children: [
            {
                id: 'newsletter-recipients',
                name: 'Newsletter Recipients',
                to: 'sw.newsletter.recipient.index',
                position: 0
            },
            {
                id: 'promotions',
                name: 'Promotions',
                to: 'sw.promotion.v2.index',
                position: 1
            }
        ]
    },
    {
        id: 'extensions',
        name: 'Extensions',
        icon: 'puzzle-piece',
        position: 2,
        children: [
            {
                id: 'my-extensions',
                name: 'My extensions',
                to: 'sw.cms.index',
                position: 0
            },
            {
                id: 'store',
                name: 'Store',
                to: 'sw.settings.store.index',
                position: 1
            }
        ]
    },
    {
        id: 'settings',
        name: 'Settings',
        icon: 'cog',
        position: 2,
        to: 'sw.settings.index'
    },
];

/**
 * @sw-package framework
 *
 * @private
 */
Component.register('sw-admin-menu', {
    template,

    data() {
        return {
            ITEMS,
            idOfExpandedItem: null
        }
    },

    computed: {
        currentUser() {
            return Shopware.Store.get('session').currentUser;
        },

        adminMenuStore() {
            return Shopware.Store.get('adminMenu');
        },
    },

    methods: {
        expandItem(id) {
            this.idOfExpandedItem = id;
        }
    },
});
