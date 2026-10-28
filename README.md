# TYPO3 Content Audit Dashboard Widget `xima_typo3_content_audit`

[![Latest Stable Version](https://typo3-badges.dev/badge/xima_typo3_content_audit/version/shields.svg)](https://packagist.org/packages/xima/xima-typo3-content-audit)
[![Supported TYPO3 versions](https://badgen.net/badge/TYPO3/12%20&%2013%20&%2014/orange)](https://extensions.typo3.org/extension/xima_typo3_content_audit)
[![Total Downloads](https://typo3-badges.dev/badge/xima_typo3_content_audit/downloads/shields.svg)](https://extensions.typo3.org/extension/xima_typo3_content_audit)

A widget for the TYPO3 dashboard to evaluate the relevance, accuracy and freshness of your digital content

![Screenshot](./Documentation/screenshot.png)

## Features

Available widgets:

- Stale Pages: Displays a list of pages that have not been changed for a long time
  - Integrators may exclude certain pages from the list, e.g. start page or landing pages
- Fresh Pages: Displays a list of pages that have been changed recently
- Long Hidden Pages: Display a list of pages that have been hidden for a long time and may no longer be relevant
- Long-hidden Content: Display a list of content that has been hidden for a long time and may no longer be relevant
- Empty Pages: Display a list of pages that have no visible content elements
  - Integrators may configure which page types (doktype) to check and exclude certain pages
- Untranslated Pages: Display pages that only exist in the default language
- Pages with missing fields: Lists pages that are missing a configurable field, e.g. a teaser abstract or teaser image
  - Works with plain text fields as well as image/file fields
- Images with missing fields: Lists images that are missing important metadata such as alternative text
  - The field may be configured, e.g. to check for missing copyright information instead of alternative text
- Broken Links: Display pages with broken internal links detected by the TYPO3 linkvalidator
  - **Note:** Requires the `typo3/cms-linkvalidator` extension to be installed and configured
- Recent Changes: Displays a list of pages and content elements that have been created or
  updated recently, including who made the change
- Daily Content Mission: Shows daily motivational messages to encourage editors to improve content quality
  - Messages rotate by day of week and reference specific widgets to check
- Content Statistics: Shows key facts about the site's content, e.g. number of pages, content elements,
  users and files - useful as a quick overview or for relaunch planning

- Most widgets provide configurations to exclude false positives, e.g. remove the privacy page from stale pages
- Most widgets provide a link to show matching pages directly in the backend pagetree,
  to make it easier for editors to find and edit the affected pages (TYPO3 >= 14 only)

🎙👉️ You can test all widgets in the included [demo setup with DDEV](#development).

> ✨ Would you like to share important news and announcements with your editorial team
> or add a general welcome message for editors on the dashboard? Check out our
> [dashboard news extension](https://github.com/xima-media/xima-typo3-dashboard-news).

## Screenshots

<details>
<summary>Show screenshot: Dashboard with all available widgets</summary>

![Dashboard with all widgets](./Documentation/screenshot-dashboard.png)

</details>

<details>
<summary>Show screenshot: Page tree filtered to show all stale pages</summary>

![Page tree filtered to all stale pages](./Documentation/screenshot-pagetree.png)

</details>

## Requirements

- PHP
- TYPO3

## Installation

``` bash
composer require xima/xima-typo3-content-audit
```

## Source

https://github.com/xima-media/xima-typo3-content-audit

## Usage

- Add widgets to your dashboard manually
- Check the result lists and decide what to do with the shown pages - update the content,
  archive or delete the page
- Use the link below each widget to show matching pages directly in the backend pagetree

## Configuration

TYPO3 hides dashboard widgets from editors unless allowed for their backend group,
even if they are part of a preset. Open the backend group record,
tab »Access Lists«, field »Available Widgets«, and enable them there.

This also lets you decide which widgets to use per project,
e.g. skip the »Untranslated Pages« widget on a single-language site.

### Dashboard Preset

The extension provides a preset which includes all widgets at once,
without the need to add them manually to your dashboard one by one.
When you add a new dashboard, just select the preset `Content Audit`.

Integrators may also set this preset as default dashboard for new users via TSconfig,
using this configuration:

```
options.dashboard.dashboardPresetsForNewUsers = tx_ximatypo3contentaudit_dashboard
```

### Widget Configuration

You may configure the widgets by adjusting the available parameters.
Copy and paste the `parameters` section from the
[Services.yaml](./Configuration/Services.yaml) file into your own
and adjust the values as needed.

To exclude certain pages from the stale pages widget,
you may add this to your sitepackage:

_EXT:acme_sitepackage/Configuration/Services.yaml_
```yaml
parameters:
  xima_typo3_content_audit.widgets.stale_pages.options:
    refreshAvailable: true
    excludePageUids: [4,8,15,16,23,42]
```

### Adding Multiple Widgets of the Same Type

TYPO3 does not let editors add the same widget twice to a dashboard.
To show multiple variants of the same widget with different configurations,
register multiple service definitions with distinct key names and `identifier` fields.
No custom data provider classes needed, config only.

Typical use case: add a second »Missing Image Fields« widget
that checks for missing copyright info instead of alternative texts.
[Copy and rename the parameter section & widget definition](./Configuration/Services.yaml)
into your sitepackage (e.g. `acme_sitepackage`) like this:

_EXT:acme_sitepackage/Configuration/Services.yaml_
```yaml
parameters:
  acme_sitepackage.widgets.missing_image_fields.options: # add your own parameter key, see below
    refreshAvailable: true
    missingField: 'copyright'

  Acme\AcmeSitepackage\Widgets\MissingImageFields: # change this key to avoid conflicts
    class: 'Xima\XimaTypo3ContentAudit\Widgets\MissingImageFields'
    arguments:
      $backendViewFactory: '@TYPO3\CMS\Backend\View\BackendViewFactory'
      $dataProvider: '@Xima\XimaTypo3ContentAudit\Widgets\Provider\MissingImageFieldsDataProvider'
      $options: '%acme_sitepackage.widgets.missing_image_fields.options%' # use your own parameters here, see above
    tags:
      - name: dashboard.widget
        identifier: 'acmeSitepackageMissingImageFields' # change this identifier to avoid conflicts
        groupNames: 'content'
        title: 'LLL:EXT:xima_wfs_sitepackage/Resources/Private/Language/locallang.xlf:widgets.missing_image_fields.title' # optional: use your own language label here
        description: 'LLL:EXT:xima_typo3_content_audit/Resources/Private/Language/locallang.xlf:widgets.missing_image_fields.description'
        iconIdentifier: 'content-audit-widgets-hidden-content'
        height: 'medium'
        width: 'medium'
```

Same trick works for the »Missing Page Fields« widget, for example to catch pages
missing an Open Graph image (`og_image`) if social media previews matter for your site.
Just copy the definition, change the identifier and use `missingField: 'og_image'` instead
of `abstract`. The widget detects on its own that this is an image field and not a text field.

## Development

To set up a development environment, please clone the repository and run

```bash
ddev start && ddev init-typo3
```

Execute the command again to reset the installation.

## License

GNU General Public License version 2 or later

The GNU General Public License can be found at http://www.gnu.org/copyleft/gpl.html.

## Author

Dan Kleine ([@pixelbrackets](https://pixelbrackets.de)) for [XIMA](https://www.xima.de/)

## Changelog

[CHANGELOG.md](CHANGELOG.md)
