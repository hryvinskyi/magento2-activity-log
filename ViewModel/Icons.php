<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\ViewModel;

use Hryvinskyi\ActivityLog\Api\IconsInterface;
use Hryvinskyi\ActivityLog\Model\Icon\InlineSvg;
use Magento\Framework\App\Area;
use Magento\Framework\View\Asset\File\NotFoundException;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Reads an icon from the module that ships it, through the view-file fallback, so a theme can replace it
 */
class Icons implements IconsInterface
{
    private const ID = '/^([A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*)::([a-z0-9]+(?:-[a-z0-9]+)*)$/';
    private const FILE = '%s::images/icons/%s.svg';

    /**
     * Icon files read in this request, by id
     *
     * @var array<string, string>
     */
    private array $files = [];

    /**
     * @param AssetRepository $assets
     * @param InlineSvg $inlineSvg
     */
    public function __construct(
        private readonly AssetRepository $assets,
        private readonly InlineSvg $inlineSvg
    ) {
    }

    /**
     * @inheritDoc
     */
    public function render(string $icon, string $class = ''): string
    {
        return $this->inlineSvg->markup($this->files[$icon] ??= $this->file($icon), $class);
    }

    /**
     * The text of the icon's file
     *
     * @param string $icon
     * @return string
     * @throws \InvalidArgumentException When the id is not "Vendor_Module::name", or the module ships no such icon
     */
    private function file(string $icon): string
    {
        if (preg_match(self::ID, $icon, $parts) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not an icon id of the form "Vendor_Module::name".', $icon)
            );
        }

        try {
            return $this->assets->createAsset(
                sprintf(self::FILE, $parts[1], $parts[2]),
                ['area' => Area::AREA_ADMINHTML]
            )->getContent();
        } catch (NotFoundException $exception) {
            throw new \InvalidArgumentException(sprintf('No module ships the icon "%s".', $icon), 0, $exception);
        }
    }
}
