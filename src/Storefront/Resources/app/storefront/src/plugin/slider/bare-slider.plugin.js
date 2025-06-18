/**
 * Bare slider CMS element JS-plugin
 * ===========================================
 * @experimental
 * @private
 *
 * A new bare metal slider component that uses the CSS scroll-snap feature, less JavaScript and no tiny-slider library.
 */
export default class BareSliderPlugin extends window.PluginBaseClass {

    static options = {
        componentName: 'cms-element-bare-slider',
    };

    /**
     * Plugin constructor and collecting of needed elements.
     * @returns void
     */
    init() {
        this._slideIndex = 1;

        this._dots = this.el.querySelectorAll(`.${this.options.componentName}-navigation-item`);
        this._dotsWrapper = this.el.querySelector(`.${this.options.componentName}-navigation`);

        this._thumbnailsContainer = this.el.querySelector(`.${this.options.componentName}-thumbnails`);
        this._thumbnails = this.el.querySelectorAll(`.${this.options.componentName}-thumbnail-item`);

        this._sliderItems = this.el.querySelectorAll(`.${this.options.componentName}-item`);
        this._sliderItemsScrollContainer = this.el.querySelector(`.${this.options.componentName}-inner`);

        this._prevButton = this.el.querySelector(`.${this.options.componentName}-arrow.prev`);
        this._nextButton = this.el.querySelector(`.${this.options.componentName}-arrow.next`);

        this._totalItemCount = Array.from(this._sliderItems).length;

        this._registerEvents();
    }

    /**
     * @private
     * @returns void
     */
    _registerEvents() {
        this._dots.forEach(dot => dot.addEventListener('click', this._slideTo.bind(this)));
        this._thumbnails.forEach(thumb => thumb.addEventListener('click', this._slideTo.bind(this)));
        this._nextButton.addEventListener('click', this._slideNext.bind(this));
        this._prevButton.addEventListener('click', this._slidePrev.bind(this));
        this._sliderItemsScrollContainer.addEventListener('scrollsnapchange', this._observeScrollSnap.bind(this));
    }

    /**
     *
     * @private
     */
    _slideNext() {
        if (this._slideIndex >= this._totalItemCount) {
            return;
        }

        this._doSlide(this._slideIndex + 1);
    }

    _slidePrev() {
        if (this._slideIndex <= 1) {
            return;
        }

        this._doSlide(this._slideIndex - 1);
    }

    /**
     * Identify the target carousel item using the data-slide-to="{index}" attribute of the clicked element.
     *
     * @private
     * @param event
     * @returns void
     */
    _slideTo(event) {
        event.preventDefault();

        this._slideIndex = Number(event.currentTarget.dataset.slideTo);

        this._doSlide(this._slideIndex);
    }

    /**
     * Query the target carousel DOM element via the index and id="carousel-item-{index}".
     * Slide to the target carousel item using native scrolling.
     *
     * @private
     * @param {Number} index
     * @param {String} behavior
     * @returns void
     */
    _doSlide(index, behavior = 'smooth') {
        const targetEl = this.el.querySelector(`#carousel-item-${index}`);

        if (!targetEl) {
            return;
        }

        targetEl.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
            behavior: behavior,
        });

        this._updateActiveDot(index);
        this._updateActiveThumbnail(index);
    }

    /**
     * Update the navigation dots active state by the current slideIndex.
     *
     * @private
     * @param {Number} index
     * @returns void
     */
    _updateActiveDot(index) {
        this._dots.forEach(dot => dot.classList.remove('active'));

        const activeDot = this._dotsWrapper.querySelector(`[data-slide-to="${index}"]`);
        activeDot.classList.add('active');
    }

    /**
     * Update the thumbnail preview active state by the current slideIndex.
     *
     * @private
     * @param {Number} index
     * @returns void
     */
    _updateActiveThumbnail(index) {
        this._thumbnails.forEach(thumb => thumb.classList.remove('active'));

        const activeThumb = this._thumbnailsContainer.querySelector(`[data-slide-to="${index}"]`);
        activeThumb.classList.add('active');

        activeThumb.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
            behavior: 'smooth',
        });
    }

    /**
     * Observe if the scrolling container has scrolled to another element.
     *
     * @param event
     * @private
     */
    _observeScrollSnap(event) {
        const targetIndex = Number(event.snapTargetInline?.dataset.index);

        if (!targetIndex) {
            return;
        }

        // Update the current slide index after the scroll-snap
        this._slideIndex = targetIndex;

        // Update the navigation active states
        this._updateActiveDot(this._slideIndex);
        this._updateActiveThumbnail(this._slideIndex);
    }
}