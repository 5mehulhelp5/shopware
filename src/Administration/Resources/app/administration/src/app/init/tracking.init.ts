/**
 * @sw-package framework
 * @private
 */
export default function initializeTracking(): void {
    Shopware.ExtensionAPI.handle('trackingPage', (pageEvent) => {
        Shopware.Tracking.page(pageEvent);

        return null;
    });

    Shopware.ExtensionAPI.handle('trackingEvent', (trackingEvent) => {
        Shopware.Tracking.track(trackingEvent);

        return null;
    });

    window.addEventListener('message', (messageEvent: MessageEvent<unknown>) => {
        if (typeof messageEvent.data === 'string' && messageEvent.data === 'trackingRegisterService') {
            Shopware.Tracking.registerTransport(messageEvent.ports[0], messageEvent.origin);
        }
    })
}