<?php

declare(strict_types=1);

namespace Ineersa\Html2text;

use Dom\HTMLDocument;
use Dom\HTMLElement;
use Dom\Node;
use Ineersa\Html2text\Processors\AnchorProcessor;
use Ineersa\Html2text\Processors\ListProcessor;
use Ineersa\Html2text\Processors\TagProcessor;
use Ineersa\Html2text\Processors\TextProcessor;
use Ineersa\Html2text\Processors\TrProcessor;
use Ineersa\Html2text\Processors\WrapProcessor;

class HTML2Markdown
{
    private const PLACEHOLDER_PREFIX = '__PH2T__';
    private const PLACEHOLDER_SUFFIX = '__';

    protected DataContainer $data;
    protected TextProcessor $textProcessor;
    private HTMLDocument $document;
    private ListProcessor $listsProcessor;
    private TagProcessor $tagProcessor;
    private WrapProcessor $wrapProcessor;

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function __invoke(string $html): string
    {
        return $this->convert($html);
    }

    public function convert(string $html): string
    {
        $html = trim($html);
        if ('' === $html) {
            return '';
        }

        $this->initProcessors($html);
        $processedHtml = $this->preprocessEntities($html);
        $this->document = $this->loadDocument($processedHtml);

        if (null !== $this->document->documentElement) {
            $this->traverseDom($this->document->documentElement);
        }

        $result = $this->finish();

        return $this->wrapProcessor->process($result);
    }

    protected function finish(): string
    {
        $this->data->pbr();
        $this->data->appendFormattedData('', false, 'end');

        $outputText = implode('', $this->data->outtextlist);

        if ($this->config->unicodeSnob) {
            $nbsp = html_entity_decode('&nbsp;', \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        } else {
            $nbsp = ' ';
        }

        return str_replace('&nbsp_place_holder;', $nbsp, $outputText);
    }

    protected function initProcessors(string $html): void
    {
        $this->data = new DataContainer($this->config);
        $this->textProcessor = new TextProcessor($this->config, $this->data);
        $trProcessor = new TrProcessor($html);
        $anchorProcessor = AnchorProcessor::fromHtml($html);
        $this->listsProcessor = new ListProcessor($html, $this->config);
        $this->tagProcessor = new TagProcessor(
            $this->config,
            $this->data,
            $trProcessor,
            $anchorProcessor,
            $this->listsProcessor,
        );
        $this->wrapProcessor = new WrapProcessor($this->config);
    }

    private function traverseDom(Node $node): void
    {
        switch ($node->nodeType) {
            case \XML_TEXT_NODE:
            case \XML_CDATA_SECTION_NODE:
                if ('' !== $node->nodeValue) {
                    $value = $node->nodeValue;
                    $this->textProcessor->process($value);
                    $this->tagProcessor->afterText($value);
                }

                return;

            case \XML_ELEMENT_NODE:
                $tagName = strtolower($node->nodeName);
                $attrs = [];
                if ($node instanceof HTMLElement && $node->hasAttributes()) {
                    foreach ($node->attributes as $attribute) {
                        $attrs[strtolower($attribute->name)] = $this->textProcessor->decodeAttributePlaceholders($attribute->value);
                    }
                }

                $this->tagProcessor->process($tagName, $attrs, true);
                if ($node->hasChildNodes()) {
                    foreach ($node->childNodes as $child) {
                        $this->traverseDom($child);
                    }
                }
                $this->tagProcessor->process($tagName, [], false);

                return;

            case \XML_COMMENT_NODE:
            case \XML_PI_NODE:
            case \XML_DOCUMENT_TYPE_NODE:
                return;
        }
    }

    private function loadDocument(string $html): HTMLDocument
    {
        $document = HTMLDocument::createFromString(
            $html,
            \LIBXML_NOERROR,
            'UTF-8'
        );
        libxml_clear_errors();

        return $document;
    }

    /**
     * Replace character/entity references with placeholders so the text processor
     * can restore them later without relying on DOM normalization side-effects.
     */
    private function preprocessEntities(string $html): string
    {
        return (string) preg_replace_callback(
            '/&(#x[0-9A-Fa-f]+|#X[0-9A-Fa-f]+|#[0-9]+|[A-Za-z][A-Za-z0-9]+);/',
            static function (array $match): string {
                $entity = $match[1];

                if ('#' === $entity[0]) {
                    $code = substr($entity, 1);

                    return self::PLACEHOLDER_PREFIX.'CHAR_'.strtolower($code).self::PLACEHOLDER_SUFFIX;
                }

                return self::PLACEHOLDER_PREFIX.'ENT_'.strtolower($entity).self::PLACEHOLDER_SUFFIX;
            },
            $html
        );
    }
}
