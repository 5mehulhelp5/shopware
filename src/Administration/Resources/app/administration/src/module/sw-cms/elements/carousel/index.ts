Shopware.Component.register('sw-cms-el-preview-carousel', () => import('./preview'));

Shopware.Component.register('sw-cms-el-config-carousel', () => import('./config'));

Shopware.Component.register('sw-cms-el-carousel', () => import('./component'));

type CarouselItemConfig = {
    newTab: boolean;
    url: string;
    mediaId: string;
};

type CarouselItem = {
    newTab: boolean;
    url: string;
    media: Entity<'media'> | null;
};

Shopware.Service('cmsService').registerCmsElement({
    name: 'carousel',
    label: '(NEW) Carousel',
    component: 'sw-cms-el-carousel',
    configComponent: 'sw-cms-el-config-carousel',
    previewComponent: 'sw-cms-el-preview-carousel',

    defaultConfig: {
        sliderItems: {
            source: 'static',
            value: [],
            type: Array,
            required: true,
            entity: {
                name: 'media',
            },
        },
        navigationArrows: {
            source: 'static',
            value: 'inside',
        },
        navigationDots: {
            source: 'static',
            value: 'none',
        },
        galleryPosition: {
            source: 'static',
            value: 'left',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        minHeight: {
            source: 'static',
            value: '340px',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
        zoom: {
            source: 'static',
            value: false,
        },
        fullScreen: {
            source: 'static',
            value: false,
        },
        keepAspectRatioOnZoom: {
            source: 'static',
            value: true,
        },
        magnifierOverGallery: {
            source: 'static',
            value: false,
        },
    },
    enrich: function enrich(slot, data) {
        if (Object.keys(data).length < 1) {
            return;
        }

        let entityCount = 0;
        Object.keys(slot.config).forEach((configKey) => {
            const entity = slot.config[configKey].entity;

            if (!entity) {
                return;
            }

            const entityKey = `entity-${entity.name}-${entityCount}`;

            if (!data[entityKey]) {
                return;
            }

            entityCount += 1;

            Object.assign(slot.data, {
                [configKey]: [] as CarouselItem[],
            });

            const items = slot.data[configKey] as unknown as CarouselItem[];
            const config = slot.config[configKey];

            if (!Array.isArray(config.value)) {
                return;
            }

            config.value.forEach((sliderItem: CarouselItemConfig) => {
                const item: CarouselItem = {
                    newTab: sliderItem.newTab,
                    url: sliderItem.url,
                    media: data[entityKey].get(sliderItem.mediaId) as Entity<'media'> | null,
                };

                items.push(item);
            });
        });
    },
});
