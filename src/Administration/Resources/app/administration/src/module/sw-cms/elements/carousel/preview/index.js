// import CMS from '../../../constant/sw-cms.constant';
import template from './sw-cms-el-preview-carousel.html.twig';
// import './sw-cms-el-image-gallery.scss';

const { Mixin, Filter } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {};
    },

    computed: {

    },

    created() {
    },

    mounted() {
    },

    methods: {

    },
};
