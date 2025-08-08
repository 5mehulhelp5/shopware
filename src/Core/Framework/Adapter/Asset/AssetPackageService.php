<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Filesystem\Path;

#[Package('framework')]
class AssetPackageService
{
    /**
     * @param array<string, string> $bundleMap
     */
    public static function create(
        array $bundleMap,
        AssetPackage $package,
        VersionStrategyInterface $versionStrategy,
        FilesystemOperator $assetFilesystem,
        mixed ...$args
    ): Packages {
        $packages = new Packages(...$args);

        if (!EnvironmentHelper::hasVariable('APP_URL')) {
            return $packages;
        }

        $currentBundlesPath = trim($assetFilesystem->read('/bundles/path'));
        foreach ($bundleMap as $bundleName => $bundlePath) {
            /** @see AssetService::getTargetDirectory() */
            $targetPath = Path::join('/bundles', $currentBundlesPath, preg_replace('/bundle$/', '', mb_strtolower($bundleName)));

            $path = $package->getUrl($targetPath);
            dump($targetPath, $path);

            try {
                $bundlePackage = new UrlPackage($path, new PrefixVersionStrategy($targetPath, $versionStrategy));
            } catch (InvalidArgumentException $exception) {
                throw AdapterException::invalidAssetUrl($exception);
            }

            $packages->addPackage('@' . $bundleName, $bundlePackage);
        }

//        dd($packages);

        return $packages;
    }
}
