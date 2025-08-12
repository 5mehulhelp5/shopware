<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ShopIdController extends AbstractController
{
    public function __construct(
        private readonly Resolver $shopIdChangeResolver,
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    #[Route(path: 'api/app-system/shop-id/change-strategies', name: 'api.app_system.shop_id.change_strategies', methods: ['GET'])]
    public function getAvailableStrategies(): JsonResponse
    {
        return new JsonResponse(
            $this->shopIdChangeResolver->getAvailableStrategies()
        );
    }

    #[Route(path: 'api/app-system/shop-id/change', name: 'api.app_system.shop_id.change', methods: ['POST'])]
    public function resolve(Request $request, Context $context): Response
    {
        $strategy = $request->get('strategy');

        if (!$strategy) {
            throw AppException::missingRequestParameter('strategy');
        }

        try {
            $this->shopIdChangeResolver->resolve($strategy, $context);
        } catch (ShopIdChangeStrategyNotFoundException $e) {
            throw AppException::shopIdChangeResolveStrategyNotFound($strategy);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: 'api/app-system/shop-id/fingerprints', name: 'api.app_system.shop_id.fingerprints', methods: ['GET'])]
    public function getFingerprints(): Response
    {
        try {
            $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException $e) {
            return new JsonResponse($e->comparisonResult);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
