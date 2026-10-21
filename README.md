# Magento 2 Activity Log

One journal of who did what in the store back office, through which channel and with what outcome. The module ships
the journal, its dashboard, grid and detail page. Channel modules record into it and extend those pages.

## What it does
- Records an **activity** per action: actor (admin, integration, customer, guest, CLI, cron), channel, action,
  subject, outcome (`success`, `failure`, `denied`), duration, store, IP, user agent and a correlation id shared by
  everything one operation caused.
- Masks secrets before anything is stored: passwords, tokens, keys, cookies and authorization headers, in arrays and
  in JSON, XML, form-encoded and plain-text bodies.
- Shows the journal in **System › Activity Log**: a dashboard with summary cards and widgets, a filterable grid with
  CSV export, and a detail page per activity with the rest of its operation.
- Deletes old activities by the retention each channel sets, and activities of channels no installed module registers
  after a configurable number of days.

The module records nothing by itself. Channel modules do: for example
[hryvinskyi/magento2-api-logger](https://github.com/hryvinskyi/magento2-api-logger) records REST API calls.

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

## For channel modules
A channel module depends on this package's `Api/` contracts only:
- `ActivityBuilderInterface` and `ActivityRecorderInterface` build and record an activity. `record()` returns null when
  the journal is off or the write fails; it never throws into the operation being recorded.
- `ChannelInterface` is registered in `ChannelPool`'s `channels` argument (code, label, colours, retention).
- `SanitizerInterface` masks values (`sanitize()`) and message bodies of any format (`sanitizeBody()`).
- `CorrelationScopeInterface` groups the activities of one operation.
- `DashboardWidgetInterface` adds a widget to the dashboard's `WidgetPool`.
- The detail page adds the layout handle `hryvinskyi_activitylog_activity_view_channel_<code>`; a channel puts its
  panel into the `activity.channel.panel` container.
- `IconsInterface` renders a module's SVG icons inline in admin templates.

## License
MIT. See [LICENSE](LICENSE).

The admin icons are from [Lucide](https://lucide.dev) (ISC licence; the licence text ships beside them in
`view/adminhtml/web/images/icons/`).
