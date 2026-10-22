<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\ValueKind;
use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;
use Hryvinskyi\ActivityLog\Api\Search\EventDetailProviderInterface;

/**
 * A detail provider for tests: the same details for every activity of its channel, built through the factories a
 * module would use, with a short, a long and a masked field
 *
 * It stands in for a module that records changes; it remembers which ids it was asked about.
 */
class FixedDetailProvider implements EventDetailProviderInterface
{
    public const LONG_VALUE_LENGTH = 5000;

    /**
     * @var list<list<int>>
     */
    public array $calls = [];

    /**
     * The recorded subject names each call was given
     *
     * @var list<array<int, string>>
     */
    public array $labels = [];

    /**
     * @param EventDetailInterfaceFactory $detailFactory
     * @param FieldChangeInterfaceFactory $fieldFactory
     * @param string $channel
     */
    public function __construct(
        private readonly EventDetailInterfaceFactory $detailFactory,
        private readonly FieldChangeInterfaceFactory $fieldFactory,
        private readonly string $channel = 'zz_detail'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(string $channel): bool
    {
        return $channel === $this->channel;
    }

    /**
     * @inheritDoc
     */
    public function detailsFor(array $activityIds, array $subjectLabels = []): array
    {
        $this->calls[] = $activityIds;
        $this->labels[] = $subjectLabels;

        $fields = [
            $this->fieldFactory->create()->withField('price', 'Price')->withScope('Default Store View')
                ->withValues('34.00', '39.00'),
            $this->fieldFactory->create()->withField('description', 'Description')->withKind(ValueKind::Long)
                ->withValues(str_repeat('a', self::LONG_VALUE_LENGTH), 'short now'),
            $this->fieldFactory->create()->withField('api_secret')->withKind(ValueKind::Masked)
                ->withValues('••••••', '••••••'),
        ];

        $details = [];
        foreach ($activityIds as $activityId) {
            $details[$activityId] = $this->detailFactory->create()
                ->withVerb(Verb::Changed)
                ->withWhat(null, 4)
                ->withSubject('Thing', '/admin/thing/edit/id/' . $activityId . '/')
                ->withSubjectLabel($this->withoutKind($subjectLabels[$activityId] ?? null))
                ->withFields($fields, 1)
                ->withUnchangedCount(2);
        }

        return $details;
    }

    /**
     * A recorded name without the "Thing " it begins with, or null to show it as recorded
     *
     * @param string|null $recorded
     * @return string|null
     */
    private function withoutKind(?string $recorded): ?string
    {
        return $recorded !== null && str_starts_with($recorded, 'Thing ') ? substr($recorded, 6) : null;
    }
}
