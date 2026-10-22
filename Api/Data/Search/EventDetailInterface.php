<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * What the module that recorded an activity can say about it beyond the journal: the verb of the sentence, what
 * changed, a link to the subject, and the changed fields
 *
 * Everything is a code, a number or data; the page holds the words. "What" is the label of the one thing that
 * changed, or null with a field count the page turns into "N fields".
 *
 * Some of the fields an actor's activity changed may have been set automatically: the system changed them while it
 * carried out what the actor asked, without the actor asking for them. They are counted apart (getAutomaticCount())
 * and their rows say so; the sentence counts only the fields the actor asked for, and an activity in which every
 * changed field was set automatically reads as the system's doing.
 *
 * Create one through EventDetailInterfaceFactory and fill it with the with…() methods, each of which returns a copy.
 *
 * @api
 */
interface EventDetailInterface
{
    /**
     * What the activity did to its subject
     *
     * @return Verb
     */
    public function getVerb(): Verb;

    /**
     * Label of the one thing that changed, or null when several did or none is worth naming
     *
     * @return string|null
     */
    public function getWhat(): ?string;

    /**
     * Number of fields the activity touched, those beyond the rows included
     *
     * @return int
     */
    public function getFieldCount(): int;

    /**
     * Number of the fields touched that were set automatically, those beyond the rows included; at most getFieldCount()
     *
     * @return int
     */
    public function getAutomaticCount(): int;

    /**
     * The kind of subject as the admin calls it (a record, a setting), or null
     *
     * @return string|null
     */
    public function getSubjectTypeLabel(): ?string;

    /**
     * The subject's name as the sentence shows it after the subject type label, or null for the recorded name
     *
     * A recording module whose recorded names begin with the kind of subject ("Record 1245 · Bag") gives the name
     * without it, so the sentence does not name the kind twice; the journal keeps what it recorded.
     *
     * @return string|null
     */
    public function getSubjectLabel(): ?string;

    /**
     * Where the admin can open the subject, or null when it no longer exists or the admin may not open it
     *
     * @return string|null An http(s) URL or a path from the site root
     */
    public function getSubjectUrl(): ?string;

    /**
     * The field rows to show, in order
     *
     * @return list<FieldChangeInterface>
     */
    public function getFields(): array;

    /**
     * Fields the activity touched that have no row here
     *
     * @return int
     */
    public function getMoreFieldCount(): int;

    /**
     * Fields compared that kept their value
     *
     * When their rows were recorded (a snapshot of the whole record) this is the number of rows that can be shown;
     * when only their number was kept, it is all there is.
     *
     * @return int
     */
    public function getUnchangedCount(): int;

    /**
     * Whether the fields that kept their value were recorded with their rows, so they can be opened
     *
     * @return bool
     */
    public function areUnchangedFieldsRecorded(): bool;

    /**
     * A copy with this verb
     *
     * @param Verb $verb
     * @return EventDetailInterface
     */
    public function withVerb(Verb $verb): EventDetailInterface;

    /**
     * A copy saying what changed
     *
     * @param string|null $what Label of the one thing that changed, or null
     * @param int $fieldCount Fields touched, zero or more
     * @return EventDetailInterface
     * @throws \InvalidArgumentException When the count is negative
     */
    public function withWhat(?string $what, int $fieldCount): EventDetailInterface;

    /**
     * A copy with the number of the fields touched that were set automatically
     *
     * @param int $automaticCount Zero or more; more than the fields touched counts as all of them
     * @return EventDetailInterface
     * @throws \InvalidArgumentException When the count is negative
     */
    public function withAutomaticCount(int $automaticCount): EventDetailInterface;

    /**
     * A copy describing the subject
     *
     * @param string|null $typeLabel
     * @param string|null $url An http(s) URL or a path from the site root, or null
     * @return EventDetailInterface
     * @throws \InvalidArgumentException When the URL is of another kind
     */
    public function withSubject(?string $typeLabel, ?string $url): EventDetailInterface;

    /**
     * A copy naming the subject this way in the sentence
     *
     * @param string|null $label The name without the kind of subject, or null (or empty) for the recorded name
     * @return EventDetailInterface
     */
    public function withSubjectLabel(?string $label): EventDetailInterface;

    /**
     * A copy with these field rows
     *
     * @param list<FieldChangeInterface> $fields
     * @param int $moreFieldCount Fields touched beyond these rows, zero or more
     * @return EventDetailInterface
     * @throws \InvalidArgumentException When a row is not a field change or the count is negative
     */
    public function withFields(array $fields, int $moreFieldCount = 0): EventDetailInterface;

    /**
     * A copy with the number of fields that kept their value, and whether their rows were recorded
     *
     * @param int $unchangedCount Zero or more
     * @param bool $recorded Whether a row of each was recorded; false when only their number was kept
     * @return EventDetailInterface
     * @throws \InvalidArgumentException When the count is negative
     */
    public function withUnchangedCount(int $unchangedCount, bool $recorded = true): EventDetailInterface;
}
