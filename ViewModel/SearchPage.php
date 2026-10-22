<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\ViewModel;

use Hryvinskyi\ActivityLog\Api\Data\Search\RangePreset;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyControl;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyGroup;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Api\Search\SearchExampleInterface;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\ExamplePool;
use Hryvinskyi\ActivityLog\Model\Search\KeyDescriptionPool;
use Hryvinskyi\ActivityLog\Model\Search\Preference\ResultView;
use Hryvinskyi\ActivityLog\Model\Search\Preference\SearchPreferenceStorageInterface;
use Hryvinskyi\ActivityLog\Model\Search\SuggestionsPresenter;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchParams;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the activity search page prints: its controls, the grid it wraps, and the config its script reads
 *
 * The config carries the endpoints (built with the URL builder, so they hold the admin secret key; the script only
 * appends a query string), the form key, the store's timezone and the admin's locale, the server's time to measure
 * the browser clock against, the search keys, the channels and which of them record changes, the admin user's view
 * choices, the contributed example searches, the names of the grid's components and the page's wording.
 *
 * "Changes only" exists only while some channel records changes; without one the page neither offers nor applies it.
 * The filters panel has a control for each search key its description puts there.
 */
class SearchPage implements ArgumentInterface
{
    /**
     * The listing the page shows as its grid view
     */
    public const LISTING = 'hryvinskyi_activity_log_listing';

    /**
     * The listing's columns, which hold its row selection ("ids")
     */
    private const COLUMNS = 'hryvinskyi_activity_log_columns';

    /**
     * The module's admin route id: admin links name the route by its id, which the secret key is computed from
     */
    private const ROUTE = 'hryvinskyi_activitylog';

    /**
     * How long the script waits for an endpoint, in ms
     */
    private const TIMEOUT_MS = 20000;

    /**
     * Groups of suggestions whose first key the search bar's placeholder names, in that order
     */
    private const PLACEHOLDER_GROUPS = [SearchKeyGroup::Fields, SearchKeyGroup::Records, SearchKeyGroup::People];

    /**
     * @var array{view: ResultView, changesOnly: bool}|null
     */
    private ?array $preferences = null;

    /**
     * @param KeyDescriptionPool $keyDescriptionPool
     * @param SuggestionsPresenter $suggestionsPresenter
     * @param ExamplePool $examplePool
     * @param ChangeChannels $changeChannels
     * @param ChannelPresenter $channelPresenter
     * @param SearchPreferenceStorageInterface $preferenceStorage
     * @param MillisecondClockInterface $clock
     * @param TimezoneInterface $timezone
     * @param ResolverInterface $localeResolver
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param JsonHexTag $jsonHexTag
     */
    public function __construct(
        private readonly KeyDescriptionPool $keyDescriptionPool,
        private readonly SuggestionsPresenter $suggestionsPresenter,
        private readonly ExamplePool $examplePool,
        private readonly ChangeChannels $changeChannels,
        private readonly ChannelPresenter $channelPresenter,
        private readonly SearchPreferenceStorageInterface $preferenceStorage,
        private readonly MillisecondClockInterface $clock,
        private readonly TimezoneInterface $timezone,
        private readonly ResolverInterface $localeResolver,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly JsonHexTag $jsonHexTag
    ) {
    }

    /**
     * The activity dashboard
     *
     * @return string
     */
    public function dashboardUrl(): string
    {
        return $this->urlBuilder->getUrl(self::ROUTE . '/dashboard/index');
    }

    /**
     * Whether the page offers "Changes only": some channel records changes
     *
     * @return bool
     */
    public function offersChangesOnly(): bool
    {
        return $this->changeChannels->codes() !== [];
    }

    /**
     * Whether "Changes only" starts checked, before the link's filters are read
     *
     * @return bool
     */
    public function changesOnly(): bool
    {
        return $this->preferences()['changesOnly'];
    }

    /**
     * Whether the grid is the view the page opens with
     *
     * @return bool
     */
    public function opensGrid(): bool
    {
        return $this->preferences()['view'] === ResultView::Grid;
    }

    /**
     * The store's timezone, in which times are shown and entered
     *
     * @return string An IANA name, such as "Europe/Copenhagen"
     */
    public function timeZone(): string
    {
        return $this->timezone->getConfigTimezone();
    }

    /**
     * The range presets and their names, in the order the popover lists them; "custom" last
     *
     * @return list<array{code: string, label: string}>
     */
    public function presets(): array
    {
        $presets = [];
        foreach ($this->presetLabels() as $code => $label) {
            $presets[] = ['code' => $code, 'label' => $label];
        }

        return $presets;
    }

    /**
     * The filters panel's controls, in panel order: one per key the panel shows
     *
     * A select may have a companion text box; its options then carry the key a value typed there is searched under
     * ("target", empty for plain text).
     *
     * @return list<array{key: string, label: string, hint: string, select: bool,
     *     options: list<array{value: string, label: string, target: string}>,
     *     companion: array{label: string, hint: string}|null}>
     */
    public function filterControls(): array
    {
        $controls = [];
        foreach ($this->keyDescriptionPool->all() as $description) {
            if (!$description->isInPanel()) {
                continue;
            }
            $targets = $description->getOptionTargets();
            $controls[] = [
                'key' => $description->getKey(),
                'label' => $description->getLabel(),
                'hint' => $description->getHint(),
                'select' => $description->getControl() === SearchKeyControl::Select,
                'options' => array_map(
                    static fn (array $option): array => $option + ['target' => $targets[$option['value']] ?? ''],
                    $description->getOptions()
                ),
                'companion' => $description->getCompanionLabel() === ''
                    ? null
                    : ['label' => $description->getCompanionLabel(), 'hint' => $description->getCompanionHint()],
            ];
        }

        return $controls;
    }

    /**
     * The contributed example searches, in order
     *
     * @return list<array{title: string, chips: list<array{key: string, value: string}>, preset: string}>
     */
    public function examples(): array
    {
        return array_map(
            static fn (SearchExampleInterface $example): array => [
                'title' => $example->getTitle(),
                'chips' => $example->getChips(),
                'preset' => $example->getPreset()->value,
            ],
            $this->examplePool->all()
        );
    }

    /**
     * The search bar's placeholder: a few keys to filter by, or any text
     *
     * @return string
     */
    public function placeholder(): string
    {
        $keys = [];
        $descriptions = $this->keyDescriptionPool->all();
        foreach (self::PLACEHOLDER_GROUPS as $group) {
            foreach ($descriptions as $description) {
                if ($description->getGroup() === $group) {
                    $keys[] = $description->getKey() . ':';
                    break;
                }
            }
        }

        return $keys === []
            ? (string) __('Search changes by any text')
            : (string) __('Search changes: %1 · or any text', implode(' · ', $keys));
    }

    /**
     * The script config as JSON safe to print inside a script element
     *
     * @return string
     */
    public function scriptConfigJson(): string
    {
        return (string) $this->jsonHexTag->serialize($this->scriptConfig());
    }

    /**
     * Config the search page script reads
     *
     * @return array<string, mixed>
     */
    public function scriptConfig(): array
    {
        $preferences = $this->preferences();

        return [
            'searchUrl' => $this->urlBuilder->getUrl(self::ROUTE . '/activity/search'),
            'suggestUrl' => $this->urlBuilder->getUrl(self::ROUTE . '/activity/suggest'),
            'preferenceUrl' => $this->urlBuilder->getUrl(self::ROUTE . '/activity/savePreference'),
            'formKey' => $this->formKey->getFormKey(),
            'timeZone' => $this->timeZone(),
            'locale' => $this->localeResolver->getLocale(),
            'serverNowMs' => $this->clock->nowMs(),
            'timeoutMs' => self::TIMEOUT_MS,
            'keys' => $this->suggestionsPresenter->keys(),
            'channels' => $this->channelPresenter->present(),
            'changeChannels' => $this->changeChannels->codes(),
            'preferences' => ['view' => $preferences['view']->value, 'changesOnly' => $preferences['changesOnly']],
            'examples' => $this->examples(),
            'grid' => [
                'provider' => self::LISTING . '.' . self::LISTING . '_data_source',
                'paging' => self::LISTING . '.' . self::LISTING . '.listing_top.listing_paging',
                'selections' => self::LISTING . '.' . self::LISTING . '.' . self::COLUMNS . '.ids',
                'exportButton' => self::LISTING . '.' . self::LISTING . '.listing_top.export_button',
                'exportOption' => 'csv',
                'param' => ActivitySearchParams::PARAM,
            ],
            'wording' => $this->wording(),
        ];
    }

    /**
     * The admin user's view choices, with the defaults for what was never chosen
     *
     * @return array{view: ResultView, changesOnly: bool}
     */
    private function preferences(): array
    {
        if ($this->preferences === null) {
            $saved = $this->preferenceStorage->load();
            $this->preferences = [
                'view' => $saved['view'] ?? ResultView::Timeline,
                'changesOnly' => $this->offersChangesOnly() && ($saved['changesOnly'] ?? true),
            ];
        }

        return $this->preferences;
    }

    /**
     * Names of the range presets by code, "custom" last
     *
     * @return array<string, string>
     */
    private function presetLabels(): array
    {
        $labels = [];
        foreach (RangePreset::cases() as $preset) {
            $labels[$preset->value] = (string) match ($preset) {
                RangePreset::LastHour => __('Last hour'),
                RangePreset::Today => __('Today'),
                RangePreset::Yesterday => __('Yesterday'),
                RangePreset::LastSevenDays => __('Last 7 days'),
                RangePreset::LastThirtyDays => __('Last 30 days'),
            };
        }
        $labels['custom'] = (string) __('Custom');

        return $labels;
    }

    /**
     * Titles of the suggestion groups by code
     *
     * @return array<string, string>
     */
    private function groupTitles(): array
    {
        $titles = [];
        foreach (SearchKeyGroup::cases() as $group) {
            $titles[$group->value] = (string) match ($group) {
                SearchKeyGroup::Fields => __('Fields'),
                SearchKeyGroup::Records => __('Records'),
                SearchKeyGroup::People => __('People & clients'),
                SearchKeyGroup::Types => __('Entity types'),
                SearchKeyGroup::Other => __('Other'),
            };
        }

        return $titles;
    }

    /**
     * The page script's wording
     *
     * @return array<string, mixed>
     */
    private function wording(): array
    {
        $phrases = [
            'placeholder' => $this->placeholder(),
            'placeholderMore' => __('Add another filter…'),
            'removeChip' => __('Remove filter %1'),
            'textKey' => __('text'),
            'quoted' => __('“%1”'),
            'anywhere' => __('anywhere in the activity'),
            'unknownKey' => __('unknown filter · searched as text'),
            'filterBy' => __('Filter by'),
            'matching' => __('Matching %1'),
            'textGroup' => __('Text'),
            'typed' => __('Use as typed'),
            'limit' => __('A search takes at most %1 filters. Remove one to add another.'),
            'tooLong' => __('A filter value is at most %1 characters long.'),
            'pickDates' => __('pick dates and times'),
            'calendarFrom' => __('Click a day to set the start, then the end. Times stay as typed above.'),
            'calendarTo' => __('Now click the end day.'),
            'fromDate' => __('Enter the start date.'),
            'fromTime' => __('Enter the start time as hours and minutes.'),
            'toDate' => __('Enter the end date.'),
            'toTime' => __('Enter the end time as hours and minutes.'),
            'filtersOne' => __('%1 filter'),
            'filtersMany' => __('%1 filters'),
            'overallOne' => __('%1 matching change overall, %2 in range'),
            'overallMany' => __('%1 matching changes overall, %2 in range'),
        ];
        $wording = array_map(
            static fn (Phrase|string $phrase): string => (string) $phrase,
            $phrases
        );
        $wording['presets'] = $this->presetLabels();
        $wording['groups'] = $this->groupTitles();

        return $wording;
    }
}
