/**
 * @sw-package framework
 */
import { tracking } from '@shopware-ag/meteor-admin-sdk';
import type {
    TrackEventParameters,
    TrackPageParameters,
    TrackingContext,
    PageEvent,
    TrackEvent,
    IdentifyEvent,
} from '@shopware-ag/meteor-admin-sdk/es/tracking';

type TrackingEvent = TrackEvent | PageEvent | IdentifyEvent;

/**
 * @private
 */
export default class Tracking {
    private transports: Map<string,MessagePort>;

    public constructor() {
        this.transports = new Map<string, MessagePort>();
    }

    public registerTransport(transport: MessagePort, name: string): void {
        this.transports.set(name, transport);

        this.identify(transport);
    }

    public page({ url, name, pageData, search}: TrackPageParameters): void {
        this.broadcastEvent({
            type: tracking.EventNamePage,
            url,
            name,
            pageData,
            search,
            context: this.context,
            userId: this.userId,
            timestamp: Date.now(),
        });
    }

    public track({ name, eventData }: TrackEventParameters): void {
        const eventName = typeof name === 'string' ? name : `${name.application}:${name.domain}:${name.action}`;

        this.broadcastEvent({
            type: tracking.EventNameTrack,
            name: eventName,
            eventData,
            context: this.context,
            userId: this.userId,
            timestamp: Date.now(),
        });
    }

    private identify(transport: MessagePort): void {
        this.sendEvent(transport, {
            type: tracking.EventNameIdentify,
            context: this.context,
            userId: this.userId,
            timestamp: Date.now(),
        })
    }

    private broadcastEvent(event: TrackingEvent): void {
        this.transports.forEach((transport) => {
            try { this.sendEvent(transport, event) } catch { /* ignore failed messages */ }
        })
    }

    private sendEvent(transport: MessagePort, event: TrackingEvent): void {
        transport.postMessage(event);
    }

    private get context(): TrackingContext {
        return {
            screen: {
                width: globalThis.screen?.width,
                height: globalThis.screen?.height,
            },
            userAgent: globalThis.navigator?.userAgent,
            shopwareVersion: Shopware.Context.app.config.version ?? '',
            shopId: Shopware.Context.app.config.shopId ?? '',
        }
    }

    private get userId(): string {
        return Shopware.Store.get('session').currentUser?.id ?? '';
    }
}