# Product Configurator Shopify Preset Export

A WordPress plugin that exports Product Configurator presets to CSV format (Shopify-compatible or raw format) via the admin interface or WP-CLI.

## Features

- Export presets to Shopify-compatible CSV format
- Export presets to raw CSV format (all preset data)
- Filter exports by product, date, author, and more
- Bulk export via admin interface with modal dialog
- Command-line export via WP-CLI
- Progress tracking for large exports
- Range-based exports for processing large datasets

## Requirements

- WordPress 5.0 or higher
- WooCommerce
- Product Configurator for WooCommerce plugin
- PHP 7.4 or higher
- WP-CLI (for command-line exports)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Product Configurator presets to use the export features

## Quick Start

### Admin Interface Export

1. Go to **WP Admin → Product Configurator → Presets**
2. Apply any filters you need (product, date, status, etc.)
3. Click the **"Export to CSV"** button at the top of the page
4. Choose your export options:
   - **Format**: Shopify or Raw
   - **Scope**: Current page, selected presets, or all matching presets
5. Click **"Start Export"**
6. Your CSV file will download automatically

### WP-CLI Export

Run exports from the command line for automation, cron jobs, or large datasets:

```bash
wp mkl-pc presets-export shopify --product_ids=123 --filters="m=202411" --output=export.csv
```

## WP-CLI Usage

### Command Syntax

```bash
wp mkl-pc presets-export [shopify|raw] [--options]
```

### Arguments

- `shopify` - Export in Shopify-compatible format (default)
- `raw` - Export in raw format with all preset data

### Common Options

| Option | Description | Example |
|--------|-------------|---------|
| `--product_ids` | Filter by product ID(s), comma-separated | `--product_ids=123` or `--product_ids="123,456"` |
| `--filters` | URL query string with filters | `--filters="m=202411&post_status=all"` |
| `--output` | Output file path (use `-` for stdout) | `--output=exports/file.csv` |
| `--scope` | Export scope: `all`, `selection`, `page`, `range` | `--scope=all` (default) |
| `--chunk-size` | Presets per batch (default: 500) | `--chunk-size=1000` |
| `--force` | Overwrite existing output file | `--force` |
| `--no-bom` | Skip UTF-8 BOM in output | `--no-bom` |
| `--progress` | Progress display: `log` or `bar` | `--progress=bar` |
| `--no-progress` | Disable progress output | `--no-progress` |

### Advanced Options

| Option | Description | Example |
|--------|-------------|---------|
| `--preset_ids` | Specific preset IDs to export | `--preset_ids="123,456,789"` |
| `--range_start` | Start from specific preset ID | `--range_start=1000` |
| `--range_limit` | Limit presets in range export | `--range_limit=500` |
| `--per_page` | Override pagination limit | `--per_page=100` |
| `--paged` | Start from specific page | `--paged=2` |
| `--variant_size_layer` | Override size layer | `--variant_size_layer="Size"` |
| `--variant_colour_layer` | Override colour layer | `--variant_colour_layer="Colour"` |

## Examples

### Basic Examples

#### Export a specific product from November 2025

```bash
wp mkl-pc presets-export shopify \
  --product_ids=12570 \
  --filters="m=202511" \
  --output=exports/product-12570-nov-2025.csv
```

#### Export multiple products

```bash
wp mkl-pc presets-export shopify \
  --product_ids="123,456,789" \
  --filters="m=202411" \
  --output=exports/multiple-products-nov-2024.csv
```

#### Export all presets from a specific year

```bash
wp mkl-pc presets-export shopify \
  --filters="m=2024" \
  --output=exports/all-2024-presets.csv
```

#### Export with specific status

```bash
wp mkl-pc presets-export shopify \
  --product_ids=123 \
  --filters="m=202411&post_status=preset" \
  --output=export.csv
```

#### Export to stdout (for piping)

```bash
wp mkl-pc presets-export shopify \
  --product_ids=123 \
  --filters="m=202411" > output.csv
```

### Advanced Examples

#### Large dataset with progress bar

```bash
wp mkl-pc presets-export shopify \
  --scope=all \
  --chunk-size=1000 \
  --progress=bar \
  --output=exports/large-export.csv
```

#### Range-based export (start from ID 5000, export 500 presets)

```bash
wp mkl-pc presets-export shopify \
  --range_start=5000 \
  --range_limit=500 \
  --output=exports/range-5000-5500.csv
```

#### Export specific preset IDs

```bash
wp mkl-pc presets-export shopify \
  --preset_ids="100,200,300,400" \
  --output=exports/specific-presets.csv
```

#### Raw format export with force overwrite

```bash
wp mkl-pc presets-export raw \
  --product_ids=123 \
  --filters="m=202411" \
  --output=export-raw.csv \
  --force
```

#### Silent export (no progress output)

```bash
wp mkl-pc presets-export shopify \
  --product_ids=123 \
  --no-progress \
  --output=export.csv
```

## Date Filter Format

The `m` parameter in filters uses WordPress's standard format:

- `m=2024` - All of 2024
- `m=202411` - November 2024  
- `m=20241115` - November 15, 2024

## Converting Admin Filter URLs to WP-CLI Commands

If you have a filter URL from the admin interface, extract these parameters:

**Example URL:**
```
/wp-admin/edit.php?m=202511&mkl_pc_product_id=12570&post_status=all&filter_action=Filter
```

**Convert to WP-CLI:**
```bash
wp mkl-pc presets-export shopify \
  --product_ids=12570 \
  --filters="m=202511&post_status=all" \
  --output=export.csv
```

### Key Parameter Mappings

| Admin URL Parameter | WP-CLI Option | Example |
|---------------------|---------------|---------|
| `mkl_pc_product_id=123` | `--product_ids=123` | Product ID filter |
| `m=202411` | `--filters="m=202411"` | Date filter |
| `post_status=preset` | `--filters="post_status=preset"` | Status filter |
| `s=search+term` | `--filters="s=search term"` | Search filter |

## Output Formats

### Shopify Format

Exports presets in a Shopify-compatible CSV format with columns for:
- Handle
- Title
- Body (HTML)
- Vendor
- Product Type
- Tags
- Published
- Option1 Name/Value
- Option2 Name/Value
- Option3 Name/Value
- Variant SKU
- Variant Grams
- Variant Inventory Tracker
- Variant Inventory Policy
- Variant Fulfillment Service
- Variant Price
- Variant Compare At Price
- Variant Requires Shipping
- Variant Taxable
- Variant Barcode
- Image Src
- Image Position
- Image Alt Text
- Gift Card
- SEO Title
- SEO Description
- Variant Image
- Variant Weight Unit
- Variant Tax Code
- Cost per item
- Status

### Raw Format

Exports all preset data including:
- Preset ID
- Product ID
- Product Name
- Product SKU
- All layer configurations
- Custom fields
- Metadata

## Automation & Cron Jobs

### Daily Export Cron Job

Add to your server's crontab:

```bash
# Export daily at 2 AM
0 2 * * * cd /path/to/wordpress && wp mkl-pc presets-export shopify --filters="m=$(date +\%Y\%m)" --output=/path/to/exports/daily-$(date +\%Y-\%m-\%d).csv
```

### Monthly Export

```bash
# Export on the 1st of each month at 3 AM
0 3 1 * * cd /path/to/wordpress && wp mkl-pc presets-export shopify --filters="m=$(date -d 'last month' +\%Y\%m)" --output=/path/to/exports/monthly-$(date -d 'last month' +\%Y-\%m).csv
```

### Product-Specific Weekly Export

```bash
# Export specific product every Monday at 1 AM
0 1 * * 1 cd /path/to/wordpress && wp mkl-pc presets-export shopify --product_ids=12570 --output=/path/to/exports/product-12570-$(date +\%Y-\%m-\%d).csv
```

## Troubleshooting

### Command Not Found

If you get `Error: 'mkl-pc' is not a registered wp command`:

1. Ensure the plugin is activated
2. Check that you're in the WordPress root directory
3. Verify WP-CLI is working: `wp --info`

### Permission Denied on Output File

Ensure the output directory exists and is writable:

```bash
mkdir -p exports
chmod 755 exports
```

### Memory Issues with Large Exports

For very large exports, use chunking:

```bash
wp mkl-pc presets-export shopify \
  --scope=all \
  --chunk-size=100 \
  --output=export.csv
```

### Empty Export

Check your filters are correct:

```bash
# Test with minimal filters first
wp mkl-pc presets-export shopify --output=test.csv

# Then add filters one by one
wp mkl-pc presets-export shopify --product_ids=123 --output=test.csv
```

## Development

### File Structure

```
product-configurator-shopify-preset-export/
├── product-configurator-shopify-preset-export.php  # Main plugin file
├── assets/
│   └── js/
│       └── admin-export.js                         # Admin interface JS
└── README.md                                        # This file
```

### Hooks & Filters

#### Filters

**`mkl_pc_preset_export_csv_headers`**
Modify CSV headers before export:

```php
add_filter('mkl_pc_preset_export_csv_headers', function($headers) {
    $headers[] = 'Custom Column';
    return $headers;
});
```

**`mkl_pc_preset_export_query_args`**
Modify WP_Query arguments before fetching presets:

```php
add_filter('mkl_pc_preset_export_query_args', function($args, $filters, $selected_ids, $scope) {
    // Add custom query modifications
    return $args;
}, 10, 4);
```

**`mkl_pc_preset_export_handle`**
Modify the export handle/slug:

```php
add_filter('mkl_pc_preset_export_handle', function($handle, $product) {
    return 'custom-' . $handle;
}, 10, 2);
```

## Support

For issues, bugs, or feature requests, please contact the development team or create an issue in the project repository.

## License

This plugin is proprietary software. All rights reserved.

## Changelog

### Version 1.0
- Initial release
- Shopify and raw CSV export formats
- Admin interface with modal export dialog
- WP-CLI command support
- Bulk export and filtering capabilities
- Range-based exports for large datasets

