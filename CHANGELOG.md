# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-10-23

### Added
- `Api\ActorScopeInterface::actAs()`: work done on someone's behalf (a queue consumer carrying out an admin's mass
  action) records its activities for that actor instead of the process. Scopes nest; each ends with its work.
- `Api\UserActorsInterface::actorOf()`: the actor of a Magento account given by user type and id, named as the
  session would name it. The user context resolver now goes through it.
- An operation of a bulk action (an asynchronous web API call, a mass action the admin panel queues) is carried out
  for the admin or integration that scheduled the bulk, not for `cli`.
- `Ui\Component\AclMassAction` is `@api` and takes `aclResources`, a list of resources an admin must hold every
  one of, beside `aclResource`.

### Fixed
- A secret field list changed within one process (a setup patch that merges names into it and reloads the
  configuration) is followed at once; the sanitizer kept the list it read first for the life of the process.
- `i18n/en_US.csv` holds every phrase the module shows (107 were missing).
- The data contracts' docblocks no longer give examples from other modules.

## [1.1.1] - 2026-10-22

### Fixed
- In production mode every activity of a process nobody logged in to was recorded with the actor `cli`:
  a payment provider's webhook, a guest's action, an anonymous REST call. The server API was a constructor
  default, and `setup:di:compile` keeps defaults in the compiled configuration as the command line that
  compiled it saw them. It is now read when an actor is resolved, so such activities are `anonymous` again.
  Activities already recorded keep their actor.

## [1.1.0] - 2026-10-22

### Added
- The search page. **System › Activity Log › All Activity** opens the page "Activity", with a search bar, a time
  range, a Filters panel, "Changes only" and a Timeline or Grid view of the same search.
  - The bar takes filters written as `key: value`. A plain word is a `text` filter. A search holds at most 20
    filters, at most 8 of them `text`, and each value is 1 to 255 characters.
  - The keys of the journal are `by`, `via`, `channel`, `outcome`, `store`, `request` (the correlation id) and
    `text`. `by: "name"`, in double quotes, is that actor exactly. `text` matches each word, at most 8, as a
    substring of the action, the subject, the actor or the correlation id, or of what a module recorded; every word
    must be found, among the newest 10,000 candidates. Past that cap the page shows the partial result and asks you to narrow the
    search.
  - The bar suggests values that occur in the log. The lists are cached for 5 minutes for each admin locale in a new
    cache type, **Activity Log Search** (`hryvinskyi_activity_log_search`). A data patch enables it once.
  - The range is set to the minute, in the store's timezone. The presets are Last hour, Today, Yesterday, Last 7
    days, Last 30 days and Custom. The default is Last 7 days.
  - The Filters panel and the chips stay in sync.
  - "Changes only" is on by default. It is off when a link asks for a request's activities or for a channel that
    records no changes. It is shown only while some installed channel records changes.
  - The choice of Timeline or Grid and "Changes only" are remembered for each admin.
- The timeline: day groups; one sentence for each change, with a "via <channel> <client>" part; values before and
  after, word diffs of long text, added and removed items of JSON lists, and masked values; "N more in this
  request" and the request chip; "Load 50 more" with paging by time and id; and states for an empty log (with example
  searches), for no result (with ways to widen the search) and for a search to narrow.
- The grid columns Field, Before → After and Scope.
- The grid count reads "10,000+" past 10,000 matches. "Select All" is hidden while it does. The CSV export is
  never capped. Mass delete applies to the activities of the search.
- Links: `?<key>=<value>` for any key, and `range=<preset>` or `range=<fromMs>-<toMs>`. The older `?channel=`,
  `?correlation_id=` and `?actor_name=` still work.
- Extension points, all `@api`: search conditions (`Api\Search\SearchConditionInterface`), key descriptions
  (`SearchKeyDescriptionInterface`), suggestion providers (`SuggestionProviderInterface`), examples
  (`SearchExampleInterface`), detail providers (`EventDetailProviderInterface`), change channels
  (`Model\Search\ChangeChannels`), recent subjects (`RecentSubjectsInterface`), subject lookup
  (`SubjectLookupInterface`), activity actors (`ActivityActorsInterface`) and the search itself
  (`ActivitySearchInterface`), with the data types in `Api\Data\Search`. The timeline can be shown on another page
  with the RequireJS module `Hryvinskyi_ActivityLog/js/timeline/timeline` and the layout handle
  `hryvinskyi_activitylog_timeline_assets`.
- On a journal of 1,000,000 rows, every search shape stays under 300 ms (MariaDB 10.6).
- Fields set automatically: `FieldChangeInterface::isAutomatic()` / `withAutomatic()` and
  `EventDetailInterface::getAutomaticCount()` / `withAutomaticCount()` let a detail provider say that the system set a
  changed field by itself while carrying out what the actor asked. The timeline tags such a field "set automatically",
  counts only the fields the actor asked for, and reads a change made only of such fields as "Magento updated …" with
  the actor below; the grid's change columns prefer the fields asked for. The search JSON carries `automatic` on each
  field and `automaticCount` on each detail.

### Changed
- The grid's per-column filters and keyword box are removed; the search bar replaces them. A data patch clears the
  old filters from every saved view once. Each admin's columns, sorting and page size are kept.
- The grid's total is counted up to 10,000 instead of counting every match.

### Fixed
- In the timeline, a before → after value that wraps onto its own line no longer covers the line under it.
- The focus is never lost: picking a calendar day keeps it in the range popover (Esc still closes it), Apply in the
  Filters panel returns it to the Filters button, and Retry or a way out of an empty result moves it to the results.
- Applying the Filters panel replaces only the filters of the controls you changed; two filters of one key stay.
- The grid shows only the latest search: an answer to an earlier one is dropped, every grid request has the page's
  deadline, and a new search clears the selection, so rows chosen for another search never narrow an export.
  "Select All" follows the "10,000+" count as it changes.
- "Widen to last 30 days" is offered only when the range is shorter and the latest match outside it falls inside the
  last 30 days. A list of unchanged fields says when it shows only some of them ("Showing 17 of 60.", with a link to
  the details), and the list of a request's other activities says when it stops at 200.
- Two quick changes of the view or of "Changes only" are saved one after the other, so the last one is kept.
- An activity whose module kept only the number of unchanged fields shows that number, without a link to rows that do
  not exist and without calling it a snapshot. A detail provider says which it is with the second argument of
  `EventDetailInterface::withUnchangedCount()`.
- The hints of the `by` and `store` keys name their exact forms: `by: "name"` for exactly that person, `store: a | b`
  for any of them.
- Admin links are built from the route id, so they work with **Add Secret Key to URLs** on. In 1.0.0, with that
  setting on, the menu entries ("Dashboard", "All Activity", "Admin Requests", "Entity Changes") and the grid's View
  action carried a key that the admin did not accept, and they redirected to the admin start page.

## [1.0.0] - 2026-10-21

### Added
- The journal: an activity has an actor (admin, integration, customer, guest, CLI, cron), a channel, an action, a
  subject, an outcome (`success`, `failure`, `denied`), a duration, a store, an IP address, a user agent and a
  correlation id shared by everything one operation caused.
- Masking of secrets before storage, in arrays and in JSON, XML, form-encoded and plain-text bodies.
- **System › Activity Log**: a dashboard with summary cards and widgets, a grid with CSV export, and a detail page
  for each activity with the rest of its operation.
- Retention for each channel, and a number of days for activities of channels that no installed module registers.
- The contracts channel modules use: `ActivityBuilderInterface`, `ActivityRecorderInterface`, `ChannelInterface`,
  `SanitizerInterface`, `CorrelationScopeInterface`, `DashboardWidgetInterface` and `IconsInterface`.
