# Magento 2 Activity Log

One journal of who did what in the store back office, through which channel and with what outcome. The module ships
the journal, its dashboard, its search page and detail page. Channel modules record into it and extend those pages.

## What it does
- Records an **activity** per action: actor (admin, integration, customer, guest, CLI, cron), channel, action,
  subject, outcome (`success`, `failure`, `denied`), duration, store, IP, user agent and a correlation id shared by
  everything one operation caused.
- Masks secrets before anything is stored: passwords, tokens, keys, cookies and authorization headers, in arrays and
  in JSON, XML, form-encoded and plain-text bodies.
- Shows the journal in **System › Activity Log**: a dashboard with summary cards and widgets, a search page with a
  timeline and a grid, and a detail page per activity with the rest of its operation.
- Records an operation of a bulk action (an asynchronous web API call, an admin mass action run by the queue) for the
  admin or integration that scheduled the bulk, not for the consumer's `cli` process.
- Deletes old activities by the retention each channel sets, and activities of channels no installed module registers
  after a configurable number of days.

The module records nothing by itself. Channel modules do: for example, a module that records REST API calls.

### The search page
**System › Activity Log › All Activity** opens the page titled "Activity".
- **Search bar.** Type words, or filters written as `key: value`. A plain word is a `text` filter. A search holds at most
  20 filters, at most 8 of them `text` (a key nothing answers counts as `text`), and each value is 1 to 255
  characters. The bar suggests values that occur in the log.
- **Time range.** Last hour, Today, Yesterday, Last 7 days, Last 30 days or Custom. The default is Last 7 days. "Last N
  days" runs from the store's midnight N−1 days ago to now. A custom range is set to the minute, in the store's
  timezone.
- **Filters panel.** It shows the same filters as the chips, and each change in one appears in the other.
- **Changes only.** Keeps the activities of channels that record changes. It is on by default. It is off when a link
  asks for a request's activities, or for a channel that records no changes. With no such channel installed, the page
  does not show it.
- **Timeline or Grid.** Both show the same search. The choice and "Changes only" are remembered for each admin.

Keys of the journal:

| Key | Matches |
|-----|---------|
| `by` | Part of the actor's name, or a kind of actor: admin, integration, customer, cli, cron. A name in double quotes, `by: "admin"`, is that actor exactly; the suggestions offer people this way |
| `via` | The client an actor worked through |
| `channel` | The channel the activity came from |
| `outcome` | `success`, `failure` or `denied` |
| `store` | The store view's code or whole name; part of a name only when nothing has that code or name. Several separated by `\|` match any of them |
| `request` | The correlation id shared by everything one request did |
| `text` | Each word, as a substring, in the action, the subject, the actor or the correlation id, or in what a module recorded. Every word must be found, each in any of those places; the first 8 distinct words count |

Modules that record into the journal add keys of their own, such as a record or a field.

`text` is matched among the newest 10,000 activities that the other filters leave. When more than that match, the page
shows the partial result and asks you to narrow the search. Every other search shape stays under 300 ms on a journal of
1,000,000 rows (measured on MariaDB 10.6).

**Timeline.**
- Activities are grouped by day, with one sentence for each change and a "via <channel> <client>" part when the actor
  worked through a client.
- A change shows its values before and after. Long text shows a word diff, a JSON list shows what was added and
  removed, and a masked value shows no content.
- A field the system set by itself while carrying out what the actor asked (the module that recorded it says so) is
  tagged "set automatically" and is not counted in the sentence. A change made only of such fields reads "Magento
  updated …", with the actor named on the line below. The grid's change columns show the fields the actor asked for.
- "N more in this request" and the request chip filter by the correlation id.
- "Load 50 more" pages by time and id, so a page never skips or repeats a row.
- An empty log offers example searches. A search with no result offers to widen the range or remove the last filter. A
  search that hit the cap asks you to narrow it.

**Grid.** The grid keeps sorting, paging, the column picker, saved views and CSV export. It adds the columns Field,
Before → After and Scope. Its per-column filters and keyword box are gone: the search bar replaces them, and a data
patch clears the old filters from saved views once.
- The count reads "10,000+" past 10,000 matches. The CSV export is not capped.
- Mass delete applies to the activities of the search. "Select All" is hidden while the count reads "10,000+".

**Links.** `?<key>=<value>` opens the page with that filter, and `range=<preset>` or `range=<fromMs>-<toMs>` sets the
range. `<preset>` is `hour`, `today`, `yesterday`, `7d` or `30d`, and the two times are milliseconds since the epoch.
The older `?channel=`, `?correlation_id=` and `?actor_name=` still work.

## Requirements
- Magento 2.4 with PHP 8.1 or later
- `hryvinskyi/magento2-base` ^2.2
- `hryvinskyi/magento2-configuration-fields` ^1.0.11

## Installation
```bash
composer require hryvinskyi/magento2-activity-log
bin/magento module:enable Hryvinskyi_ActivityLog
bin/magento setup:upgrade
```

## Configuration
**Stores › Configuration › Hryvinskyi › Activity Log**
- **Record Activity**: turns the journal on or off. On by default.
- **Secret Field Names**: field names whose values are masked. Names are compared without case, with `-` and spaces
  read as `_`. `password`, `token`, `secret`, `authorization`, `cookie`, `api_key`, `apikey` and `private_key` are always
  masked, whatever this list says.
- **Keep Activity of Removed Channels (days)**: days to keep activities of channels no installed module registers.

ACL: `Hryvinskyi_ActivityLog::dashboard`, `::view`, `::delete`, and `::config` for the settings.

**System › Cache Management › Activity Log Search** (`hryvinskyi_activity_log_search`) keeps the search bar's
suggestions for 5 minutes, for each admin locale. `setup:upgrade` enables it once; if you disable it afterwards, the
module leaves it disabled.

## For channel modules
A channel module depends on this package's `Api/` contracts only:
- `ActivityBuilderInterface` and `ActivityRecorderInterface` build and record an activity. `record()` returns null when
  the journal is off or the write fails; it never throws into the operation being recorded.
- `ChannelInterface` is registered in `ChannelPool`'s `channels` argument (code, label, colours, retention).
- `SanitizerInterface` masks values (`sanitize()`) and message bodies of any format (`sanitizeBody()`).
- `CorrelationScopeInterface` groups the activities of one operation.
- `ActorScopeInterface::actAs()` runs work on someone's behalf: what it records is theirs, not the process's. A
  module whose queue consumer carries out what an admin asked for wraps the work in it.
- `UserActorsInterface::actorOf()` turns a Magento account (user type and id) into the actor the session would name.
- `DashboardWidgetInterface` adds a widget to the dashboard's `WidgetPool`.
- The detail page adds the layout handle `hryvinskyi_activitylog_activity_view_channel_<code>`; a channel puts its
  panel into the `activity.channel.panel` container.
- `IconsInterface` renders a module's SVG icons inline in admin templates.

## Extending the search
A module that records into the journal serves its own keys on the search page with the contracts below. They are
`@api`. All of them are `di.xml` arguments of pool classes, and each pool checks its items when it is built: an item
of the wrong type fails there, not in a search.

| Contract | Registered in | What it does |
|----------|---------------|--------------|
| `Api\Search\SearchConditionInterface` | `conditions` of `Model\Search\ConditionPool` | Serves a key: `activityIds($value)` returns a sub-select of journal ids, or null when the value matches nothing. `isResidual()` says the condition is not indexed, so it is applied among the newest candidates only. A key may have conditions from several modules; an activity matches when it matches any of them. |
| `Api\Search\SearchKeyDescriptionInterface` | `keyDescriptions` of `Model\Search\KeyDescriptionPool` | Presents a key: label, hint, control (`text` or `select`), options, group (`fields`, `records`, `people`, `types`, `other`), sort order, whether the Filters panel has a control for it (`isInPanel()`), a companion text box and option targets for a select, and `matchesFieldNames()` when the value names fields. Declare it as a virtual type of `Model\Search\KeyDescription\KeyDescription`. |
| `Api\Search\SuggestionProviderInterface` | `suggestionProviders` of `Model\Search\SuggestionPool` | Lists the values of one key that occur in the log, at most 200. One bounded query on indexed columns; the provider never caches, because the pool does. Register it under a unique name. |
| `Api\Search\SearchExampleInterface` | `examples` of `Model\Search\ExamplePool` | An example search for the empty log: a title, filters and a range preset. The journal ships none. |
| `Api\Search\EventDetailProviderInterface` | `detailProviders` of `Model\Search\Detail\DetailProviderPool` | Describes the activities of the channels it supports: the verb, what changed, a link to the subject, the changed fields (each saying whether it was set automatically, with their number in `withAutomaticCount()`), and how many fields kept their value (with whether their rows were recorded, so they can be opened). `detailsFor()` also receives the subject names the journal recorded. The first provider that supports a channel serves it. Without one, an activity is shown from the journal alone. |
| `Model\Search\ChangeChannels` | `changeChannels` | The codes of channels that record changes. "Changes only" keeps these channels. |
| `Api\Search\RecentSubjectsInterface` | injected | The subjects changed in the last 30 days as the journal recorded them, for a suggestion provider. |
| `Api\Search\SubjectLookupInterface` | injected | Subjects the journal recorded under a name, for records that were deleted or renamed. |
| `Api\Search\ActivityActorsInterface` | injected | The actor names of a set of activities, for a panel that narrows them by actor. |
| `Api\Search\ActivitySearchInterface` | injected | Runs a search: `search(SearchRequestInterface)` returns a `SearchPageInterface`. |

`Api\Data\Search\*` holds the data types these contracts exchange: `SearchRequestInterface`,
`SearchCriterionInterface`, `SearchPageInterface`, `EventViewInterface`, `EventDetailInterface`,
`FieldChangeInterface`, `SuggestionInterface`, and the enums `Verb`, `ValueKind`, `RangePreset`, `SearchKeyControl` and
`SearchKeyGroup`. Create them through their generated factories.

A condition must follow these rules:
- The sub-select selects one column, the journal id, is not correlated with the outer query, and has no order or limit.
  The search places it as `id IN (…)` and owns the candidate limit.
- Bind every value with `where('… ?', $value)`, escape `LIKE` wildcards with `Magento\Framework\DB\Helper::escapeLikeValue()`,
  and compare varchar id columns with string values so that their index is used.

Example: a module serves the key `invoice` from its own table and describes it.

```xml
<type name="Hryvinskyi\ActivityLog\Model\Search\ConditionPool">
    <arguments>
        <argument name="conditions" xsi:type="array">
            <item name="acme_invoice" xsi:type="object">Acme\Billing\Model\Search\InvoiceCondition</item>
        </argument>
    </arguments>
</type>
<type name="Hryvinskyi\ActivityLog\Model\Search\KeyDescriptionPool">
    <arguments>
        <argument name="keyDescriptions" xsi:type="array">
            <item name="invoice" xsi:type="object">Acme\Billing\Model\Search\InvoiceKey</item>
        </argument>
    </arguments>
</type>
<virtualType name="Acme\Billing\Model\Search\InvoiceKey"
             type="Hryvinskyi\ActivityLog\Model\Search\KeyDescription\KeyDescription">
    <arguments>
        <argument name="key" xsi:type="string">invoice</argument>
        <argument name="label" xsi:type="string" translate="true">Invoice</argument>
        <argument name="hint" xsi:type="string" translate="true">Invoice number</argument>
        <argument name="sortOrder" xsi:type="number">300</argument>
        <argument name="group" xsi:type="string">records</argument>
    </arguments>
</virtualType>
```

```php
class InvoiceCondition implements SearchConditionInterface
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function getKey(): string
    {
        return 'invoice';
    }

    public function isResidual(): bool
    {
        return false;
    }

    public function activityIds(string $value): ?Select
    {
        $connection = $this->resource->getConnection();

        return $connection->select()
            ->from($this->resource->getTableName('acme_invoice_change'), ['activity_id'])
            ->where('invoice_number = ?', $value);
    }
}
```

**Show the timeline on another page.** Add the layout handle `hryvinskyi_activitylog_timeline_assets` to the page, then
`mount(root, options)` the RequireJS module `Hryvinskyi_ActivityLog/js/timeline/timeline`. The mount returns
`show(search)`, `stop()` and `destroy()`. The options are described at the top of `view/adminhtml/web/js/timeline/timeline.js`.

## Known limits
- A denied activity is described as "tried to change" or "tried to delete" only when its channel's module says so;
  otherwise it is shown with its Denied outcome and a generic sentence.
- A search by `text` is matched among the newest 10,000 candidates.

## License
MIT. See [LICENSE](LICENSE).

The admin icons are from [Lucide](https://lucide.dev) (ISC licence; the licence text ships beside them in
`view/adminhtml/web/images/icons/`).
