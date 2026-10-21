<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data;

use Hryvinskyi\ActivityLog\Api\Data\SubjectInterface;

/**
 * Immutable subject, created through SubjectInterfaceFactory
 */
class Subject implements SubjectInterface
{
    public const TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';
    public const ID_MAX_LENGTH = 64;
    public const LABEL_MAX_LENGTH = 255;

    /**
     * @param string $type
     * @param string $label
     * @param string|null $id
     * @throws \InvalidArgumentException When a value breaks the journal's rules
     */
    public function __construct(
        private readonly string $type,
        private readonly string $label,
        private readonly ?string $id = null
    ) {
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            throw new \InvalidArgumentException(sprintf('Malformed subject type "%s".', $type));
        }

        if (trim($label) === '' || mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A subject label is 1 to %d characters long.', self::LABEL_MAX_LENGTH)
            );
        }

        if ($id !== null && ($id === '' || mb_strlen($id) > self::ID_MAX_LENGTH)) {
            throw new \InvalidArgumentException(
                sprintf('A subject id is 1 to %d characters long.', self::ID_MAX_LENGTH)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }
}
