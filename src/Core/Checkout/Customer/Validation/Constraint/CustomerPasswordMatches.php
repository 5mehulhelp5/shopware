<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\HasNamedArguments;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraint;

#[Package('checkout')]
class CustomerPasswordMatches extends Constraint
{
    final public const CUSTOMER_PASSWORD_NOT_CORRECT = 'fe2faa88-34d9-4c3b-99b3-8158b1ed8dc7';

    protected const ERROR_NAMES = [
        self::CUSTOMER_PASSWORD_NOT_CORRECT => 'CUSTOMER_PASSWORD_NOT_CORRECT',
    ];

    public string $message = 'Your password is wrong';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use $salesChannelContext instead
     */
    protected SalesChannelContext $context;

    protected SalesChannelContext $salesChannelContext;

    /**
     * @param ?array{salesChannelContext: SalesChannelContext} $options
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $options parameter will be removed, use $salesChannelContext instead
     *
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?SalesChannelContext $salesChannelContext = null)
    {
        if ($salesChannelContext === null) {
            if ($options === null) {
                Feature::triggerDeprecationOrThrow('v6.8.0.0', 'The parameter $options will be required and natively typed as array');
            }

            $options ??= [];

            if (!Feature::isActive('v6.8.0.0') && isset($options['context'])) {
                $options['salesChannelContext'] = $options['context'];
            }

            if (!($options['salesChannelContext'] ?? null) instanceof SalesChannelContext) {
                throw CustomerException::missingOption('salesChannelContext', self::class);
            }

            if (!Feature::isActive('v6.8.0.0')) {
                $options['context'] = $options['salesChannelContext'];
            }
        }

        parent::__construct($options ?? []);
        
        if (Feature::isActive('v6.8.0.0')) {
            if ($salesChannelContext === null) {
                throw CustomerException::missingOption('salesChannelContext', self::class);
            }
            
            $this->salesChannelContext = $salesChannelContext;
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getSalesChannelContext instead
     */
    public function getContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getSalesChannelContext')
        );

        return $this->context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
