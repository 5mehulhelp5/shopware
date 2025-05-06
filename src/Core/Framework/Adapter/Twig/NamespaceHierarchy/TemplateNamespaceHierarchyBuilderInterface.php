<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * Gets the current hierarchy as param and can extend and modify the hierarchy.
     * Needs to return the new hierarchy.
     * Example hierarchy structure:
     * [
     *     'Storefront' => -1, // priority of the plugin(namespace)
     *     'SwagPayPal' => 0,
     *     'MyOwnTheme' => 1,
     * ]
     *
     * @param array<string, int> $namespaceHierarchy
     *
     * @return array<string, int>
     */
    public function buildNamespaceHierarchy(array $namespaceHierarchy): array;
}
