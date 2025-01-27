<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Extension\PdfRendererExtension;
use Shopware\Core\Checkout\Document\Extension\XmlRendererExtension;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
final class ZugferdRenderer extends AbstractDocumentTypeRenderer
{
    public const FILE_EXTENSION = 'xml';

    public const FILE_CONTENT_TYPE = 'application/xml';

    /**
     * @internal
     */
    public function __construct(
        protected ZugferdBuilder $documentBuilder,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        dd('here');
        return $this->extensions->publish(
            name: XmlRendererExtension::NAME,
            extension: new XmlRendererExtension($document),
            function: $this->documentBuilder->buildDocument($document)
        );
    }

    public function getDecorated(): AbstractDocumentTypeRenderer
    {
        throw new DecorationPatternException(self::class);
    }

//    protected function createDocument(RendererResult $renderResult, OrderEntity $order, DocumentGenerateOperation $operation, Context $context): void
//    {
//        $forceDocumentCreation = $operation->getConfig()['forceDocumentCreation'] ?? true;
//        if (!$forceDocumentCreation && $order->getDocuments()?->first()) {
//            return;
//        }
//
//        $config = clone $this->documentConfigLoader->load(InvoiceRenderer::TYPE, $order->getSalesChannelId(), $context);
//        $config->merge($operation->getConfig());
//
//        $documentNumber = $config->getDocumentNumber();
//        if ($documentNumber === null) {
//            $config->setDocumentNumber($documentNumber = $this->getNumber($context, $order, $operation));
//        }
//
//        try {
//            $content = $this->documentBuilder->buildDocument($order, $operation, $config, $context);
//            $renderResult->addSuccess(
//                $order->getId(),
//                new RenderedDocument(
//                    '', // @deprecated tag:v6.7.0 - will be removed
//                    $documentNumber,
//                    $config->buildName(),
//                    FileTypes::XML,
//                    $config->jsonSerialize(),
//                    'application/xml'
//                )
//            );
//        } catch (DocumentException $e) {
//            $renderResult->addError($order->getId(), $e);
//        }
//    }
//
//    private function getNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
//    {
//        return $this->numberRangeValueGenerator->getValue(
//            'document_' . InvoiceRenderer::TYPE,
//            $context,
//            $order->getSalesChannelId(),
//            $operation->isPreview()
//        );
//    }
}
