<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model;

use Hryvinskyi\ActivityLog\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Reads the activity log settings from the default scope: the journal is one store-wide record
 */
class Config implements ConfigInterface
{
    public const XML_PATH_ENABLED = 'hryvinskyi_activity_log/general/enabled';
    public const XML_PATH_SECRET_FIELDS = 'hryvinskyi_activity_log/security/secret_fields';
    public const XML_PATH_ORPHAN_RETENTION_DAYS = 'hryvinskyi_activity_log/retention/orphan_days';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * @inheritDoc
     */
    public function getSecretFields(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_SECRET_FIELDS);
        if (!is_string($value)) {
            return [];
        }

        $fields = [];
        foreach (preg_split('/[\s,]+/', strtolower($value)) ?: [] as $field) {
            if ($field !== '') {
                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @inheritDoc
     */
    public function getOrphanRetentionDays(): int
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_ORPHAN_RETENTION_DAYS);

        return is_numeric($value) ? max(1, (int) $value) : 90;
    }
}
