<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class HookableEventCollector
{
    private const PRIVILEGES = 'privileges';

    /**
     * @var string[][][]
     */
    private array $hookableEventNamesWithPrivileges = [];

    /**
     * @var array<string>|null
     */
    private ?array $hookableEntities = null;

    /**
     * @param iterable<EntityDefinition> $hookableEntityDefinitions
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly iterable $hookableEntityDefinitions
    ) {
    }

    public function getHookableEventNamesWithPrivileges(Context $context): array
    {
        if (!$this->hookableEventNamesWithPrivileges) {
            $this->hookableEventNamesWithPrivileges = $this->getEventNamesWithPrivileges($context);
        }

        return $this->hookableEventNamesWithPrivileges;
    }

    /**
     * @return list<string>
     */
    public function getPrivilegesFromBusinessEventDefinition(BusinessEventDefinition $businessEventDefinition): array
    {
        $privileges = [];
        foreach ($businessEventDefinition->getData() as $data) {
            if ($data['type'] !== 'entity') {
                continue;
            }

            $entityName = $this->definitionRegistry->get($data['entityClass'])->getEntityName();
            $privileges[] = $entityName . ':' . AclRoleDefinition::PRIVILEGE_READ;
        }

        return $privileges;
    }

    /**
     * @return array<string, array{privileges: list<string>}>
     */
    public function getEntityWrittenEventNamesWithPrivileges(): array
    {
        $entityWrittenEventNames = [];
        foreach ($this->getHookableEntities() as $entity) {
            $privileges = [
                self::PRIVILEGES => [$entity . ':' . AclRoleDefinition::PRIVILEGE_READ],
            ];

            $entityWrittenEventNames[$entity . '.written'] = $privileges;
            $entityWrittenEventNames[$entity . '.deleted'] = $privileges;
        }

        return $entityWrittenEventNames;
    }

    /**
     * Dynamically discovers all hookable entities by checking for services tagged with 'shopware.entity.hookable'.
     *
     * @return array<string>
     */
    public function getHookableEntities(): array
    {
        if ($this->hookableEntities !== null) {
            return $this->hookableEntities;
        }

        $hookableEntities = [];

        foreach ($this->hookableEntityDefinitions as $definition) {
            if ($definition instanceof EntityDefinition) {
                $hookableEntities[] = $definition->getEntityName();
            }
        }

        $this->hookableEntities = array_unique($hookableEntities);

        return $this->hookableEntities;
    }

    private function getEventNamesWithPrivileges(Context $context): array
    {
        return array_merge(
            $this->getEntityWrittenEventNamesWithPrivileges(),
            $this->getBusinessEventNamesWithPrivileges($context),
            $this->getHookableEventNames()
        );
    }

    private function getHookableEventNames(): array
    {
        return array_reduce(array_values(
            array_map(static fn ($hookableEvent) => [$hookableEvent => [self::PRIVILEGES => []]], Hookable::HOOKABLE_EVENTS)
        ), 'array_merge', []);
    }

    private function getBusinessEventNamesWithPrivileges(Context $context): array
    {
        $response = $this->businessEventCollector->collect($context);

        return array_map(function (BusinessEventDefinition $businessEventDefinition) {
            $privileges = $this->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

            return [
                self::PRIVILEGES => $privileges,
            ];
        }, $response->getElements());
    }
}
