<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data;

use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;

/**
 * Immutable actor, created through ActorInterfaceFactory
 */
class Actor implements ActorInterface
{
    public const NAME_MAX_LENGTH = 255;
    public const ID_MAX_LENGTH = 64;
    public const VIA_CLIENT_MAX_LENGTH = 128;
    public const VIA_VERSION_MAX_LENGTH = 32;

    /**
     * @param ActorType $type
     * @param string $name
     * @param string|null $id
     * @param string|null $viaClient
     * @param string|null $viaVersion
     * @param string|null $viaChannel
     * @throws \InvalidArgumentException When a value breaks the journal's rules
     */
    public function __construct(
        private readonly ActorType $type,
        private readonly string $name,
        private readonly ?string $id = null,
        private readonly ?string $viaClient = null,
        private readonly ?string $viaVersion = null,
        private readonly ?string $viaChannel = null
    ) {
        if ($viaChannel !== null && ($viaClient === null || preg_match(Activity::CHANNEL_PATTERN, $viaChannel) !== 1)) {
            throw new \InvalidArgumentException(
                sprintf('A delegation channel "%s" needs a client name and a well-formed code.', $viaChannel)
            );
        }

        if (trim($name) === '' || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('An actor name is 1 to %d characters long.', self::NAME_MAX_LENGTH)
            );
        }

        if ($id !== null && ($id === '' || mb_strlen($id) > self::ID_MAX_LENGTH)) {
            throw new \InvalidArgumentException(
                sprintf('An actor id is 1 to %d characters long.', self::ID_MAX_LENGTH)
            );
        }

        if ($viaClient !== null && (trim($viaClient) === '' || mb_strlen($viaClient) > self::VIA_CLIENT_MAX_LENGTH)) {
            throw new \InvalidArgumentException(
                sprintf('A client name is 1 to %d characters long.', self::VIA_CLIENT_MAX_LENGTH)
            );
        }

        if ($viaVersion !== null && ($viaClient === null || mb_strlen($viaVersion) > self::VIA_VERSION_MAX_LENGTH)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'A client version needs a client name and is at most %d characters long.',
                    self::VIA_VERSION_MAX_LENGTH
                )
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): ActorType
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getViaClient(): ?string
    {
        return $this->viaClient;
    }

    /**
     * @inheritDoc
     */
    public function getViaVersion(): ?string
    {
        return $this->viaVersion;
    }

    /**
     * @inheritDoc
     */
    public function getViaChannel(): ?string
    {
        return $this->viaChannel;
    }
}
