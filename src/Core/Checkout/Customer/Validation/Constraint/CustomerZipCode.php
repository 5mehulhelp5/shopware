<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class CustomerZipCode extends Constraint
{
    final public const ZIP_CODE_INVALID = 'ZIP_CODE_INVALID';

    protected const ERROR_NAMES = [
        NotBlank::IS_BLANK_ERROR => 'IS_BLANK_ERROR',
        self::ZIP_CODE_INVALID => 'ZIP_CODE_INVALID',
    ];

    public ?string $countryId = null;

    public bool $caseSensitiveCheck = true;

    private string $message = 'This value is not a valid ZIP code for country {{ iso }}';

    private string $messageRequired = 'Postal code is required for that country';

    /**
     * @param ?array{countryId?: ?string, caseSensitiveCheck?: bool} $options
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $options parameter will be removed, use $countryId and $caseSensitiveCheck instead
     */
    public function __construct(?array $options = null, ?string $countryId = null, ?bool $caseSensitiveCheck = null)
    {
        if ($countryId === null && $caseSensitiveCheck === null) {
            if (Feature::isActive('v6.8.0.0')) {
                if ($options === null) {
                    Feature::triggerDeprecationOrThrow('v6.8.0.0', 'The parameter $options will be required and natively typed as array');
                }
            } else {
                if ($options !== null && !\is_array($options)) {
                    $options = [
                        'countryId' => $options,
                    ];
                }
            }

            $options ??= [];

            if (\array_key_exists('countryId', $options) && ($options['countryId'] !== null && !\is_string($options['countryId']))) {
                throw CustomerException::missingOption('countryId', self::class);
            }

            if (isset($options['caseSensitiveCheck']) && !\is_bool($options['caseSensitiveCheck'])) {
                throw CustomerException::invalidOption('caseSensitiveCheck', 'bool', self::class);
            }
        }

        parent::__construct($options ?? []);
        
        if (Feature::isActive('v6.8.0.0')) {
            if ($countryId !== null || $caseSensitiveCheck !== null) {
                $this->countryId = $countryId;
                $this->caseSensitiveCheck = $caseSensitiveCheck ?? true;
            }
        }
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageRequired(): string
    {
        return $this->messageRequired;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function isCaseSensitiveCheck(): bool
    {
        return $this->caseSensitiveCheck;
    }
}
