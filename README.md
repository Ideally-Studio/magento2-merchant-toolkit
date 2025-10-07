# Ideally Studio Merchant Toolkit

Magento 2 admin enhancements that make it easier for merchandisers to preview storefront entities per store view.

## Features

- **Product View Button**: Adds a `View on Store` button on the product edit form and a `View` action column in the product grid. Both handle single and multi-store assignments, opening the appropriate storefront URL in a new tab.
- **Category View Button**: Adds a `View on Store` button to the category edit form, ensuring the generated URL matches the store view assigned to the category.
- **CMS Page View Button**: Adds a `View Page` button to the CMS page editor. When a page is assigned to multiple store views (or to all store views), the button becomes a dropdown with one entry per store view.

## Installation

```bash
composer require ideallystudio/module-merchant-toolkit
bin/magento setup:upgrade
```

If you deploy static content, re-run:

```bash
bin/magento setup:static-content:deploy -f
```

## Requirements

- PHP 8.1 or later
- Magento Open Source/Commerce 2.4.x (framework 103.x)

## Support

Open an issue in your project tracker or reach out to the Ideally Studio engineering team.
