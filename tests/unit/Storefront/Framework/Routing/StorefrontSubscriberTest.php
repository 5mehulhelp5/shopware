<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(StorefrontSubscriber::class)]
class StorefrontSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['customerNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            CustomerLoginEvent::class => [
                'updateSessionAfterLogin',
            ],
            CustomerLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            StorefrontRenderEvent::class => [
                ['addHreflang'],
                ['addShopIdParameter'],
                ['addIconSetConfig'],
            ],
            SalesChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];

        static::assertSame($expected, StorefrontSubscriber::getSubscribedEvents());
    }

    public function testAddIconSetConfigWithRegistryWithGetByTechnicalName(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'Storefront');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            $request,
            Generator::createSalesChannelContext()
        );

        $themeConfig = new StorefrontPluginConfiguration('Storefront');
        $themeConfig->setIconSets(['default' => '@Storefront/icons/default']);

        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);

        $themeRegistry
            ->expects(static::never())
            ->method('getConfigurations');

        $themeRegistry
            ->expects(static::once())
            ->method('getByTechnicalName')
            ->willReturn($themeConfig);

        $subscriber = new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(HreflangLoaderInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $this->createMock(ShopIdProvider::class),
            $this->createMock(ActiveAppsLoader::class),
            $this->createMock(SystemConfigService::class),
            $themeRegistry,
        );

        $subscriber->addIconSetConfig($event);

        static::assertArrayHasKey('themeIconConfig', $event->getParameters());
    }
}
