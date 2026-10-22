<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;

/**
 * Immutable event details, created empty through EventDetailInterfaceFactory and filled through its with…() methods
 */
class EventDetail implements EventDetailInterface
{
    /**
     * @var Verb
     */
    private Verb $verb = Verb::Changed;

    /**
     * @var string|null
     */
    private ?string $what = null;

    /**
     * @var int
     */
    private int $fieldCount = 0;

    /**
     * @var int
     */
    private int $automaticCount = 0;

    /**
     * @var string|null
     */
    private ?string $subjectTypeLabel = null;

    /**
     * @var string|null
     */
    private ?string $subjectLabel = null;

    /**
     * @var string|null
     */
    private ?string $subjectUrl = null;

    /**
     * @var list<FieldChangeInterface>
     */
    private array $fields = [];

    /**
     * @var int
     */
    private int $moreFieldCount = 0;

    /**
     * @var int
     */
    private int $unchangedCount = 0;

    /**
     * @var bool
     */
    private bool $unchangedRecorded = true;

    /**
     * @param LinkPolicy $linkPolicy
     */
    public function __construct(
        private readonly LinkPolicy $linkPolicy
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getVerb(): Verb
    {
        return $this->verb;
    }

    /**
     * @inheritDoc
     */
    public function getWhat(): ?string
    {
        return $this->what;
    }

    /**
     * @inheritDoc
     */
    public function getFieldCount(): int
    {
        return $this->fieldCount;
    }

    /**
     * @inheritDoc
     */
    public function getAutomaticCount(): int
    {
        return min($this->automaticCount, $this->fieldCount);
    }

    /**
     * @inheritDoc
     */
    public function getSubjectTypeLabel(): ?string
    {
        return $this->subjectTypeLabel;
    }

    /**
     * @inheritDoc
     */
    public function getSubjectLabel(): ?string
    {
        return $this->subjectLabel;
    }

    /**
     * @inheritDoc
     */
    public function getSubjectUrl(): ?string
    {
        return $this->subjectUrl;
    }

    /**
     * @inheritDoc
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @inheritDoc
     */
    public function getMoreFieldCount(): int
    {
        return $this->moreFieldCount;
    }

    /**
     * @inheritDoc
     */
    public function getUnchangedCount(): int
    {
        return $this->unchangedCount;
    }

    /**
     * @inheritDoc
     */
    public function areUnchangedFieldsRecorded(): bool
    {
        return $this->unchangedRecorded;
    }

    /**
     * @inheritDoc
     */
    public function withVerb(Verb $verb): EventDetailInterface
    {
        $copy = clone $this;
        $copy->verb = $verb;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withWhat(?string $what, int $fieldCount): EventDetailInterface
    {
        $this->assertCount($fieldCount, 'fields touched');

        $copy = clone $this;
        $copy->what = $what === '' ? null : $what;
        $copy->fieldCount = $fieldCount;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withAutomaticCount(int $automaticCount): EventDetailInterface
    {
        $this->assertCount($automaticCount, 'fields set automatically');

        $copy = clone $this;
        $copy->automaticCount = $automaticCount;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withSubject(?string $typeLabel, ?string $url): EventDetailInterface
    {
        if ($url !== null) {
            $this->linkPolicy->assertSafe($url);
        }

        $copy = clone $this;
        $copy->subjectTypeLabel = $typeLabel === '' ? null : $typeLabel;
        $copy->subjectUrl = $url;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withSubjectLabel(?string $label): EventDetailInterface
    {
        $copy = clone $this;
        $copy->subjectLabel = $label === null || trim($label) === '' ? null : $label;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withFields(array $fields, int $moreFieldCount = 0): EventDetailInterface
    {
        $this->assertCount($moreFieldCount, 'further fields');
        $rows = [];
        foreach ($fields as $field) {
            $rows[] = $this->row($field);
        }

        $copy = clone $this;
        $copy->fields = $rows;
        $copy->moreFieldCount = $moreFieldCount;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withUnchangedCount(int $unchangedCount, bool $recorded = true): EventDetailInterface
    {
        $this->assertCount($unchangedCount, 'unchanged fields');

        $copy = clone $this;
        $copy->unchangedCount = $unchangedCount;
        $copy->unchangedRecorded = $recorded;

        return $copy;
    }

    /**
     * One row, refusing anything that is not a field change
     *
     * @param mixed $field
     * @return FieldChangeInterface
     * @throws \InvalidArgumentException When it is something else
     */
    private function row(mixed $field): FieldChangeInterface
    {
        if (!$field instanceof FieldChangeInterface) {
            throw new \InvalidArgumentException(
                sprintf('A field row is a %s, not %s.', FieldChangeInterface::class, get_debug_type($field))
            );
        }

        return $field;
    }

    /**
     * Refuse a negative count
     *
     * @param int $count
     * @param string $what
     * @return void
     * @throws \InvalidArgumentException When it is negative
     */
    private function assertCount(int $count, string $what): void
    {
        if ($count < 0) {
            throw new \InvalidArgumentException(sprintf('A number of %s is 0 or more, not %d.', $what, $count));
        }
    }
}
