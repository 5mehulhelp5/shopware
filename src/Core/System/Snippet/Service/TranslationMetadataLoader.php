<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationMetadataLoader
{
    private const CROWDIN_METADATA_FILENAME = 'crowdin-metadata.json';

    public function __construct(
        private readonly TranslationConfig $config,
        private readonly ClientInterface $client,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function load(): void
    {
        $path = Path::join(TranslationLoader::TRANSLATION_DIR, self::CROWDIN_METADATA_FILENAME);

        if ($this->filesystem->fileExists($path)) {
            throw SnippetException::metadataFileAlreadyExists($path);
        }

        $this->downloadFile($this->config->metadataUrl, $path);
    }

    private function downloadFile(Uri $url, string $destination): void
    {
        try {
            $response = $this->client->request('GET', $url);

            $this->filesystem->write($destination, $response->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw SnippetException::translationMetadataWriteFailed($url, $e);
        }
    }
}
