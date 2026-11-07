<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller\Fixture;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use PHPUnit\Framework\Assert;

/**
 * The Activity Log menu as an admin page prints it, read the way a browser reads it
 *
 * A titled group is an element with role="group", named by its visible heading through aria-labelledby. An entry is
 * a link of the menu: its label, its address, and whether it shows an icon. An entry outside every titled group has
 * the empty heading. A channel module's test reads its own entries through this class, so the menu's markup is read
 * in one place.
 */
class ActivityLogMenu
{
    /**
     * The menu's title, printed on its toggle button
     */
    public const TITLE = 'Activity Log';

    /**
     * @var DOMXPath
     */
    private DOMXPath $xpath;

    /**
     * @var DOMElement
     */
    private DOMElement $menu;

    /**
     * Find the Activity Log menu in a page; fails unless the page prints it exactly once
     *
     * @param string $html
     */
    public function __construct(string $html)
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->xpath = new DOMXPath($document);

        $menus = [];
        $wrappers = $this->elements(
            '//div[contains(concat(" ", normalize-space(@class), " "), " hryvinskyi-core-menu ")]'
        );
        foreach ($wrappers as $wrapper) {
            $titles = $this->elements('.//button//*[contains(@class, "admin__action-dropdown-text")]', $wrapper);
            if ($titles !== [] && $this->text($titles[0]) === self::TITLE) {
                $menus[] = $wrapper;
            }
        }
        Assert::assertCount(1, $menus, 'the page prints the Activity Log menu once');
        $this->menu = $menus[0];
    }

    /**
     * Get the headings of the titled groups, in the order the menu prints them
     *
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(
            fn (DOMElement $group): string => $this->headingOf($group),
            $this->elements('.//*[@role="group"]', $this->menu)
        );
    }

    /**
     * Get the address of every link of the menu, in the order the menu prints them
     *
     * @return list<string>
     */
    public function links(): array
    {
        return array_map(
            static fn (DOMElement $link): string => $link->getAttribute('href'),
            $this->elements('.//li/a', $this->menu)
        );
    }

    /**
     * Get the one entry with this label; fails unless the menu prints exactly one
     *
     * @param string $label
     * @return array{heading: string, href: string, icon: bool}
     */
    public function entry(string $label): array
    {
        $entries = array_values(array_filter(
            $this->elements('.//li/a', $this->menu),
            fn (DOMElement $link): bool => $this->text($link) === $label
        ));
        Assert::assertCount(1, $entries, 'the menu prints one entry "' . $label . '"');
        $link = $entries[0];

        $groups = $this->elements('ancestor::*[@role="group"][1]', $link);

        return [
            'heading' => $groups === [] ? '' : $this->headingOf($groups[0]),
            'href' => $link->getAttribute('href'),
            'icon' => $this->elements('.//*[contains(@class, "menu-item-icon")]//*[local-name() = "svg"]', $link)
                !== [],
        ];
    }

    /**
     * Get the visible heading that names a group; fails unless the group names exactly one element
     *
     * @param DOMElement $group
     * @return string
     */
    private function headingOf(DOMElement $group): string
    {
        $id = $group->getAttribute('aria-labelledby');
        Assert::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $id, 'a group is named by an element id');
        $headings = $this->elements('//*[@id="' . $id . '"]');
        Assert::assertCount(1, $headings, 'the id ' . $id . ' names one element');

        return $this->text($headings[0]);
    }

    /**
     * Get the elements an XPath expression selects
     *
     * @param string $expression
     * @param DOMNode|null $context
     * @return list<DOMElement>
     */
    private function elements(string $expression, ?DOMNode $context = null): array
    {
        $nodes = $this->xpath->query($expression, $context);
        Assert::assertNotFalse($nodes, $expression . ' is a valid expression');

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * Get an element's text as a reader sees it, with runs of white space as one space
     *
     * @param DOMElement $element
     * @return string
     */
    private function text(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}
