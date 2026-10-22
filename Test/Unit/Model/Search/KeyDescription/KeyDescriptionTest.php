<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\KeyDescription;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyControl;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyGroup;
use Hryvinskyi\ActivityLog\Model\Search\KeyDescription\KeyDescription;
use Magento\Framework\Data\OptionSourceInterface;
use PHPUnit\Framework\TestCase;

/**
 * A key description configured in di.xml refuses a malformed key, control or group, and says where its values are
 * suggested and whether it names fields
 */
class KeyDescriptionTest extends TestCase
{
    public function testAKeyWithoutGroupIsOfferedWithTheOthersAndNamesNoFields(): void
    {
        $description = new KeyDescription('by', 'Actor', 'Name or kind');

        self::assertSame('by', $description->getKey());
        self::assertSame(SearchKeyControl::Text, $description->getControl());
        self::assertSame(SearchKeyGroup::Other, $description->getGroup());
        self::assertFalse($description->matchesFieldNames());
        self::assertSame([], $description->getOptions());
    }

    public function testTheGroupAndTheFieldNameFlagComeFromConfiguration(): void
    {
        $description = new KeyDescription('field', 'Field', 'Code or label', group: 'fields', matchesFieldNames: true);

        self::assertSame(SearchKeyGroup::Fields, $description->getGroup());
        self::assertTrue($description->matchesFieldNames());
    }

    public function testEveryGroupCodeIsAccepted(): void
    {
        foreach (SearchKeyGroup::cases() as $group) {
            self::assertSame($group, (new KeyDescription('key', 'Key', '', group: $group->value))->getGroup());
        }
    }

    public function testAnUnknownGroupIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown suggestion group "misc".');

        new KeyDescription('by', 'Actor', '', group: 'misc');
    }

    public function testAMalformedKeyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new KeyDescription('By', 'Actor', '');
    }

    public function testASelectNeedsOptionsAndListsThemAsText(): void
    {
        $options = new class implements OptionSourceInterface {
            /**
             * @return list<array{value: string|int, label: string}>
             */
            public function toOptionArray(): array
            {
                return [['value' => 'success', 'label' => 'Success'], ['value' => 7, 'label' => 'Seven']];
            }
        };

        $description = new KeyDescription('outcome', 'Outcome', '', 'select', 10, $options);

        self::assertSame(
            [['value' => 'success', 'label' => 'Success'], ['value' => '7', 'label' => 'Seven']],
            $description->getOptions()
        );

        $this->expectException(\InvalidArgumentException::class);
        new KeyDescription('outcome', 'Outcome', '', 'select');
    }

    public function testAKeyIsInTheFiltersPanelUnlessConfiguredOut(): void
    {
        $shown = new KeyDescription('by', 'Actor', '');
        self::assertTrue($shown->isInPanel());
        self::assertSame([], $shown->getOptionTargets());
        self::assertSame('', $shown->getCompanionLabel());

        self::assertFalse((new KeyDescription('text', 'Text', '', inPanel: false))->isInPanel());
    }

    public function testASelectMayHaveACompanionBoxWhoseValueGoesToTheKeyOfTheChosenOption(): void
    {
        $description = new KeyDescription(
            'kind',
            'Kind',
            '',
            'select',
            options: $this->options(),
            optionTargets: ['a' => 'alpha', 'b' => 'beta'],
            companionLabel: 'Record',
            companionHint: 'An id'
        );

        self::assertSame(['a' => 'alpha', 'b' => 'beta'], $description->getOptionTargets());
        self::assertSame(['Record', 'An id'], [$description->getCompanionLabel(), $description->getCompanionHint()]);
    }

    public function testACompanionBoxNeedsASelect(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new KeyDescription('kind', 'Kind', '', companionLabel: 'Record');
    }

    public function testOptionTargetsNeedACompanionBox(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new KeyDescription('kind', 'Kind', '', 'select', options: $this->options(), optionTargets: ['a' => 'alpha']);
    }

    public function testAnOptionTargetMustBeAKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new KeyDescription(
            'kind',
            'Kind',
            '',
            'select',
            options: $this->options(),
            optionTargets: ['a' => 'Not A Key'],
            companionLabel: 'Record'
        );
    }

    /**
     * @return OptionSourceInterface
     */
    private function options(): OptionSourceInterface
    {
        return new class implements OptionSourceInterface {
            /**
             * @return list<array{value: string, label: string}>
             */
            public function toOptionArray(): array
            {
                return [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];
            }
        };
    }
}
