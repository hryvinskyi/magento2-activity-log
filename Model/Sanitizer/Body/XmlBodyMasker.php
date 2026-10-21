<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Sanitizer\Body;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Magento\Framework\DomDocument\DomDocumentFactory;

/**
 * An XML document: the content of every element and the value of every attribute under a secret name is masked
 *
 * The document is parsed without loading anything from outside it: no network access, no external DTD or entity,
 * no entity substitution, so an entity reference is written back as the reference it was. A document without a
 * secret is returned as it came, byte for byte.
 */
class XmlBodyMasker implements BodyMaskerInterface
{
    private const DECLARATION = '<?xml';

    /**
     * @param DomDocumentFactory $documents
     */
    public function __construct(
        private readonly DomDocumentFactory $documents
    ) {
    }

    /**
     * @inheritDoc
     */
    public function mask(string $body, SanitizerInterface $sanitizer): ?string
    {
        $trimmed = ltrim($body);
        if (!str_starts_with($trimmed, '<')) {
            return null;
        }

        $document = $this->parse($body);
        $root = $document?->documentElement;
        if ($document === null || $root === null) {
            return null;
        }

        if (!$this->maskElement($root, $sanitizer)) {
            return $body;
        }

        return $this->write($document, str_starts_with($trimmed, self::DECLARATION)) ?? SanitizerInterface::MASK;
    }

    /**
     * Parse the text as XML without reaching outside it
     *
     * @param string $body
     * @return \DOMDocument|null Null when the text is not well-formed XML
     */
    private function parse(string $body): ?\DOMDocument
    {
        $document = $this->documents->create();
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($body, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $document : null;
    }

    /**
     * Mask an element's secret attributes, then its content when its name is secret, or else its children's
     *
     * @param \DOMElement $element
     * @param SanitizerInterface $sanitizer
     * @return bool Whether anything was masked
     */
    private function maskElement(\DOMElement $element, SanitizerInterface $sanitizer): bool
    {
        $changed = $this->maskAttributes($element, $sanitizer);

        if ($sanitizer->isSecretField($element->localName ?? $element->nodeName)) {
            return $this->replaceContent($element) || $changed;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof \DOMElement && $this->maskElement($child, $sanitizer)) {
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Mask the value of every attribute under a secret name
     *
     * @param \DOMElement $element
     * @param SanitizerInterface $sanitizer
     * @return bool Whether anything was masked
     */
    private function maskAttributes(\DOMElement $element, SanitizerInterface $sanitizer): bool
    {
        $changed = false;
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if ($attribute->value !== ''
                && $sanitizer->isSecretField($attribute->localName ?? $attribute->name)
            ) {
                $attribute->value = SanitizerInterface::MASK;
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Replace whatever an element holds by the mask; an empty element stays empty
     *
     * @param \DOMElement $element
     * @return bool Whether anything was masked
     */
    private function replaceContent(\DOMElement $element): bool
    {
        if (!$element->hasChildNodes()) {
            return false;
        }

        $element->textContent = SanitizerInterface::MASK;

        return true;
    }

    /**
     * Write the masked document back, with an XML declaration only when the original had one
     *
     * @param \DOMDocument $document
     * @param bool $declared
     * @return string|null Null when the document cannot be written
     */
    private function write(\DOMDocument $document, bool $declared): ?string
    {
        // Without an encoding, the mask in an attribute would be written as character references
        $document->encoding ??= 'UTF-8';
        if ($declared) {
            $xml = $document->saveXML();

            return $xml === false ? null : $xml;
        }

        $parts = [];
        foreach (iterator_to_array($document->childNodes) as $node) {
            $xml = $document->saveXML($node);
            if ($xml === false) {
                return null;
            }
            $parts[] = $xml;
        }

        return implode("\n", $parts);
    }
}
