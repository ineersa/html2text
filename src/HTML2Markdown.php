<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

use Dom\HTMLDocument;
use Dom\HTMLElement;

class HTML2Markdown
{
    protected DataContainer $data;
    protected TextProcessor $textProcessor;
    private HTMLDocument $document;
    private ListsStructure $listsStructure;
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

        $this->initProcessors();
        $this->document = $this->loadDocument($html);
        $this->listsStructure = new ListsStructure($html, $this->config->googleDoc);

        if (null !== $this->document->documentElement) {
            $this->traverseDom($this->document->documentElement);
        } else {
            foreach ($this->document->childNodes as $child) {
                $this->traverseDom($child);
            }
        }

        $result = $this->finish();

        return $this->wrapProcessor->process($result);
    }

    public function getDocument(): HTMLDocument
    {
        return $this->document;
    }

    public function getListsStructure(): ListsStructure
    {
        return $this->listsStructure;
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

    protected function initProcessors(): void
    {
        $this->data = new DataContainer($this->config);
        $this->textProcessor = new TextProcessor($this->config, $this->data);
        $this->tagProcessor = new TagProcessor($this->config, $this->data, $this);
        $this->wrapProcessor = new WrapProcessor($this->config);
    }

    private function traverseDom(\Dom\Node $node): void
    {
        switch ($node->nodeType) {
            case \XML_TEXT_NODE:
            case \XML_CDATA_SECTION_NODE:
                if ('' !== $node->nodeValue) {
                    $this->textProcessor->process($node->nodeValue);
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

            default:
                if ($node->hasChildNodes()) {
                    foreach ($node->childNodes as $childNode) {
                        $this->traverseDom($childNode);
                    }
                }
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
}
