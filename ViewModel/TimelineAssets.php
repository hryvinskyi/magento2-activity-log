<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\ViewModel;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Hryvinskyi\ActivityLog\Model\Presentation\ActorTypeLabels;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Source\OutcomeOptions;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the activity timeline prints with, in the admin user's language: its wording, the words of each verb, the
 * labels and colours of channels, kinds of actor and outcomes, and how many activities a text search looks through
 * at most (the timeline names that number when a search was narrowed to them)
 *
 * A page showing the timeline gets it by including the layout handle "hryvinskyi_activitylog_timeline_assets",
 * which prints this as JSON beside the timeline's element templates and stylesheet. The timeline composes its
 * sentences from these strings and holds no wording of its own. "%1", "%2"… in a string stand for the numbers or
 * names the timeline puts in; strings with "One" and "Many" in their name are the singular and plural of one text.
 *
 * @api
 */
class TimelineAssets implements ArgumentInterface
{
    /**
     * @param ChannelPresenter $channelPresenter
     * @param ActorTypeLabels $actorTypeLabels
     * @param OutcomeOptions $outcomeOptions
     * @param JsonHexTag $jsonHexTag
     */
    public function __construct(
        private readonly ChannelPresenter $channelPresenter,
        private readonly ActorTypeLabels $actorTypeLabels,
        private readonly OutcomeOptions $outcomeOptions,
        private readonly JsonHexTag $jsonHexTag
    ) {
    }

    /**
     * The timeline's config as JSON safe to print inside a script element
     *
     * @return string
     */
    public function configJson(): string
    {
        return (string) $this->jsonHexTag->serialize($this->config());
    }

    /**
     * The timeline's config: wording, verbs, channels, kinds of actor, outcomes and the candidate limit
     *
     * @return array{
     *     wording: array<string, string>,
     *     verbs: array<string, string>,
     *     channels: array<string, array{label: string, chart: string, bg: string, fg: string}>,
     *     actorTypes: array<string, string>,
     *     outcomes: array<string, string>,
     *     candidateLimit: int
     * }
     */
    public function config(): array
    {
        $verbs = [];
        foreach (Verb::cases() as $verb) {
            $verbs[$verb->value] = (string) $this->verb($verb);
        }
        $actorTypes = [];
        foreach (ActorType::cases() as $type) {
            $actorTypes[$type->value] = (string) $this->actorTypeLabels->label($type);
        }
        $outcomes = [];
        foreach (Outcome::cases() as $outcome) {
            $outcomes[$outcome->value] = (string) $this->outcomeOptions->label($outcome);
        }

        return [
            'wording' => array_map(static fn (Phrase $phrase): string => (string) $phrase, $this->wording()),
            'verbs' => $verbs,
            'channels' => $this->channelPresenter->present(),
            'actorTypes' => $actorTypes,
            'outcomes' => $outcomes,
            'candidateLimit' => SearchQueryBuilder::CANDIDATE_LIMIT,
        ];
    }

    /**
     * Words of a verb
     *
     * @param Verb $verb
     * @return Phrase
     */
    private function verb(Verb $verb): Phrase
    {
        return match ($verb) {
            Verb::Changed => __('changed'),
            Verb::Created => __('created'),
            Verb::Deleted => __('deleted'),
            Verb::Performed => __('performed'),
            Verb::TriedToChange => __('tried to change'),
            Verb::TriedToDelete => __('tried to delete'),
        };
    }

    /**
     * The timeline's wording by name
     *
     * @return array<string, Phrase>
     */
    private function wording(): array
    {
        return [
            'via' => __('via'),
            'on' => __('on'),
            'empty' => __('(empty)'),
            'masked' => __('masked'),
            'maskedNote' => __('encrypted value · changed, never stored or searchable in clear text'),
            'automatic' => __('set automatically'),
            'automaticUpdated' => __('Magento updated'),
            'automaticDuring' => __('during a save by'),
            'severalScopes' => __('several scopes'),
            'createdWithOne' => __('with %1 field'),
            'createdWith' => __('with %1 fields'),
            'fields' => __('%1 fields'),
            'moreFields' => __('and %1 more fields.'),
            'openRecord' => __('Open the record'),
            'showFullDiff' => __('Show full diff'),
            'collapse' => __('Collapse'),
            'longMeta' => __('%1 → %2 chars · %3 edits'),
            'truncated' => __('cut for display; the whole value is on the details page'),
            'jsonMeta' => __('%1 added · %2 removed · %3 unchanged'),
            'showUnchanged' => __('Show unchanged'),
            'hideUnchanged' => __('Hide unchanged'),
            'unchangedOne' => __('%1 unchanged field recorded (snapshot mode)'),
            'unchangedMany' => __('%1 unchanged fields recorded (snapshot mode)'),
            'unchangedCountOne' => __('%1 other field unchanged'),
            'unchangedCountMany' => __('%1 other fields unchanged'),
            'unchangedPartial' => __('Showing %1 of %2.'),
            'siblings' => __('%1 more in this request'),
            'siblingsCapped' => __('Only the first %1 are listed. Filter by the request to see them all.'),
            'correlationTitle' => __('Correlation ID · click to filter the whole request'),
            'openDetails' => __('Open details →'),
            'dayEventsOne' => __('%1 event'),
            'dayEventsMany' => __('%1 events'),
            'dayToday' => __('Today · %1'),
            'dayYesterday' => __('Yesterday · %1'),
            'changeOne' => __('change'),
            'changeMany' => __('changes'),
            'events' => __('events'),
            'newestFirst' => __('newest first'),
            'showingOf' => __('Showing %1 of %2'),
            'atLeast' => __('%1+'),
            'loadMore' => __('Load %1 more'),
            'loadedOf' => __('Loaded %1 of %2 · keyset paging by time and id'),
            'loading' => __('Loading activities…'),
            'emptyLogTitle' => __('Nothing recorded yet'),
            'emptyLogText' => __(
                'Logging was enabled a moment ago. The first entries appear as soon as something changes. '
                . 'In the meantime, these are the kinds of questions this page answers:'
            ),
            'noResultsTitle' => __('No changes match in %1'),
            'outsideOne' => __('%1 matching change is outside the selected range. The latest is from %2.'),
            'outsideMany' => __('%1 matching changes are outside the selected range. The latest is from %2.'),
            'noMatch' => __(
                'Nothing in the log matches all of these filters. Remove one, or check the spelling of a value.'
            ),
            'nothingInRange' => __('Nothing was recorded in this range.'),
            'widen' => __('Widen to last 30 days'),
            'removeLast' => __('Remove last filter'),
            'clearAll' => __('Clear all'),
            'narrow' => __(
                'More activities match the other filters than a text search looks through, so only the newest %1 '
                . 'were searched. Narrow the search to find older matches.'
            ),
            'loadError' => __('The results could not be loaded.'),
            'retry' => __('Retry'),
        ];
    }
}
