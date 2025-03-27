export default class CarouselPlugin extends window.PluginBaseClass {
    init() {
        console.log('CarouselPlugin initialized');

        this._slideIndex = 1;

        this._dots = document.querySelectorAll('.cms-block-image-carousel-navigation-item');
        this._dotsWrapper = document.querySelector('.cms-block-image-carousel-navigation');
        this._thumbnails = document.querySelectorAll('.cms-block-image-carousel-thumbnail-item');

        this._registerEvents();
    }

    _registerEvents() {
        this._dots.forEach((dot) => {
            dot.addEventListener('click', this._slideTo.bind(this));
        });

        this._thumbnails.forEach((thumb) => {
            thumb.addEventListener('click', this._slideTo.bind(this));
        });
    }

    _slideTo(event) {
        event.preventDefault();
        const targetIndex = Number(event.currentTarget.dataset.slideTo);
        const targetEl = document.querySelector(`#carousel-item-${targetIndex}`);

        this._slideIndex = targetIndex;

        if (!targetEl) {
            return;
        }

        targetEl.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        this._updateActiveDot();
    }

    _updateActiveDot() {
        this._dots.forEach((dot) => {
            dot.classList.remove('active');
        });

        const activeDot = this._dotsWrapper.querySelector(`[data-slide-to="${this._slideIndex}"]`);
        activeDot.classList.add('active');
    }
}