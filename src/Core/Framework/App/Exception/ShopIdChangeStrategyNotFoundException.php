<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ShopIdChangeStrategyNotFoundException extends AppException
{
    public function __construct(string $strategyName)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            AppException::SHOP_ID_CHANGE_STRATEGY_NOT_FOUND,
            'Unable to find resolver with name "{{ strategyName }}".',
            ['strategyName' => $strategyName]
        );
    }
}
