<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Inline SVG icons for admin templates
 *
 * An icon is a Lucide SVG file a module ships as view/adminhtml/web/images/icons/<name>.svg, named
 * "Vendor_Module::name". Templates get this as a layout argument.
 *
 * @api
 */
interface IconsInterface extends ArgumentInterface
{
    /**
     * The icon as inline SVG markup, decorative: hidden from assistive technology
     *
     * @param string $icon "Vendor_Module::name", the name in lower case with hyphens
     * @param string $class CSS classes for the svg element: letters, digits, "-", "_" and spaces
     * @return string
     * @throws \InvalidArgumentException When the icon id or the classes are malformed, or no module ships the icon
     */
    public function render(string $icon, string $class = ''): string;
}
