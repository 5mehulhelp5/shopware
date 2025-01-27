<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Extension\PdfRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
class ZugferdPdfRenderer extends AbstractDocumentTypeRenderer
{
    public const FILE_EXTENSION = 'pdf';

    public const FILE_CONTENT_TYPE = 'application/pdf';

    /**
     * @internal
     *
     * @param array<string, mixed> $dompdfOptions
     */
    public function __construct(
        private readonly array $dompdfOptions,
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly string $rootDir,
        private readonly ExtensionDispatcher $extensions,
        private readonly AbstractDocumentTypeRenderer $zugferdRenderer
    ) {
    }

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        return $this->extensions->publish(
            name: PdfRendererExtension::NAME,
            extension: new PdfRendererExtension($document),
            function: $this->_render(...)
        );
    }

    public function getDecorated(): AbstractDocumentTypeRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    private function _render(RenderedDocument $document): string
    {
        $pdfRenderer = new PdfRenderer(
            $this->dompdfOptions,
            $this->documentTemplateRenderer,
            $this->rootDir,
            $this->extensions
        );

        $pdfDocument = $pdfRenderer->render($document);

        return $this->embedXMLIntoPDF($operations, $context, $rendererConfig, $pdfDocument);
    }

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    protected function embedXMLIntoPDF(array $operations, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $invoice): RendererResult
    {
        // So ElectronicRenderer don't need to create a new number
        $this->setSuccessDocumentNumbers($invoice->getSuccess(), $operations);
        $electronicInvoice = $this->zugferdRenderer->render($operations, $context, $rendererConfig);
        $renderResult = new RendererResult();

        foreach ($invoice->getSuccess() as $orderId => $invoiceDocument) {
            $electronicDoc = $electronicInvoice->getOrderSuccess($orderId);
            if ($electronicDoc === null) {
                $renderResult->addError($orderId, DocumentException::electronicInvoiceViolation(1, ['Electronic invoice is null' => [$orderId]]));

                continue;
            }

            try {
                $combined = (new ZugferdDocumentPdfMerger($electronicDoc->getContent(), $invoiceDocument->getContent()))
                    ->setAdditionalCreatorTool('Shopware@' . $this->shopwareVersion)
                    ->generateDocument()
                    ->downloadString();

                $invoiceDocument->setName('embedded_' . $invoiceDocument->getName());
                $invoiceDocument->setContent($combined);

                $renderResult->addSuccess($orderId, $invoiceDocument);
            } catch (\Throwable $e) {
                $renderResult->addError($orderId, $e);
            }
        }

        $renderResult->assign(['errors' => \array_merge($invoice->getErrors(), $electronicInvoice->getErrors(), $renderResult->getErrors())]);

        return $renderResult;
    }

    /**
     * @param array<string, RenderedDocument> $successes
     * @param DocumentGenerateOperation[] $operations
     */
    protected function setSuccessDocumentNumbers(array $successes, array $operations): void
    {
        foreach ($successes as $orderId => $document) {
            $operation = $operations[$orderId] ?? null;
            if (!$operation) {
                continue;
            }

            $config = $operation->getConfig();
            $config['documentNumber'] = $document->getNumber();
            $operation->assign(['config' => $config]);
        }
    }
}
