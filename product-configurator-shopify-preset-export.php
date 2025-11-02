<?php
/**
 * Plugin Name: Product Configurator - Shopify Preset Export
 * Description: Export MKL Product Configurator presets to a Shopify-compatible CSV directly from the presets admin list.
 * Version:     0.1.0
 * Author:      Happy Webs Limited
 * Author URI:  https://happywebs.co.uk
 * Text Domain: mkl-pc-shopify-export
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('MKL_PC_Preset_Shopify_Export')) {
    final class MKL_PC_Preset_Shopify_Export
    {
        private const ACTION_SLUG      = 'mkl_pc_export_presets_csv';
        private const RAW_ACTION_SLUG  = 'mkl_pc_export_presets_raw_csv';
        private const NONCE_ACTION     = 'mkl_pc_export_presets_csv';
        private const CAPABILITY       = 'manage_woocommerce';
        private const VERSION          = '0.1.0';
        private const DEFAULT_SHOPIFY_CATEGORY = 'Hardware > Hardware Accessories > Tool Storage & Organization > Work Benches > Stationary Work Benches';

        /**
         * CSV header order based on Shopify's product_template.csv.
         */
        private const CONFIGURATION_PRICE_HEADER = 'Configuration Total Price';

        private const CSV_HEADERS = [
            'Handle',
            'Title',
            'Body (HTML)',
            'Vendor',
            'Product Category',
            'Type',
            'Tags',
            'Published',
            'Option1 Name',
            'Option1 Value',
            'Option1 Linked To',
            'Option2 Name',
            'Option2 Value',
            'Option2 Linked To',
            'Option3 Name',
            'Option3 Value',
            'Option3 Linked To',
            'Variant SKU',
            'Variant Grams',
            'Variant Inventory Tracker',
            'Variant Inventory Qty',
            'Variant Inventory Policy',
            'Variant Fulfillment Service',
            'Variant Price',
            'Variant Compare At Price',
            'Variant Requires Shipping',
            'Variant Taxable',
            'Unit Price Total Measure',
            'Unit Price Total Measure Unit',
            'Unit Price Base Measure',
            'Unit Price Base Measure Unit',
            'Variant Barcode',
            'Image Src',
            'Image Position',
            'Image Alt Text',
            'Gift Card',
            'SEO Title',
            'SEO Description',
            'Google Shopping / Google Product Category',
            'Google Shopping / Gender',
            'Google Shopping / Age Group',
            'Google Shopping / MPN',
            'Google Shopping / Condition',
            'Google Shopping / Custom Product',
            'Google Shopping / Custom Label 0',
            'Google Shopping / Custom Label 1',
            'Google Shopping / Custom Label 2',
            'Google Shopping / Custom Label 3',
            'Google Shopping / Custom Label 4',
            'Assembly Required (product.metafields.custom.assembly_required)',
            'Frame colours (product.metafields.custom.frame_colour)',
            'Google: Custom Product (product.metafields.mm-google-shopping.custom_product)',
            'Product rating count (product.metafields.reviews.rating_count)',
            'Color (product.metafields.shopify.color-pattern)',
            'Features (product.metafields.shopify.features)',
            'Frame material (product.metafields.shopify.frame-material)',
            'Furniture/Fixture material (product.metafields.shopify.furniture-fixture-material)',
            'Hardware material (product.metafields.shopify.hardware-material)',
            'Load capacity (product.metafields.shopify.load-capacity)',
            'Lock type (product.metafields.shopify.lock-type)',
            'Material (product.metafields.shopify.material)',
            'Power source (product.metafields.shopify.power-source)',
            'Shelf material (product.metafields.shopify.shelf-material)',
            'Top surface material (product.metafields.shopify.top-surface-material)',
            'Complementary products (product.metafields.shopify--discovery--product_recommendation.complementary_products)',
            'Related products (product.metafields.shopify--discovery--product_recommendation.related_products)',
            'Related products settings (product.metafields.shopify--discovery--product_recommendation.related_products_display)',
            'Search product boosts (product.metafields.shopify--discovery--product_search_boost.queries)',
            'Variant Image',
            'Variant Weight Unit',
            'Variant Tax Code',
            'Cost per item',
            'Status',
            self::CONFIGURATION_PRICE_HEADER,
        ];

        private const RAW_CSV_HEADERS = [
            'Preset ID',
            'Preset Title',
            'Preset Slug',
            'Preset Status',
            'Preset Author ID',
            'Preset Author Name',
            'Preset Date',
            'Preset Modified',
            'Preset Permalink',
            'Product ID',
            'Product Title',
            'Product Slug',
            'Product Status',
            'Product Type',
            'Product SKU',
            'Product Price',
            'Product Regular Price',
            'Product Categories',
            'Product Tags',
            'Product Permalink',
            'Product Featured Image',
            'Variant Price',
            'Variant Price (Raw)',
            'Base Price Component',
            'Extra Price Total',
            'Extra Price Raw',
            'Extra Price Overrides Product Price',
            'Variant Requires Shipping',
            'Variant Taxable',
            'Variant Weight Grams',
            'Variant Weight Unit',
            'Variant SKU',
            'Variant Key',
            'Option1 Label',
            'Option1 Value',
            'Option1 Slug',
            'Option2 Label',
            'Option2 Value',
            'Option2 Slug',
            'Other Options JSON',
            'Option Entries JSON',
            'Configuration JSON',
            'Preset Meta JSON',
            'Variant Image',
        ];

        /** @var MKL_PC_Preset_Shopify_Export|null */
        private static $instance;

        /** @var array<int,\Mkl_PC_Preset_Configuration|null> */
        private $configuration_cache = [];

        /**
         * Optional admin overrides for identifying variant layers.
         *
         * @var array<string,array{raw:string,normalized:string,slug:string}>
         */
        private $variant_layer_overrides = [
            'size'   => ['raw' => '', 'normalized' => '', 'slug' => ''],
            'colour' => ['raw' => '', 'normalized' => '', 'slug' => ''],
        ];

        /**
         * Reset variant layer overrides to their defaults.
         */
        private function reset_variant_layer_overrides(): void
        {
            $this->variant_layer_overrides = [
                'size'   => ['raw' => '', 'normalized' => '', 'slug' => ''],
                'colour' => ['raw' => '', 'normalized' => '', 'slug' => ''],
            ];
        }

        /**
         * Singleton bootstrapping.
         */
        public static function instance(): MKL_PC_Preset_Shopify_Export
        {
            if (! self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function __construct()
        {
            if (! is_admin()) {
                return;
            }

            add_action('manage_posts_extra_tablenav', [$this, 'render_export_button'], 20, 1);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('admin_post_' . self::ACTION_SLUG, [$this, 'handle_export']);
            add_action('admin_post_nopriv_' . self::ACTION_SLUG, [$this, 'deny_non_privileged']);
            add_action('admin_post_' . self::RAW_ACTION_SLUG, [$this, 'handle_raw_export']);
            add_action('admin_post_nopriv_' . self::RAW_ACTION_SLUG, [$this, 'deny_non_privileged']);
            add_action('admin_footer', [$this, 'render_export_modal_template']);
        }

        /**
         * Add a button to the presets list view (top tablenav) to trigger the CSV export.
         *
         * @param string $which Tablenav location identifier ('top' or 'bottom').
         */
        public function render_export_button(string $which): void
        {
            if ('top' !== $which) {
                return;
            }

            $screen = get_current_screen();

            if (! $screen || 'edit-mkl_pc_configuration' !== $screen->id) {
                return;
            }

            $query_payload = [];

            if (! empty($_SERVER['QUERY_STRING'])) {
                $raw_query = wp_unslash((string) $_SERVER['QUERY_STRING']);
                parse_str($raw_query, $query_payload);

                unset(
                    $query_payload['action'],
                    $query_payload['action2'],
                    $query_payload['_wpnonce'],
                    $query_payload['ids'],
                    $query_payload['paged']
                );
            }

            $source_query = '';
            if (! empty($query_payload)) {
                $query_string = http_build_query($query_payload);
                $source_query = base64_encode($query_string);
            }

            $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

            $per_page = (int) get_user_option('edit_mkl_pc_configuration_per_page', get_current_user_id());
            if ($per_page <= 0) {
                $per_page = (int) apply_filters('edit_posts_per_page', 20, 'mkl_pc_configuration');
                if ($per_page <= 0) {
                    $per_page = 20;
                }
            }

            $nonce      = wp_create_nonce(self::NONCE_ACTION);
            $action_url = admin_url('admin-post.php');

            echo '<div class="mkl-preset-export-controls" style="display:inline-flex;align-items:center;gap:10px;margin-left:8px;">';
            echo '<button type="button" class="button button-primary mkl-preset-export-modal-trigger"';
            echo ' data-export-url="' . esc_url($action_url) . '"';
            echo ' data-nonce="' . esc_attr($nonce) . '"';
            echo ' data-source-query="' . esc_attr($source_query) . '"';
            echo ' data-paged="' . esc_attr($paged) . '"';
            echo ' data-per-page="' . esc_attr($per_page) . '"';
            echo ' data-default-scope="page"';
            echo ' data-default-action="' . esc_attr(self::ACTION_SLUG) . '"';
            echo ' data-default-format="shopify"';
            echo '>';
            echo esc_html__('Export presets…', 'mkl-pc-shopify-export');
            echo '</button>';
            echo '</div>';
        }

        /**
         * Handle the CSV export request.
         */
        public function enqueue_admin_assets(string $hook_suffix): void
        {
            if ('edit.php' !== $hook_suffix) {
                return;
            }

            $screen = get_current_screen();
            if (! $screen || 'edit-mkl_pc_configuration' !== $screen->id) {
                return;
            }

            wp_enqueue_script(
                'mkl-pc-shopify-export-admin',
                plugins_url('assets/js/admin-export.js', __FILE__),
                [],
                self::VERSION,
                true
            );
        }

        /**
         * Output the modal template used for configuring exports.
         */
        public function render_export_modal_template(): void
        {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;

            if (! $screen || 'edit-mkl_pc_configuration' !== $screen->id) {
                return;
            }

            static $printed = false;

            if ($printed) {
                return;
            }

            $printed = true;

            ?>
            <div id="mkl-preset-export-modal" class="mkl-preset-export-modal" hidden aria-hidden="true">
                <div class="mkl-preset-export-modal__overlay" data-role="close" aria-hidden="true"></div>
                <div class="mkl-preset-export-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mkl-preset-export-modal-title" tabindex="-1">
                    <div class="mkl-preset-export-modal__header">
                        <h2 id="mkl-preset-export-modal-title"><?php echo esc_html__('Export presets', 'mkl-pc-shopify-export'); ?></h2>
                        <button type="button" class="mkl-preset-export-modal__close" data-role="close" aria-label="<?php echo esc_attr__('Close export dialog', 'mkl-pc-shopify-export'); ?>">×</button>
                    </div>
                    <div class="mkl-preset-export-modal__body">
                        <p class="mkl-preset-export-modal__intro">
                            <?php echo esc_html__('Exports always honour the filters currently applied to this list (search, dates, products, etc.). Choose your format and scope, then click Export.', 'mkl-pc-shopify-export'); ?>
                        </p>
                        <label class="mkl-preset-export-modal__field">
                            <span class="mkl-preset-export-modal__label"><?php echo esc_html__('Export format', 'mkl-pc-shopify-export'); ?></span>
                            <select class="mkl-preset-export-modal__select" data-export-format>
                                <option value="shopify" data-action="<?php echo esc_attr(self::ACTION_SLUG); ?>">
                                    <?php echo esc_html__('Shopify CSV (grouped variants)', 'mkl-pc-shopify-export'); ?>
                                </option>
                                <option value="raw" data-action="<?php echo esc_attr(self::RAW_ACTION_SLUG); ?>">
                                    <?php echo esc_html__('Raw presets CSV (detailed)', 'mkl-pc-shopify-export'); ?>
                                </option>
                            </select>
                        </label>
                        <fieldset class="mkl-preset-export-modal__fieldset">
                            <legend><?php echo esc_html__('Export scope', 'mkl-pc-shopify-export'); ?></legend>
                            <label class="mkl-preset-export-modal__checkbox">
                                <input type="radio" name="mkl-preset-export-scope" value="page" checked="checked">
                                <?php echo esc_html__('Current page (honours filters)', 'mkl-pc-shopify-export'); ?>
                            </label>
                            <label class="mkl-preset-export-modal__checkbox">
                                <input type="radio" name="mkl-preset-export-scope" value="selection">
                                <?php echo esc_html__('Only selected presets', 'mkl-pc-shopify-export'); ?>
                            </label>
                            <label class="mkl-preset-export-modal__checkbox">
                                <input type="radio" name="mkl-preset-export-scope" value="all">
                                <?php echo esc_html__('Entire filtered list (may be slow)', 'mkl-pc-shopify-export'); ?>
                            </label>
                        </fieldset>
                        <div class="mkl-preset-export-modal__grid">
                            <label class="mkl-preset-export-modal__field">
                                <span class="mkl-preset-export-modal__label"><?php echo esc_html__('Filter by product ID (optional)', 'mkl-pc-shopify-export'); ?></span>
                                <input type="number" min="1" step="1" class="mkl-preset-export-modal__input" data-export-field="variant_product_id" placeholder="<?php echo esc_attr__('e.g. 12570', 'mkl-pc-shopify-export'); ?>">
                            </label>
                            <label class="mkl-preset-export-modal__field">
                                <span class="mkl-preset-export-modal__label"><?php echo esc_html__('Variant layer for Option 1 (size)', 'mkl-pc-shopify-export'); ?></span>
                                <input type="text" class="mkl-preset-export-modal__input" data-export-field="variant_size_layer" placeholder="<?php echo esc_attr__('Auto detect (e.g. Size)', 'mkl-pc-shopify-export'); ?>">
                            </label>
                            <label class="mkl-preset-export-modal__field">
                                <span class="mkl-preset-export-modal__label"><?php echo esc_html__('Variant layer for Option 2 (colour)', 'mkl-pc-shopify-export'); ?></span>
                                <input type="text" class="mkl-preset-export-modal__input" data-export-field="variant_colour_layer" placeholder="<?php echo esc_attr__('Auto detect (e.g. Colour)', 'mkl-pc-shopify-export'); ?>">
                            </label>
                        </div>
                    </div>
                    <div class="mkl-preset-export-modal__footer">
                        <button type="button" class="button button-secondary" data-role="close"><?php echo esc_html__('Cancel', 'mkl-pc-shopify-export'); ?></button>
                        <button type="button" class="button button-primary" data-role="submit"><?php echo esc_html__('Export', 'mkl-pc-shopify-export'); ?></button>
                        <span class="spinner mkl-preset-export-spinner" style="visibility:hidden;"></span>
                    </div>
                </div>
            </div>
            <style id="mkl-preset-export-modal-styles">
                .mkl-preset-export-modal[hidden] { display: none !important; }
                .mkl-preset-export-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .mkl-preset-export-modal__overlay {
                    position: absolute;
                    inset: 0;
                    background: rgba(15, 23, 42, 0.55);
                }
                .mkl-preset-export-modal__dialog {
                    position: relative;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.45);
                    width: 520px;
                    max-width: calc(100% - 32px);
                    max-height: calc(100% - 40px);
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }
                .mkl-preset-export-modal__header {
                    padding: 16px 20px;
                    border-bottom: 1px solid #e2e8f0;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                }
                .mkl-preset-export-modal__header h2 {
                    margin: 0;
                    font-size: 18px;
                    line-height: 1.4;
                }
                .mkl-preset-export-modal__close {
                    border: none;
                    background: transparent;
                    font-size: 22px;
                    line-height: 1;
                    cursor: pointer;
                    color: #475569;
                }
                .mkl-preset-export-modal__body {
                    padding: 20px;
                    overflow-y: auto;
                }
                .mkl-preset-export-modal__intro {
                    margin: 0 0 16px;
                    color: #475569;
                    font-size: 13px;
                }
                .mkl-preset-export-modal__field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    margin-bottom: 12px;
                }
                .mkl-preset-export-modal__label {
                    font-weight: 600;
                    font-size: 13px;
                    color: #1e293b;
                }
                .mkl-preset-export-modal__select,
                .mkl-preset-export-modal__input {
                    width: 100%;
                    padding: 6px 8px;
                }
                .mkl-preset-export-modal__fieldset {
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 12px 14px;
                    margin: 0 0 16px;
                }
                .mkl-preset-export-modal__fieldset legend {
                    font-weight: 600;
                    font-size: 13px;
                    color: #1e293b;
                }
                .mkl-preset-export-modal__checkbox {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-weight: 400;
                    font-size: 13px;
                    margin-top: 8px;
                }
                .mkl-preset-export-modal__grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 12px 16px;
                }
                .mkl-preset-export-modal__footer {
                    padding: 16px 20px;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 12px;
                }
                body.mkl-preset-export-modal-open {
                    overflow: hidden;
                }
            </style>
            <?php
        }

        /**
         * Parse common request parameters for any preset export.
         *
         * @return array{
         *     scope:string,
         *     per_page:int,
         *     paged:int,
         *     filters:array,
         *     selected_ids:array<int>,
         *     export_all:bool,
         *     product_override_id:int,
         *     page_ids:array<int>
         * }
         */
        private function parse_export_request(): array
        {
            $raw_scope = 'page';
            if (isset($_POST['scope'])) {
                $raw_scope = sanitize_key(wp_unslash($_POST['scope']));
            } elseif (isset($_GET['scope'])) {
                $raw_scope = sanitize_key(wp_unslash($_GET['scope']));
            }

            $per_page = 20;
            if (isset($_POST['per_page'])) {
                $per_page = (int) wp_unslash($_POST['per_page']);
            } elseif (isset($_GET['per_page'])) {
                $per_page = (int) wp_unslash($_GET['per_page']);
            }
            if ($per_page <= 0) {
                $per_page = 20;
            }

            $paged = 1;
            if (isset($_POST['paged'])) {
                $paged = max(1, (int) wp_unslash($_POST['paged']));
            } elseif (isset($_GET['paged'])) {
                $paged = max(1, (int) wp_unslash($_GET['paged']));
            }

            $raw_source_query = '';
            if (isset($_POST['source_query'])) {
                $raw_source_query = wp_unslash((string) $_POST['source_query']);
            } elseif (isset($_GET['source_query'])) {
                $raw_source_query = wp_unslash((string) $_GET['source_query']);
            }

            $source_filters = [];
            if ('' !== $raw_source_query) {
                $decoded = base64_decode($raw_source_query);
                if (false !== $decoded && '' !== $decoded) {
                    parse_str($decoded, $source_filters);
                }
            }

            $product_override_id = 0;
            if (isset($_POST['variant_product_id'])) {
                $product_override_id = absint(wp_unslash((string) $_POST['variant_product_id']));
            } elseif (isset($_GET['variant_product_id'])) {
                $product_override_id = absint(wp_unslash((string) $_GET['variant_product_id']));
            }

            if ($product_override_id > 0) {
                $source_filters['post_parent'] = $product_override_id;
            }

            $size_layer_override = '';
            if (isset($_POST['variant_size_layer'])) {
                $size_layer_override = (string) wp_unslash($_POST['variant_size_layer']);
            } elseif (isset($_GET['variant_size_layer'])) {
                $size_layer_override = (string) wp_unslash($_GET['variant_size_layer']);
            }

            $colour_layer_override = '';
            if (isset($_POST['variant_colour_layer'])) {
                $colour_layer_override = (string) wp_unslash($_POST['variant_colour_layer']);
            } elseif (isset($_GET['variant_colour_layer'])) {
                $colour_layer_override = (string) wp_unslash($_GET['variant_colour_layer']);
            }

            $this->reset_variant_layer_overrides();
            $this->set_variant_layer_override('size', $size_layer_override);
            $this->set_variant_layer_override('colour', $colour_layer_override);

            $raw_selected_ids = '';
            if (isset($_POST['preset_ids'])) {
                $raw_selected_ids = wp_unslash((string) $_POST['preset_ids']);
            } elseif (isset($_GET['preset_ids'])) {
                $raw_selected_ids = wp_unslash((string) $_GET['preset_ids']);
            }

            $selected_ids = $this->parse_id_list($raw_selected_ids);

            $raw_page_scope_ids = '';
            if (isset($_POST['page_scope_ids'])) {
                $raw_page_scope_ids = wp_unslash((string) $_POST['page_scope_ids']);
            } elseif (isset($_GET['page_scope_ids'])) {
                $raw_page_scope_ids = wp_unslash((string) $_GET['page_scope_ids']);
            }

            $page_scope_ids = $this->parse_id_list($raw_page_scope_ids);

            $export_all = false;
            if (isset($_POST['export_all'])) {
                $export_all = (bool) (int) wp_unslash($_POST['export_all']);
            } elseif (isset($_GET['export_all'])) {
                $export_all = (bool) (int) wp_unslash($_GET['export_all']);
            }

            $allowed_scopes = ['selection', 'page', 'all'];
            if (! in_array($raw_scope, $allowed_scopes, true)) {
                $raw_scope = 'page';
            }

            if ($export_all) {
                $raw_scope = 'all';
            } elseif (! empty($selected_ids)) {
                $raw_scope = 'selection';
            } elseif ('selection' === $raw_scope) {
                $raw_scope = 'page';
            }

            if ('selection' === $raw_scope && empty($selected_ids)) {
                $raw_scope = 'page';
            }

            return [
                'scope'               => $raw_scope,
                'per_page'            => $per_page,
                'paged'               => $paged,
                'filters'             => $source_filters,
                'selected_ids'        => $selected_ids,
                'export_all'          => $export_all,
                'product_override_id' => $product_override_id,
                'page_ids'            => $page_scope_ids,
            ];
        }

        /**
         * Handle the CSV export request.
         */
        public function handle_export(): void
        {
            if (! current_user_can(self::CAPABILITY)) {
                wp_die(
                    esc_html__('You do not have permission to export presets.', 'mkl-pc-shopify-export'),
                    esc_html__('Forbidden', 'mkl-pc-shopify-export'),
                    ['response' => 403]
                );
            }

            check_admin_referer(self::NONCE_ACTION);

            if (! function_exists('wc_get_product')) {
                wp_die(
                    esc_html__('WooCommerce is required for this export.', 'mkl-pc-shopify-export'),
                    esc_html__('Missing dependency', 'mkl-pc-shopify-export'),
                    ['response' => 500]
                );
            }

            $context      = $this->parse_export_request();
            $scope        = $context['scope'];
            $per_page     = $context['per_page'];
            $paged        = $context['paged'];
            $filters      = $context['filters'];
            $selected_ids = $context['selected_ids'];
            $page_ids     = $context['page_ids'];

            $query_args = $this->build_query_args($filters, $selected_ids, $scope, $per_page, $paged, $page_ids);
            $presets    = $this->fetch_presets($query_args);

            if (empty($presets)) {
                $this->send_no_presets_response();
            }

            $this->prime_preset_caches($presets);

            $grouped = $this->group_presets_by_product($presets);

            if (empty($grouped)) {
                $this->send_no_presets_response();
            }

            if ('all' === $scope) {
                $headers = apply_filters('mkl_pc_preset_export_csv_headers', self::CSV_HEADERS);
                $output  = $this->open_csv_stream($headers, 'preset-export');

                $this->process_shopify_groups($grouped, function (array $row) use ($output, $headers) {
                    $filtered_rows = apply_filters('mkl_pc_preset_export_rows', [$row]);
                    foreach ($filtered_rows as $filtered_row) {
                        $this->write_csv_row($output, $headers, $filtered_row);
                    }
                });

                fclose($output);
                exit;
            }

            $rows = $this->process_shopify_groups($grouped);
            if (empty($rows)) {
                $this->send_no_presets_response();
            }

            /**
             * Allow final modification of the CSV rows before streaming.
             *
             * @param array $rows Array of row arrays keyed by column header.
             */
            $rows = apply_filters('mkl_pc_preset_export_rows', $rows);

            $this->stream_csv($rows);
        }

        /**
         * Handle the raw presets CSV export request.
         */
        public function handle_raw_export(): void
        {
            if (! current_user_can(self::CAPABILITY)) {
                wp_die(
                    esc_html__('You do not have permission to export presets.', 'mkl-pc-shopify-export'),
                    esc_html__('Forbidden', 'mkl-pc-shopify-export'),
                    ['response' => 403]
                );
            }

            check_admin_referer(self::NONCE_ACTION);

            if (! function_exists('wc_get_product')) {
                wp_die(
                    esc_html__('WooCommerce is required for this export.', 'mkl-pc-shopify-export'),
                    esc_html__('Missing dependency', 'mkl-pc-shopify-export'),
                    ['response' => 500]
                );
            }

            $context      = $this->parse_export_request();
            $scope        = $context['scope'];
            $per_page     = $context['per_page'];
            $paged        = $context['paged'];
            $filters      = $context['filters'];
            $selected_ids = $context['selected_ids'];
            $page_ids     = $context['page_ids'];

            $query_args = $this->build_query_args($filters, $selected_ids, $scope, $per_page, $paged, $page_ids);
            $presets    = $this->fetch_presets($query_args);

            if (empty($presets)) {
                $this->send_no_presets_response();
            }

            $this->prime_preset_caches($presets);

            if ('all' === $scope) {
                $headers = apply_filters('mkl_pc_preset_export_csv_headers', self::RAW_CSV_HEADERS);
                $output  = $this->open_csv_stream($headers, 'preset-raw-export');

                $this->build_raw_export_rows($presets, function (array $row) use ($output, $headers, $presets) {
                    $filtered_rows = apply_filters('mkl_pc_preset_raw_export_rows', [$row], $presets);
                    foreach ($filtered_rows as $filtered_row) {
                        $this->write_csv_row($output, $headers, $filtered_row);
                    }
                });

                fclose($output);
                exit;
            }

            $rows = $this->build_raw_export_rows($presets);

            if (empty($rows)) {
                $this->send_no_presets_response();
            }

            /**
             * Allow modification of rows before streaming the raw export CSV.
             *
             * @param array<int,array<string,string>> $rows    The export rows.
             * @param array<int,\WP_Post>             $presets The preset posts included in the export.
             */
            $rows = apply_filters('mkl_pc_preset_raw_export_rows', $rows, $presets);

            $this->stream_csv($rows, self::RAW_CSV_HEADERS, 'preset-raw-export');
        }

        /**
         * Deny unauthenticated access to the export endpoint.
         */
        public function deny_non_privileged(): void
        {
            wp_die(
                esc_html__('Authentication is required to export presets.', 'mkl-pc-shopify-export'),
                esc_html__('Unauthorized', 'mkl-pc-shopify-export'),
                ['response' => 401]
            );
        }

        /**
         * Build the WP_Query arguments for fetching presets based on filters and selection.
         *
         * @param array  $filters
         * @param array  $selected_ids
         * @param string $scope
         * @param int    $per_page
         * @param int    $paged
         * @param array<int> $page_ids
         * @return array
         */
        private function build_query_args(array $filters, array $selected_ids, string $scope, int $per_page, int $paged, array $page_ids = []): array
        {
            $args = [
                'post_type'      => 'mkl_pc_configuration',
                'post_status'    => 'preset',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'suppress_filters' => false,
            ];

            if ('selection' === $scope && ! empty($selected_ids)) {
                $args['post__in']       = array_map('absint', $selected_ids);
                $args['posts_per_page'] = max(1, count($args['post__in']));
                $args['orderby']        = 'post__in';
            } elseif ('page' === $scope) {
                if (! empty($page_ids)) {
                    $args['post__in']       = array_map('absint', $page_ids);
                    $args['posts_per_page'] = max(1, count($args['post__in']));
                    $args['orderby']        = 'post__in';
                } else {
                    $per_page = $per_page > 0 ? $per_page : 20;
                    $args['posts_per_page'] = $per_page;
                    $args['paged']          = max(1, $paged);
                }
            } else { // 'all'
                $args['posts_per_page'] = -1;
            }

            if (isset($filters['post_status']) && 'all' !== $filters['post_status']) {
                $statuses = array_map('sanitize_key', (array) $filters['post_status']);
                $statuses = array_filter($statuses);
                if (! empty($statuses)) {
                    $args['post_status'] = $statuses;
                }
            }

            if (! empty($filters['s'])) {
                $args['s'] = sanitize_text_field((string) $filters['s']);
            }

            if (! empty($filters['author'])) {
                $args['author'] = (int) $filters['author'];
            }

            if (! empty($filters['m'])) {
                $args['m'] = preg_replace('/[^0-9]/', '', (string) $filters['m']);
            }

            if (! empty($filters['orderby'])) {
                $args['orderby'] = sanitize_text_field((string) $filters['orderby']);
            }

            if (! empty($filters['order'])) {
                $order = strtoupper(sanitize_text_field((string) $filters['order']));
                if (in_array($order, ['ASC', 'DESC'], true)) {
                    $args['order'] = $order;
                }
            }

            foreach (['post_parent', 'product_id', 'parent', 'post_parent_id', 'mkl_pc_product'] as $key) {
                if (! empty($filters[$key])) {
                    $args['post_parent'] = absint($filters[$key]);
                    break;
                }
            }

            $tax_query = [];

            if (! empty($filters['product_cat'])) {
                $terms = array_filter(array_map('sanitize_title', (array) $filters['product_cat']));
                if ($terms) {
                    $tax_query[] = [
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => $terms,
                    ];
                }
            }

            if (! empty($filters['product_tag'])) {
                $terms = array_filter(array_map('sanitize_title', (array) $filters['product_tag']));
                if ($terms) {
                    $tax_query[] = [
                        'taxonomy' => 'product_tag',
                        'field'    => 'slug',
                        'terms'    => $terms,
                    ];
                }
            }

            if (! empty($tax_query)) {
                if (count($tax_query) > 1) {
                    $tax_query['relation'] = 'AND';
                }
                $args['tax_query'] = $tax_query;
            }

            /**
             * Filter the query arguments used to fetch presets for export.
             *
             * @param array $args          Query arguments.
             * @param array $filters       Parsed filters from the source list table.
             * @param array $selected_ids  Explicitly selected preset IDs.
             * @param string $scope        Export scope (selection, page, all).
             * @param int    $per_page     Items per page (when scope is page).
             * @param int    $paged        Current page (when scope is page).
             */
            return apply_filters('mkl_pc_preset_export_query_args', $args, $filters, $selected_ids, $scope, $per_page, $paged, $page_ids);
        }

        /**
         * Parse a comma or whitespace separated string of IDs into an array.
         *
         * @param string|array $raw_ids
         * @return array<int>
         */
        private function parse_id_list($raw_ids): array
        {
            $candidates = [];

            if (is_array($raw_ids)) {
                $candidates = $raw_ids;
            } elseif (is_string($raw_ids) && '' !== trim($raw_ids)) {
                $candidates = preg_split('/[\s,]+/', $raw_ids);
            }

            $ids = [];

            foreach ($candidates as $candidate) {
                $id = absint($candidate);
                if ($id) {
                    $ids[$id] = $id;
                }
            }

            return array_values($ids);
        }

        /**
         * Normalise a layer label for comparison.
         */
        private function normalize_layer_label(string $value): string
        {
            $value = strtolower(trim(preg_replace('/\s+/', ' ', $value)));

            return $value;
        }

        /**
         * Store an admin-provided override for a variant layer.
         */
        private function set_variant_layer_override(string $type, string $value): void
        {
            if (! isset($this->variant_layer_overrides[$type])) {
                return;
            }

            $value = sanitize_text_field((string) $value);
            $value = trim($value);

            if ('' === $value) {
                $this->variant_layer_overrides[$type] = [
                    'raw'        => '',
                    'normalized' => '',
                    'slug'       => '',
                ];
                return;
            }

            $this->variant_layer_overrides[$type] = [
                'raw'        => $value,
                'normalized' => $this->normalize_layer_label($value),
                'slug'       => sanitize_title($value),
            ];
        }

        /**
         * Retrieve an override data structure for the requested layer type.
         *
         * @return array{raw:string,normalized:string,slug:string}
         */
        private function get_variant_layer_override(string $type): array
        {
            if (! isset($this->variant_layer_overrides[$type])) {
                return ['raw' => '', 'normalized' => '', 'slug' => ''];
            }

            return $this->variant_layer_overrides[$type];
        }

        /**
         * Determine whether the provided layer label matches an override.
         *
         * @param array{raw:string,normalized:string,slug:string} $override
         */
        private function label_matches_override(string $label, array $override): bool
        {
            if ('' === $override['raw']) {
                return false;
            }

            $normalized_label = $this->normalize_layer_label($label);
            if ($normalized_label === $override['normalized']) {
                return true;
            }

            $slug = sanitize_title($label);
            if ('' !== $slug && $slug === $override['slug']) {
                return true;
            }

            return false;
        }

        /**
         * Identify primary option layers (size and colour) within a preset configuration.
         *
         * @param array<int,array<string,mixed>> $option_entries
         * @return array{size: ?array, colour: ?array, other: array}
         */
        private function classify_primary_options(array $option_entries): array
        {
            $result = [
                'size'   => null,
                'colour' => null,
                'other'  => [],
            ];

            $matched_entries = [];
            $size_override   = $this->get_variant_layer_override('size');
            $colour_override = $this->get_variant_layer_override('colour');

            if ('' !== $size_override['raw'] || '' !== $colour_override['raw']) {
                foreach ($option_entries as $entry) {
                    $label = isset($entry['name']) ? (string) $entry['name'] : '';
                    $value = isset($entry['value']) ? (string) $entry['value'] : '';

                    if ('' === $label || '' === $value) {
                        continue;
                    }

                    $entry_key = md5($label . '|' . $value . '|' . (string) ($entry['order'] ?? ''));

                    if ('' !== $size_override['raw'] && null === $result['size'] && $this->label_matches_override($label, $size_override)) {
                        $result['size'] = [
                            'name'  => $label,
                            'value' => $value,
                            'slug'  => $this->slugify_value($value),
                        ];
                        $matched_entries[$entry_key] = true;
                        continue;
                    }

                    if ('' !== $colour_override['raw'] && null === $result['colour'] && $this->label_matches_override($label, $colour_override)) {
                        $slug = $this->slugify_value($value);
                        $slug = apply_filters('mkl_pc_preset_export_colour_slug', $slug, $value, $entry);

                        $result['colour'] = [
                            'name'  => $label,
                            'value' => $value,
                            'slug'  => $slug,
                        ];
                        $matched_entries[$entry_key] = true;
                    }
                }
            }

            foreach ($option_entries as $entry) {
                $label = isset($entry['name']) ? (string) $entry['name'] : '';
                $value = isset($entry['value']) ? (string) $entry['value'] : '';

                if ($label === '' || $value === '') {
                    continue;
                }

                $entry_key = md5($label . '|' . $value . '|' . (string) ($entry['order'] ?? ''));
                if (isset($matched_entries[$entry_key])) {
                    continue;
                }

                $type = $this->classify_option_label($label);

                if ($type === 'size' && null === $result['size']) {
                    $result['size'] = [
                        'name'  => $label,
                        'value' => $value,
                        'slug'  => $this->slugify_value($value),
                    ];
                    continue;
                }

                if ($type === 'colour' && null === $result['colour']) {
                    $slug = $this->slugify_value($value);
                    $slug = apply_filters('mkl_pc_preset_export_colour_slug', $slug, $value, $entry);

                    $result['colour'] = [
                        'name'  => $label,
                        'value' => $value,
                        'slug'  => $slug,
                    ];
                    continue;
                }

                $result['other'][] = $entry;
            }

            return $result;
        }

        /**
         * Classify an option label.
         */
        private function classify_option_label(string $label): string
        {
            $normalized = strtolower($label);

            if (strpos($normalized, 'size') !== false) {
                return 'size';
            }

            if (strpos($normalized, 'colour') !== false || strpos($normalized, 'color') !== false) {
                return 'colour';
            }

            return 'other';
        }

        /**
         * Generate a slug-style value from the provided choice label.
         */
        private function slugify_value(string $value): string
        {
            $slug = sanitize_title($value);

            if ($slug === '' && $value !== '') {
                $slug = strtolower(trim(preg_replace('/\s+/', '-', $value)));
            }

            return $slug;
        }

        /**
         * Determine if extra price is configured to override the base product price.
         */
        private function extra_price_overrides_product_price(): bool
        {
            if (! function_exists('mkl_pc')) {
                return false;
            }

            $settings = mkl_pc('settings');
            if (! $settings || ! is_object($settings) || ! method_exists($settings, 'get')) {
                return false;
            }

            $override = $settings->get('extra_price_overrides_product_price', false);

            /**
             * Filter whether the extra price overrides the product price.
             *
             * @param bool $override Current override flag.
             */
            return (bool) apply_filters('extra_price_overrides_product_price', $override);
        }

        /**
         * Retrieve presets based on query arguments.
         *
         * @param array $query_args
         * @return array<\WP_Post>
         */
        private function fetch_presets(array $query_args): array
        {
            return get_posts($query_args);
        }

        /**
         * Prime object caches for presets to reduce subsequent queries.
         *
         * @param array<int,\WP_Post> $presets
         */
        private function prime_preset_caches(array $presets): void
        {
            if (empty($presets)) {
                return;
            }

            $ids = [];
            foreach ($presets as $preset) {
                if ($preset instanceof \WP_Post) {
                    $ids[] = (int) $preset->ID;
                }
            }

            if (! empty($ids)) {
                update_postmeta_cache($ids);
            }

            if (function_exists('update_post_caches')) {
                update_post_caches($presets, 'mkl_pc_configuration', false, true);
            }
        }

        /**
         * Group presets by their parent WooCommerce product ID.
         *
         * @param array<\WP_Post> $presets
         * @return array<int, array<\WP_Post>>
         */
        private function group_presets_by_product(array $presets): array
        {
            $grouped = [];

            foreach ($presets as $preset) {
                if (! $preset instanceof \WP_Post) {
                    continue;
                }

                $parent_id = (int) $preset->post_parent;

                if (! $parent_id) {
                    continue;
                }

                $grouped[$parent_id][] = $preset;
            }

            return $grouped;
        }

        /**
         * Process grouped presets into Shopify export rows, optionally streaming.
         *
         * @param array<int,array<\WP_Post>> $grouped
         * @param callable|null              $row_consumer
         * @return array<int,array<string,string>>
         */
        private function process_shopify_groups(array $grouped, ?callable $row_consumer = null): array
        {
            $rows = [];

            foreach ($grouped as $product_id => $preset_posts) {
                $product = wc_get_product($product_id);

                if (! $product) {
                    continue;
                }

                $variants          = [];
                $seen_variant_keys = [];

                foreach ($preset_posts as $preset_post) {
                    $variant_payload = $this->build_variant_payload($product, $preset_post);

                    if (! $variant_payload) {
                        continue;
                    }

                    $variant_key = isset($variant_payload['variant_key']) ? (string) $variant_payload['variant_key'] : '';
                    if ($variant_key !== '') {
                        if (isset($seen_variant_keys[$variant_key])) {
                            continue;
                        }
                        $seen_variant_keys[$variant_key] = true;
                    }

                    $variants[] = $variant_payload;
                }

                if (empty($variants)) {
                    continue;
                }

                $product_rows = $this->format_product_rows($product, $variants);

                if ($row_consumer) {
                    foreach ($product_rows as $product_row) {
                        $row_consumer($product_row);
                    }
                } else {
                    $rows = array_merge($rows, $product_rows);
                }
            }

            if ($row_consumer) {
                return [];
            }

            return $rows;
        }

        /**
         * Build raw export rows for the provided presets.
         *
         * @param array<int,\WP_Post> $presets
         * @param callable|null       $row_consumer
         * @return array<int,array<string,string>>
         */
        private function build_raw_export_rows(array $presets, ?callable $row_consumer = null): array
        {
            $rows                     = [];
            $overrides_product_price  = $this->extra_price_overrides_product_price();
            $price_decimals           = wc_get_price_decimals();
            $price_decimals           = is_numeric($price_decimals) ? (int) $price_decimals : 2;

            foreach ($presets as $preset_post) {
                if (! $preset_post instanceof \WP_Post) {
                    continue;
                }

                $product = null;
                if ($preset_post->post_parent) {
                    $product = wc_get_product((int) $preset_post->post_parent);
                }

                $config_model   = $this->get_configuration_model($preset_post->ID);
                $option_entries = $this->extract_configuration_options($preset_post);
                $classified     = $this->classify_primary_options($option_entries);

                $size_label = '';
                $size_value = '';
                $size_slug  = '';

                if (! empty($classified['size'])) {
                    $size_label = (string) $classified['size']['name'];
                    $size_value = trim((string) $classified['size']['value']);
                    $size_slug  = (string) $classified['size']['slug'];
                }

                $colour_label = '';
                $colour_value = '';
                $colour_slug  = '';

                if (! empty($classified['colour'])) {
                    $colour_label = (string) $classified['colour']['name'];
                    $colour_value = trim((string) $classified['colour']['value']);
                    $colour_slug  = (string) $classified['colour']['slug'];
                }

                $variant_key = '';
                if ('' !== $size_value && '' !== $colour_slug) {
                    $variant_key = strtolower($size_value) . '|' . strtolower($colour_slug);
                }

                $extra_price_raw = $this->calculate_extra_price($config_model);
                $extra_total     = 0.0;

                if ($product instanceof \WC_Product) {
                    $extra_total = $this->calculate_configuration_extra_total($product, $config_model, $preset_post);
                }

                $base_price_component = 0.0;
                $base_price           = 0.0;

                if ($product instanceof \WC_Product) {
                    $base_price = (float) $product->get_price();
                    $base_price_component = $base_price;
                }

                $variant_price_raw = 0.0;
                $extra_display_total = $extra_total > 0 ? $extra_total : $extra_price_raw;

                if ($overrides_product_price) {
                    $variant_price_raw     = $extra_display_total;
                    $base_price_component  = 0.0;
                } else {
                    $variant_price_raw = $base_price + $extra_display_total;
                }

                $variant_price_raw = apply_filters(
                    'mkl_pc_preset_export_variant_price',
                    (float) $variant_price_raw,
                    $product,
                    $preset_post,
                    $config_model
                );

                $variant_price_formatted      = $this->format_price((float) $variant_price_raw);
                $extra_total_formatted        = $this->format_price((float) $extra_total);
                $extra_price_raw_formatted    = $this->format_price((float) $extra_price_raw);
                $base_price_component_display = $this->format_price((float) $base_price_component);

                $preset_meta_raw = get_post_meta($preset_post->ID);
                $preset_meta     = [];
                foreach ($preset_meta_raw as $meta_key => $meta_values) {
                    if (is_array($meta_values)) {
                        $preset_meta[$meta_key] = count($meta_values) === 1 ? $meta_values[0] : array_values($meta_values);
                    } else {
                        $preset_meta[$meta_key] = $meta_values;
                    }
                }

                $preset_meta_json = wp_json_encode($preset_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (false === $preset_meta_json) {
                    $preset_meta_json = '';
                }

                $option_entries_json = wp_json_encode($option_entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (false === $option_entries_json) {
                    $option_entries_json = '';
                }

                $other_options_json = wp_json_encode($classified['other'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (false === $other_options_json) {
                    $other_options_json = '';
                }

                $configuration_raw   = wp_unslash($preset_post->post_content);
                $configuration_json  = '';
                if ('' !== $configuration_raw) {
                    $decoded = json_decode($configuration_raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $configuration_json = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (false === $configuration_json) {
                            $configuration_json = $configuration_raw;
                        }
                    } else {
                        $configuration_json = $configuration_raw;
                    }
                }

                $variant_image = $this->get_variant_image_url($preset_post, $config_model);

                $variant_requires_shipping = false;
                $variant_taxable           = false;
                $variant_weight_grams      = '';
                $variant_weight_unit       = '';
                $variant_sku               = 'preset-' . $preset_post->ID;
                $product_title             = '';
                $product_slug              = '';
                $product_status            = '';
                $product_type              = '';
                $product_price_formatted   = $this->format_price((float) $base_price);
                $product_regular_price     = $this->format_price(0.0);
                $product_categories        = '';
                $product_tags              = '';
                $product_permalink         = '';
                $product_featured_image    = '';

                if ($product instanceof \WC_Product) {
                    $product_title         = $product->get_name();
                    $product_status        = $product->get_status();
                    $product_type          = $product->get_type();
                    $product_slug          = get_post_field('post_name', $product->get_id());
                    $product_price_formatted = $this->format_price((float) $product->get_price());
                    $product_regular_price   = $this->format_price((float) $product->get_regular_price());
                    $variant_requires_shipping = ! $product->get_virtual();
                    $variant_taxable           = ('taxable' === $product->get_tax_status());
                    $variant_weight_unit       = $this->map_weight_unit(get_option('woocommerce_weight_unit', 'kg'));
                    $variant_weight_value      = $this->convert_weight_to_grams($product->get_weight());
                    if (null !== $variant_weight_value) {
                        $variant_weight_grams = (string) $variant_weight_value;
                    }

                    $sku_base = $product->get_sku();
                    if ($sku_base) {
                        $variant_sku = $sku_base . '-' . $preset_post->ID;
                    } else {
                        $variant_sku = 'preset-' . $product->get_id() . '-' . $preset_post->ID;
                    }

                    $product_id = $product->get_id();
                    $product_permalink = get_permalink($product_id);

                    $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
                    if (! is_wp_error($terms) && ! empty($terms)) {
                        $product_categories = implode('|', array_map('sanitize_text_field', $terms));
                    }

                    $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
                    if (! is_wp_error($tags) && ! empty($tags)) {
                        $product_tags = implode('|', array_map('sanitize_text_field', $tags));
                    }

                    $image_id = $product->get_image_id();
                    if ($image_id) {
                        $url = wp_get_attachment_url($image_id);
                        if ($url) {
                            $product_featured_image = $url;
                        }
                    }
                }

                $preset_author_id   = (int) $preset_post->post_author;
                $preset_author_name = $preset_author_id ? get_the_author_meta('display_name', $preset_author_id) : '';

                $row = [
                    'Preset ID'                              => (string) $preset_post->ID,
                    'Preset Title'                           => $preset_post->post_title,
                    'Preset Slug'                            => $preset_post->post_name,
                    'Preset Status'                          => $preset_post->post_status,
                    'Preset Author ID'                       => (string) $preset_author_id,
                    'Preset Author Name'                     => $preset_author_name,
                    'Preset Date'                            => $preset_post->post_date,
                    'Preset Modified'                        => $preset_post->post_modified,
                    'Preset Permalink'                       => get_permalink($preset_post),
                    'Product ID'                             => $product instanceof \WC_Product ? (string) $product->get_id() : '',
                    'Product Title'                          => $product_title,
                    'Product Slug'                           => (string) $product_slug,
                    'Product Status'                         => $product_status,
                    'Product Type'                           => $product_type,
                    'Product SKU'                            => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
                    'Product Price'                          => $product_price_formatted,
                    'Product Regular Price'                  => $product_regular_price,
                    'Product Categories'                     => $product_categories,
                    'Product Tags'                           => $product_tags,
                    'Product Permalink'                      => $product_permalink,
                    'Product Featured Image'                 => $product_featured_image,
                    'Variant Price'                          => $variant_price_formatted,
                    'Variant Price (Raw)'                    => number_format((float) $variant_price_raw, $price_decimals, '.', ''),
                    'Base Price Component'                   => $base_price_component_display,
                    'Extra Price Total'                      => $extra_total_formatted,
                    'Extra Price Raw'                        => $extra_price_raw_formatted,
                    'Extra Price Overrides Product Price'    => $overrides_product_price ? 'true' : 'false',
                    'Variant Requires Shipping'              => $variant_requires_shipping ? 'true' : 'false',
                    'Variant Taxable'                        => $variant_taxable ? 'true' : 'false',
                    'Variant Weight Grams'                   => $variant_weight_grams,
                    'Variant Weight Unit'                    => $variant_weight_unit,
                    'Variant SKU'                            => $variant_sku,
                    'Variant Key'                            => $variant_key,
                    'Option1 Label'                          => $size_label,
                    'Option1 Value'                          => $size_value,
                    'Option1 Slug'                           => $size_slug,
                    'Option2 Label'                          => $colour_label,
                    'Option2 Value'                          => $colour_value,
                    'Option2 Slug'                           => $colour_slug,
                    'Other Options JSON'                     => $other_options_json,
                    'Option Entries JSON'                    => $option_entries_json,
                    'Configuration JSON'                     => $configuration_json,
                    'Preset Meta JSON'                       => $preset_meta_json,
                    'Variant Image'                          => $variant_image,
                ];

                if ($row_consumer) {
                    $row_consumer($row);
                } else {
                    $rows[] = $row;
                }
            }

            return $row_consumer ? [] : $rows;
        }

        /**
         * Calculate the combined extra price for all configured choices in a preset.
         *
         * @param \WC_Product                      $product
         * @param \Mkl_PC_Preset_Configuration|null $config
         * @param \WP_Post                         $preset_post
         */
        private function calculate_configuration_extra_total(\WC_Product $product, $config, \WP_Post $preset_post): float
        {
            if (! $config || ! method_exists($config, 'get_layers')) {
                return 0.0;
            }

            $layers = $config->get_layers();
            if (! is_array($layers) || empty($layers)) {
                return 0.0;
            }

            $product_clone = clone $product;

            $context_cart_item = [
                'product_id'        => $product_clone->get_id(),
                'variation_id'      => method_exists($product_clone, 'get_variation_id') ? $product_clone->get_variation_id() : 0,
                'data'              => $product_clone,
                'configurator_data' => $layers,
                'preset_id'         => $preset_post->ID,
            ];

            $total = 0.0;

            foreach ($layers as $layer_choice) {
                if (! $layer_choice || ! is_object($layer_choice)) {
                    continue;
                }

                if (! apply_filters('mkl_pc/extra_price/add_extra_price', true, $layer_choice, $context_cart_item)) {
                    continue;
                }

                $choice_price = $layer_choice->get_choice('extra_price');
                $choice_price = apply_filters('mkl_pc/extra_price/price_to_display', $choice_price, $layer_choice, $product_clone, null);
                $choice_price = (float) $choice_price;

                $quantity = apply_filters('mkl_pc/extra_price/qty', 1, $layer_choice, $context_cart_item);
                $quantity = (float) $quantity;

                $line_total = $choice_price * $quantity;
                $line_total = apply_filters(
                    'mkl_pc_preset_export_extra_item_total',
                    $line_total,
                    $layer_choice,
                    $product_clone,
                    $preset_post,
                    $context_cart_item
                );
                $total += (float) $line_total;
            }

            $total = apply_filters(
                'mkl_pc_preset_export_extra_total',
                $total,
                $product_clone,
                $preset_post,
                $layers,
                $context_cart_item
            );

            return (float) $total;
        }

        /**
         * Build the data payload for a single preset variant.
         *
         * @param \WC_Product $product
         * @param \WP_Post    $preset_post
         * @return array<string,mixed>|null
         */
        private function build_variant_payload(\WC_Product $product, \WP_Post $preset_post): ?array
        {
            $option_entries = $this->extract_configuration_options($preset_post);
            $options_map    = [];

            foreach ($option_entries as $entry) {
                if (! isset($options_map[$entry['name']])) {
                    $options_map[$entry['name']] = $entry['value'];
                }
            }

            $classified = $this->classify_primary_options($option_entries);

            if (! $classified['size'] || ! $classified['colour']) {
                return null;
            }

            $size_label   = $classified['size']['name'];
            $size_value   = trim($classified['size']['value']);
            $size_slug    = $classified['size']['slug'];
            $colour_label = $classified['colour']['name'];
            $colour_value = trim($classified['colour']['value']);
            $colour_slug  = $classified['colour']['slug'];
            $variant_key  = strtolower($size_value) . '|' . strtolower($colour_slug);

            $config_model  = $this->get_configuration_model($preset_post->ID);
            $extra_price   = $this->calculate_extra_price($config_model);
            $base_price    = (float) $product->get_price();
            $extra_total   = $this->calculate_configuration_extra_total($product, $config_model, $preset_post);
            $base_component = $base_price;

            if ($extra_total <= 0.0 && $extra_price > 0.0) {
                $extra_total = $extra_price;
            }

            $overrides_product_price = $this->extra_price_overrides_product_price();
            if ($overrides_product_price) {
                $variant_price = $extra_total > 0 ? $extra_total : $extra_price;
                $base_component = 0.0;
            } else {
                $variant_price = $base_price + ($extra_total > 0 ? $extra_total : $extra_price);
            }

            $variant_price = apply_filters(
                'mkl_pc_preset_export_variant_price',
                $variant_price,
                $product,
                $preset_post,
                $config_model
            );
            $variant_price_value = (float) $variant_price;

            $variant_image = $this->get_variant_image_url($preset_post, $config_model);

            $sku_base = $product->get_sku();
            if (! $sku_base) {
                $sku_base = 'preset-' . $product->get_id();
            }
            $variant_sku = $sku_base . '-' . $preset_post->ID;

            $grams       = $this->convert_weight_to_grams($product->get_weight());
            $weight_unit = $this->map_weight_unit(get_option('woocommerce_weight_unit', 'kg'));

            $barcode = get_post_meta($preset_post->ID, '_barcode', true);
            if (! $barcode) {
                $barcode = $product->get_meta('_barcode', true);
            }

        $compare_at_price = '';
        $regular_price    = (float) $product->get_regular_price();
        if ($regular_price > $variant_price) {
            $compare_at_price = $this->format_price($regular_price);
        }
        if ($compare_at_price === '') {
            $compare_at_price = $this->format_price(0.0);
        }

        $cost_meta = $product->get_meta('_wc_cog_cost', true);
        $cost      = $cost_meta !== '' ? $this->format_price((float) $cost_meta) : '';

        $payload = [
            'preset_id'        => $preset_post->ID,
            'name'             => $preset_post->post_title,
            'options'          => $options_map,
            'options_ordered'  => $option_entries,
            'variant_key'      => $variant_key,
            'size_label'       => $size_label,
            'size_value'       => $size_value,
            'size_slug'        => $size_slug,
            'colour_label'     => $colour_label,
            'colour_value'     => $colour_value,
            'colour_slug'      => $colour_slug,
            'price'            => $this->format_price($variant_price_value),
            'price_raw'        => $variant_price_value,
            'extra_price_total' => $this->format_price($extra_total),
            'extra_price_raw'   => $extra_total,
            'base_price_raw'    => $base_component,
                'base_price_formatted' => $this->format_price($base_component),
                'compare_at_price' => $compare_at_price,
                'sku'              => $variant_sku,
                'grams'            => null !== $grams ? (string) $grams : '',
                'weight_unit'      => $weight_unit,
                'requires_shipping'=> ! $product->get_virtual(),
                'taxable'          => ('taxable' === $product->get_tax_status()),
                'barcode'          => $barcode ? (string) $barcode : '',
                'image'            => $variant_image,
                'cost'             => $cost,
                'include_us'       => ! $product->get_virtual(),
            ];

            /**
             * Allow advanced customisation of the variant payload before formatting rows.
             *
             * @param array       $payload
             * @param \WC_Product $product
             * @param \WP_Post    $preset_post
             * @param mixed       $config_model
             */
            return apply_filters('mkl_pc_preset_export_variant_payload', $payload, $product, $preset_post, $config_model);
        }

        /**
         * Determine the ordered option schema for a product (up to 3 options).
         *
         * @param array<int,array<string,mixed>> $variants
         * @return array<int,string>
         */
        private function determine_option_schema(array $variants): array
        {
            $schema = [];

            foreach ($variants as $variant) {
                if (empty($variant['options_ordered']) || ! is_array($variant['options_ordered'])) {
                    continue;
                }

                foreach ($variant['options_ordered'] as $entry) {
                    if (! isset($entry['name'])) {
                        continue;
                    }
                    $name  = (string) $entry['name'];
                    $order = isset($entry['order']) ? (int) $entry['order'] : count($schema);

                    if (! isset($schema[$name])) {
                        $schema[$name] = $order;
                    } else {
                        $schema[$name] = min($schema[$name], $order);
                    }
                }
            }

            if (empty($schema)) {
                return [];
            }

            asort($schema, SORT_NUMERIC);

            return array_slice(array_keys($schema), 0, 3);
        }

        /**
         * Convert variants into CSV rows for a single product.
         *
         * @param \WC_Product $product
         * @param array<int,array<string,mixed>> $variants
         * @return array<int,array<string,string>>
         */
        private function format_product_rows(\WC_Product $product, array $variants): array
        {
            $rows             = [];
            $product_handle   = $this->get_product_handle($product);
            $product_image    = $this->get_primary_product_image($product);
            $product_desc     = $this->get_product_description_html($product);
            $product_vendor   = $this->get_product_vendor($product);
            $product_category = $this->get_product_category_path($product);
            $product_type     = $this->get_product_type_label($product);
            $product_tags     = $this->get_product_tags($product);
            $published        = $product->get_status() === 'publish' ? 'true' : 'false';
            $status_value     = $product->get_status() === 'publish' ? 'active' : 'draft';
            $seo_title        = $this->get_seo_title($product);
            $seo_description  = $this->get_seo_description($product);

            $size_label   = $variants[0]['size_label'] ?? '';
            $colour_label = $variants[0]['colour_label'] ?? '';
            $size_label   = $size_label !== '' ? $size_label : __('Size', 'mkl-pc-shopify-export');
            $colour_label = $colour_label !== '' ? $colour_label : __('Colour', 'mkl-pc-shopify-export');

            $colour_slugs = array_unique(array_filter(array_map(function ($variant) {
                return isset($variant['colour_slug']) ? (string) $variant['colour_slug'] : '';
            }, $variants)));
            $colour_slug_list = implode('; ', $colour_slugs);

            $is_first    = true;
            $image_order = 1;

            foreach ($variants as $variant) {
                $row = array_fill_keys(self::CSV_HEADERS, '');

                $row['Handle'] = $product_handle;

                if ($is_first) {
                    $row['Title']              = $product->get_name();
                    $row['Body (HTML)']        = $product_desc;
                    $row['Vendor']             = $product_vendor;
                    $row['Product Category']   = $product_category;
                    $row['Type']               = $product_type;
                    $row['Tags']               = $product_tags;
                    $row['Published']          = $published;
                    $row['Gift Card']          = 'false';
                    $row['SEO Title']          = $seo_title;
                    $row['SEO Description']    = $seo_description;
                    $row['Google Shopping / Custom Product'] = 'TRUE';
                    $row['Status']             = $status_value;
                    $row['Option1 Name']       = $size_label;
                    $row['Option2 Name']       = $colour_label;
                    $row['Option1 Linked To']  = '';
                    $row['Option2 Linked To']  = 'product.metafields.shopify.color-pattern';
                    $row['Option3 Name']       = '';
                    $row['Option3 Linked To']  = '';
                    $row['Assembly Required (product.metafields.custom.assembly_required)'] = 'FALSE';
                    $row['Frame colours (product.metafields.custom.frame_colour)'] = $colour_slug_list;
                    $row['Google: Custom Product (product.metafields.mm-google-shopping.custom_product)'] = 'TRUE';
                    $row['Color (product.metafields.shopify.color-pattern)'] = $colour_slug_list;

                    if (! empty($product_image['url'])) {
                        $row['Image Src']      = $product_image['url'];
                        $row['Image Position'] = (string) $image_order;
                        $row['Image Alt Text'] = $product_image['alt'];
                        $image_order++;
                    }
                } else {
                    $row['Published'] = '';
                    $row['Status']    = '';
                }

                $row['Option1 Value'] = $variant['size_value'] ?? '';
                $row['Option2 Value'] = $variant['colour_slug'] ?? $this->slugify_value($variant['colour_value'] ?? '');
                $row['Option3 Value'] = '';

                $row['Variant SKU']                 = (string) $variant['sku'];
                $variant_grams                      = isset($variant['grams']) ? (string) $variant['grams'] : '';
                $row['Variant Grams']               = $variant_grams !== '' ? $variant_grams : '0.0';
                $row['Variant Inventory Tracker']   = '';
                $row['Variant Inventory Qty']       = '0';
                $row['Variant Inventory Policy']    = 'deny';
                $row['Variant Fulfillment Service'] = 'manual';
                $row['Variant Price']               = $variant['price'];
                $row['Variant Compare At Price']    = $variant['compare_at_price'] !== '' ? $variant['compare_at_price'] : $this->format_price(0.0);
                $row['Variant Requires Shipping']   = $variant['requires_shipping'] ? 'true' : 'false';
                $row['Variant Taxable']             = $variant['taxable'] ? 'true' : 'false';
                $row['Unit Price Total Measure']    = '';
                $row['Unit Price Total Measure Unit'] = '';
                $row['Unit Price Base Measure']     = '';
                $row['Unit Price Base Measure Unit'] = '';
                $row['Variant Barcode']             = $variant['barcode'];
                $row['Variant Image']               = $variant['image'];
                $row['Variant Weight Unit']         = $variant['weight_unit'];
                $row['Variant Tax Code']            = '';
                $row['Cost per item']               = $variant['cost'];
                $row['Status']                      = $is_first ? $status_value : '';
                $row['Option2 Linked To']           = $is_first ? 'product.metafields.shopify.color-pattern' : '';
                $row[self::CONFIGURATION_PRICE_HEADER] = $variant['extra_price_total'];

                $rows[] = apply_filters('mkl_pc_preset_export_csv_row', $row, $variant, $product, $is_first);
                $is_first = false;
            }

            return $rows;
        }

        /**
         * Open a CSV output stream and emit headers.
         *
         * @param array<int,string> $headers
         * @param string            $filename_prefix
         * @return resource
         */
        private function open_csv_stream(array $headers, string $filename_prefix)
        {
            $filename_prefix = strtolower(preg_replace('/[^a-z0-9\-_]+/', '-', $filename_prefix));
            if ('' === $filename_prefix) {
                $filename_prefix = 'preset-export';
            }

            $filename = $filename_prefix . '-' . gmdate('Ymd-His') . '.csv';

            nocache_headers();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Pragma: no-cache');

            $output = fopen('php://output', 'w');

            if (! $output) {
                wp_die(
                    esc_html__('Unable to open output stream.', 'mkl-pc-shopify-export'),
                    esc_html__('Export failed', 'mkl-pc-shopify-export'),
                    ['response' => 500]
                );
            }

            // Output UTF-8 BOM for spreadsheet compatibility.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, $headers);

            return $output;
        }

        /**
         * Write a single CSV row following the known header order.
         *
         * @param resource              $output
         * @param array<int,string>     $headers
         * @param array<string,string>  $row
         */
        private function write_csv_row($output, array $headers, array $row): void
        {
            $ordered = [];
            foreach ($headers as $header_label) {
                $ordered[] = isset($row[$header_label]) ? $row[$header_label] : '';
            }

            fputcsv($output, $ordered);
        }

        /**
         * Output the CSV stream to the browser.
         *
         * @param array<int,array<string,string>> $rows
         * @param array<int,string>               $headers_override
         * @param string                          $filename_prefix
         */
        private function stream_csv(array $rows, array $headers_override = [], string $filename_prefix = 'preset-export'): void
        {
            $headers = ! empty($headers_override) ? $headers_override : self::CSV_HEADERS;
            $headers = apply_filters('mkl_pc_preset_export_csv_headers', $headers);

            $output = $this->open_csv_stream($headers, $filename_prefix);

            foreach ($rows as $row) {
                $this->write_csv_row($output, $headers, $row);
            }

            fclose($output);
            exit;
        }

        /**
         * Fallback response when no presets match the export filters.
         */
        private function send_no_presets_response(): void
        {
            wp_die(
                esc_html__('No presets matched the current filters for export. Adjust the product filter or variant layer overrides and try again.', 'mkl-pc-shopify-export'),
                esc_html__('Nothing to export', 'mkl-pc-shopify-export'),
                ['response' => 404]
            );
        }

        /**
         * Extract choice options from a preset's stored configuration.
         *
         * @param \WP_Post $preset_post
         * @return array<int,array{name:string,value:string,order:int}>
         */
        private function extract_configuration_options(\WP_Post $preset_post): array
        {
            $entries     = [];
            $raw_content = wp_unslash($preset_post->post_content);

            if (! $raw_content) {
                return $entries;
            }

            $decoded = json_decode($raw_content, true);

            if (! is_array($decoded)) {
                return $entries;
            }

            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $choice_id = $item['choice_id'] ?? null;
                if (null === $choice_id || '' === $choice_id) {
                    continue;
                }

                $is_choice = isset($item['is_choice'])
                    ? filter_var($item['is_choice'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : null;

                if ($is_choice === false) {
                    continue;
                }

                $layer_name = isset($item['layer_name']) ? trim((string) $item['layer_name']) : '';
                if ('' === $layer_name) {
                    continue;
                }

                if (stripos($layer_name, 'Visual -') === 0) {
                    continue;
                }

                $choice_name = isset($item['name']) ? trim((string) $item['name']) : '';
                if ('' === $choice_name && isset($item['choice_name'])) {
                    $choice_name = trim((string) $item['choice_name']);
                }

                $order = 0;
                if (isset($item['image_order'])) {
                    $order = (int) $item['image_order'];
                } elseif (isset($item['order'])) {
                    $order = (int) $item['order'];
                } elseif (isset($item['layer_id'])) {
                    $order = (int) $item['layer_id'];
                } else {
                    $order = count($entries);
                }

                $entries[] = [
                    'name'  => $layer_name,
                    'value' => $choice_name,
                    'order' => $order,
                ];
            }

            usort($entries, static function ($a, $b) {
                if ($a['order'] === $b['order']) {
                    return strcmp($a['name'], $b['name']);
                }
                return $a['order'] <=> $b['order'];
            });

            return $entries;
        }

        /**
         * Retrieve (and cache) the configuration model for a preset.
         *
         * @param int $preset_id
         * @return \Mkl_PC_Preset_Configuration|null
         */
        private function get_configuration_model(int $preset_id)
        {
            if (! class_exists('Mkl_PC_Preset_Configuration')) {
                return null;
            }

            if (array_key_exists($preset_id, $this->configuration_cache)) {
                return $this->configuration_cache[$preset_id];
            }

            try {
                $config = new \Mkl_PC_Preset_Configuration($preset_id);
                if (is_wp_error($config)) {
                    $config = null;
                }
            } catch (\Throwable $throwable) {
                $config = null;
            }

            $this->configuration_cache[$preset_id] = $config;

            return $config;
        }

        /**
         * Calculate the total extra price configured on preset choices.
         *
         * @param mixed $config
         * @return float
         */
        private function calculate_extra_price($config): float
        {
            if (! $config || ! method_exists($config, 'get_layers')) {
                return 0.0;
            }

            $total  = 0.0;
            $layers = $config->get_layers();

            if (! is_array($layers)) {
                return 0.0;
            }

            foreach ($layers as $choice) {
                if (! is_object($choice) || ! method_exists($choice, 'get_choice')) {
                    continue;
                }

                $extra = $choice->get_choice('extra_price');
                if ('' === $extra || null === $extra) {
                    continue;
                }

                $total += (float) $extra;
            }

            return $total;
        }

        /**
         * Resolve a variant-specific image URL.
         *
         * @param \WP_Post $preset_post
         * @param mixed    $config
         * @return string
         */
        private function get_variant_image_url(\WP_Post $preset_post, $config): string
        {
            $thumbnail_id = get_post_thumbnail_id($preset_post);

            if ($thumbnail_id) {
                $url = wp_get_attachment_url($thumbnail_id);
                if ($url) {
                    return $url;
                }
            }

            if ($config && method_exists($config, 'get_image_url')) {
                $image = $config->get_image_url(false, 'full');
                if (is_array($image) && isset($image['url'])) {
                    $image = $image['url'];
                }
                if (is_string($image)) {
                    return $image;
                }
            }

            return '';
        }

        /**
         * Convert a weight to grams.
         *
         * @param string|float|null $weight
         * @return int|null
         */
        private function convert_weight_to_grams($weight): ?int
        {
            if ($weight === '' || $weight === null) {
                return null;
            }

            $weight = (float) $weight;
            $unit   = get_option('woocommerce_weight_unit', 'kg');

            switch ($unit) {
                case 'g':
                    return (int) round($weight);
                case 'kg':
                    return (int) round($weight * 1000);
                case 'lbs':
                case 'lb':
                    return (int) round($weight * 453.59237);
                case 'oz':
                    return (int) round($weight * 28.3495231);
                default:
                    return (int) round($weight);
            }
        }

        /**
         * Map WooCommerce weight unit to Shopify-compatible unit.
         *
         * @param string $unit
         * @return string
         */
        private function map_weight_unit(string $unit): string
        {
            $unit = strtolower($unit);

            switch ($unit) {
                case 'g':
                    return 'g';
                case 'kg':
                    return 'kg';
                case 'lb':
                case 'lbs':
                    return 'lb';
                case 'oz':
                    return 'oz';
                default:
                    return 'g';
            }
        }

        /**
         * Build a Shopify handle from the product slug.
         *
         * @param \WC_Product $product
         * @return string
        */
       private function get_product_handle(\WC_Product $product): string
       {
            $base_name = $product->get_name();
            $clean_name = $base_name;

            if ($clean_name) {
                $patterns = [
                    '/\s*[–-]\s*Size:\s*[^–-]+/i',
                    '/\s*[–-]\s*Colour:\s*[^–-]+/i',
                    '/\s*[–-]\s*Color:\s*[^–-]+/i',
                    '/\s*[–-]\s*Worktop:\s*[^–-]+/i',
                    '/\s*[–-]\s*Left-side Options:\s*[^–-]+/i',
                    '/\s*[–-]\s*Right-side Options:\s*[^–-]+/i',
                ];
                $clean_name = preg_replace($patterns, '', $clean_name);
                $clean_name = trim(preg_replace('/\s+/', ' ', $clean_name));
            }

            $handle = sanitize_title($clean_name);

            if (! $handle) {
                $handle = $product->get_slug();
            }

            if (! $handle) {
                $handle = sanitize_title($base_name ?: ('product-' . $product->get_id()));
            }

            $handle = sanitize_title($handle);
            $handle = apply_filters('mkl_pc_preset_export_handle', $handle, $product);

            if (! $handle) {
                $handle = 'product-' . $product->get_id();
            }

            return $handle;
        }

        /**
         * Retrieve the primary product image URL and alt text.
         *
         * @param \WC_Product $product
         * @return array{url:string,alt:string}
         */
        private function get_primary_product_image(\WC_Product $product): array
        {
            $attachment_id = $product->get_image_id();

            if (! $attachment_id) {
                return ['url' => '', 'alt' => ''];
            }

            $url = wp_get_attachment_url($attachment_id);
            $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

            if (! $alt) {
                $alt = $product->get_name();
            }

            return [
                'url' => $url ?: '',
                'alt' => $alt ? wp_strip_all_tags($alt) : '',
            ];
        }

        /**
         * Get the product description formatted as HTML.
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_product_description_html(\WC_Product $product): string
        {
            $description = $product->get_description();
            if (! $description) {
                return '';
            }

            return apply_filters('the_content', $description);
        }

        /**
         * Resolve a vendor name for Shopify export.
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_product_vendor(\WC_Product $product): string
        {
            $preferred_taxonomies = apply_filters(
                'mkl_pc_preset_export_vendor_taxonomies',
                ['pa_brand', 'pa_vendor', 'pa_manufacturer']
            );

            foreach ($preferred_taxonomies as $taxonomy) {
                $value = $product->get_attribute($taxonomy);
                if ($value) {
                    return wp_strip_all_tags($value);
                }
            }

            $store_name = get_bloginfo('name');
            return is_string($store_name) ? $store_name : '';
        }

        /**
         * Build a category breadcrumb path (Parent > Child).
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_product_category_path(\WC_Product $product): string
        {
            $terms = get_the_terms($product->get_id(), 'product_cat');

            if (! $terms || is_wp_error($terms)) {
                return $this->determine_shopify_category($product, []);
            }

            $primary = array_shift($terms);
            if (! $primary) {
                return $this->determine_shopify_category($product, []);
            }

            $ancestor_ids = array_reverse(get_ancestors($primary->term_id, 'product_cat'));
            $names        = [];

            foreach ($ancestor_ids as $ancestor_id) {
                $ancestor = get_term($ancestor_id, 'product_cat');
                if ($ancestor && ! is_wp_error($ancestor)) {
                    $names[] = $ancestor->name;
                }
            }

            $names[] = $primary->name;

            return $this->determine_shopify_category($product, $names);
        }

        /**
         * Resolve a Shopify-recognised category string.
         *
         * @param \WC_Product $product
         * @param array<int,string> $category_names
         */
        private function determine_shopify_category(\WC_Product $product, array $category_names): string
        {
            $manual = $product->get_meta('_shopify_product_category', true);
            if ($manual === '') {
                $manual = $product->get_meta('shopify_product_category', true);
            }
            if ($manual === '') {
                $manual = $product->get_meta('_product_category', true);
            }

            if (is_string($manual) && $manual !== '') {
                $manual = trim($manual);
                $manual = apply_filters('mkl_pc_preset_export_manual_category', $manual, $product);
                if ($manual !== '') {
                    return $manual;
                }
            }

            $derived = '';
            if (! empty($category_names)) {
                $derived = implode(' > ', array_map('wp_strip_all_tags', $category_names));
            }

            $derived = apply_filters('mkl_pc_preset_export_category_from_terms', $derived, $category_names, $product);
            if ($this->is_valid_shopify_category($derived)) {
                return $derived;
            }

            $fallback = self::DEFAULT_SHOPIFY_CATEGORY;

            $product_name = strtolower($product->get_name());
            $slug         = strtolower($product->get_slug());
            $search_blob  = $product_name . ' ' . $slug . ' ' . implode(' ', $category_names);

            if (strpos($search_blob, 'trolley') !== false) {
                $fallback = 'Hardware > Hardware Accessories > Tool Storage & Organization > Tool Carts';
            } elseif (strpos($search_blob, 'workbench') !== false || strpos($search_blob, 'work bench') !== false) {
                $fallback = self::DEFAULT_SHOPIFY_CATEGORY;
            }

            $fallback = apply_filters('mkl_pc_preset_export_default_category', $fallback, $product, $category_names);

            return $fallback;
        }

        /**
         * Basic validation that a category string resembles Shopify taxonomy.
         */
        private function is_valid_shopify_category(?string $category): bool
        {
            if (! is_string($category)) {
                return false;
            }

            $category = trim($category);
            if ($category === '') {
                return false;
            }

            if (is_numeric($category)) {
                return true;
            }

            if (strpos($category, '>') !== false) {
                return true;
            }

            return false;
        }

        /**
         * Provide a product type label for Shopify.
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_product_type_label(\WC_Product $product): string
        {
            $type = $product->get_attribute('pa_product_type');
            if ($type) {
                return wp_strip_all_tags($type);
            }

            $wc_type = $product->get_type();
            return ucwords(str_replace('-', ' ', (string) $wc_type));
        }

        /**
         * Return a comma-separated list of product tags.
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_product_tags(\WC_Product $product): string
        {
            $terms = get_the_terms($product->get_id(), 'product_tag');

            if (! $terms || is_wp_error($terms)) {
                return '';
            }

            $names = array_map('wp_strip_all_tags', wp_list_pluck($terms, 'name'));

            return implode(', ', $names);
        }

        /**
         * Resolve an SEO title, falling back to the product name.
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_seo_title(\WC_Product $product): string
        {
            $yoast = $product->get_meta('_yoast_wpseo_title', true);
            if ($yoast) {
                return (string) $yoast;
            }

            $aioseo = $product->get_meta('_aioseo_title', true);
            if ($aioseo) {
                return (string) $aioseo;
            }

            return $product->get_name();
        }

        /**
         * Resolve an SEO description, falling back to short description or trimmed content.
         *
         * @param \WC_Product $product
         * @return string
         */
        private function get_seo_description(\WC_Product $product): string
        {
            $yoast = $product->get_meta('_yoast_wpseo_metadesc', true);
            if ($yoast) {
                return (string) $yoast;
            }

            $aioseo = $product->get_meta('_aioseo_description', true);
            if ($aioseo) {
                return (string) $aioseo;
            }

            $short = $product->get_short_description();
            if ($short) {
                return wp_strip_all_tags($short);
            }

            $desc = $product->get_description();
            if ($desc) {
                return wp_strip_all_tags(wp_trim_words($desc, 40, '…'));
            }

            return '';
        }

        /**
         * Format a price with two decimal places.
         *
         * @param float $price
         * @return string
         */
        private function format_price(float $price): string
        {
            return number_format((float) $price, wc_get_price_decimals(), '.', '');
        }
    }
}

if (is_admin()) {
    MKL_PC_Preset_Shopify_Export::instance();
}
