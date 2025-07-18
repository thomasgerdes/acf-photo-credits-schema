<?php
/**
 * Plugin Name: ACF Photo Credits Schema
 * Plugin URI: https://github.com/thomasgerdes/acf-photo-credits-schema
 * Description: WordPress plugin that adds Schema.org markup for photographer credits and Creative Commons licenses from Advanced Custom Fields
 * Version: 1.0.0
 * Author: Thomas Gerdes
 * Author URI: https://thomasgerdes.de
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: acf-photo-credits-schema
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF Photo Credits Schema Plugin
 * 
 * A WordPress plugin that automatically generates Schema.org markup for photographer
 * credits and Creative Commons licenses stored in Advanced Custom Fields.
 * 
 * This plugin adds structured data to help search engines understand image
 * attribution and licensing information on your website.
 */
class ACF_Photo_Credits_Schema {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Plugin slug
     */
    const PLUGIN_SLUG = 'acf-photo-credits-schema';
    
    /**
     * Text domain for translations
     */
    const TEXT_DOMAIN = 'acf-photo-credits-schema';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check if ACF is active
        if (!function_exists('get_field')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
            return;
        }
        
        // Hook into WordPress
        add_action('wp_head', array($this, 'add_image_schema'), 20);
        add_action('wp_head', array($this, 'add_article_schema'), 21);
        add_action('wp_head', array($this, 'add_meta_tags'), 22);
        add_action('acf/save_post', array($this, 'auto_fill_cc_license_link'));
        add_filter('wp_sitemaps_posts_entry', array($this, 'enhance_sitemap'), 10, 3);
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_settings = array(
            'target_categories' => array('photolog'),
            'auto_fill_cc_links' => true,
            'include_sitemap_data' => true
        );
        
        add_option(self::PLUGIN_SLUG . '_settings', $default_settings);
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * ACF missing notice
     */
    public function acf_missing_notice() {
        $class = 'notice notice-error';
        $message = __('ACF Photo Credits Schema: This plugin requires Advanced Custom Fields to be installed and activated.', self::TEXT_DOMAIN);
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Add settings link to plugin actions
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=' . self::PLUGIN_SLUG) . '">' . __('Settings', self::TEXT_DOMAIN) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add image schema to head
     */
    public function add_image_schema() {
        if (!$this->should_add_schema()) {
            return;
        }
        
        $image_schemas = $this->get_image_schemas();
        
        if (!empty($image_schemas)) {
            echo '<script type="application/ld+json">';
            echo json_encode($image_schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo '</script>' . "\n";
        }
    }
    
    /**
     * Add article schema to head
     */
    public function add_article_schema() {
        if (!$this->should_add_schema()) {
            return;
        }
        
        $article_schema = $this->get_article_schema();
        
        if ($article_schema) {
            echo '<script type="application/ld+json">';
            echo json_encode($article_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo '</script>' . "\n";
        }
    }
    
    /**
     * Add meta tags
     */
    public function add_meta_tags() {
        if (!$this->should_add_schema()) {
            return;
        }
        
        $featured_image_id = get_post_thumbnail_id();
        if (!$featured_image_id) {
            return;
        }
        
        $photographer = get_field('photographer', $featured_image_id);
        $cc_license = get_field('cc_license', $featured_image_id);
        $cc_license_link = get_field('cc_license_link', $featured_image_id);
        
        if ($photographer || $cc_license) {
            $credit_text = $this->build_credit_text($photographer, $cc_license);
            
            // Open Graph tags
            echo '<meta property="og:image:alt" content="' . esc_attr($credit_text) . '">' . "\n";
            
            // Copyright meta tags
            if ($photographer) {
                echo '<meta name="dcterms.rightsHolder" content="' . esc_attr($photographer) . '">' . "\n";
                echo '<meta name="copyright" content="' . esc_attr($photographer) . '">' . "\n";
            }
            
            // License meta tags
            if ($cc_license) {
                echo '<meta name="dcterms.license" content="' . esc_attr($cc_license) . '">' . "\n";
                
                // Add license URL if available
                if ($cc_license_link) {
                    echo '<meta name="license" content="' . esc_attr($cc_license_link) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Auto-fill CC license link
     */
    public function auto_fill_cc_license_link($post_id) {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        
        if (!isset($settings['auto_fill_cc_links']) || !$settings['auto_fill_cc_links']) {
            return;
        }
        
        if (get_post_type($post_id) !== 'attachment') {
            return;
        }
        
        $cc_license = get_field('cc_license', $post_id);
        $cc_license_link = get_field('cc_license_link', $post_id);
        
        if ($cc_license && empty($cc_license_link)) {
            $license_url = $this->get_cc_license_url($cc_license);
            if ($license_url) {
                update_field('cc_license_link', $license_url, $post_id);
            }
        }
    }
    
    /**
     * Enhance sitemap with image data
     */
    public function enhance_sitemap($sitemap_entry, $post, $post_type) {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        
        if (!isset($settings['include_sitemap_data']) || !$settings['include_sitemap_data']) {
            return $sitemap_entry;
        }
        
        if ($post_type !== 'post' || !$this->is_target_category($post->ID)) {
            return $sitemap_entry;
        }
        
        $image_data = $this->get_post_images_for_sitemap($post);
        
        if (!empty($image_data)) {
            $sitemap_entry['images'] = $image_data;
        }
        
        return $sitemap_entry;
    }
    
    /**
     * Check if schema should be added
     */
    private function should_add_schema() {
        if (!is_single()) {
            return false;
        }
        
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_categories = isset($settings['target_categories']) ? $settings['target_categories'] : array('photolog');
        
        foreach ($target_categories as $category) {
            // Check both category slug and name to handle case sensitivity
            if (in_category($category) || in_category(strtolower($category)) || in_category(ucfirst(strtolower($category)))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if post is in target category
     */
    private function is_target_category($post_id) {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_categories = isset($settings['target_categories']) ? $settings['target_categories'] : array('photolog');
        
        foreach ($target_categories as $category) {
            // Check both category slug and name to handle case sensitivity
            if (in_category($category, $post_id) || in_category(strtolower($category), $post_id) || in_category(ucfirst(strtolower($category)), $post_id)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get image schemas
     */
    private function get_image_schemas() {
        global $post;
        
        preg_match_all('/wp-image-(\d+)/', $post->post_content, $matches);
        if (empty($matches[1])) {
            return array();
        }
        
        $image_ids = array_unique($matches[1]);
        $schemas = array();
        
        foreach ($image_ids as $image_id) {
            $schema = $this->build_image_schema($image_id);
            if ($schema) {
                $schemas[] = $schema;
            }
        }
        
        return $schemas;
    }
    
    /**
     * Build image schema
     */
    private function build_image_schema($image_id) {
        $photographer = get_field('photographer', $image_id);
        $photographer_website = get_field('photographer_website', $image_id);
        $cc_license = get_field('cc_license', $image_id);
        $cc_license_link = get_field('cc_license_link', $image_id);
        
        if (!$photographer && !$cc_license) {
            return null;
        }
        
        $image_url = wp_get_attachment_url($image_id);
        $image_meta = wp_get_attachment_metadata($image_id);
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $image_title = get_the_title($image_id);
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            '@id' => $image_url . '#image',
            'contentUrl' => $image_url,
            'url' => $image_url,
            'caption' => $image_alt ?: $image_title,
            'name' => $image_title
        );
        
        // Add dimensions
        if (isset($image_meta['width']) && isset($image_meta['height'])) {
            $schema['width'] = $image_meta['width'];
            $schema['height'] = $image_meta['height'];
        }
        
        // Add creator
        if ($photographer) {
            $creator = array(
                '@type' => 'Person',
                'name' => $photographer
            );
            
            if ($photographer_website) {
                $creator['url'] = $photographer_website;
            }
            
            $schema['creator'] = $creator;
        }
        
        // Add license
        if ($cc_license) {
            // CC-specific properties
            if (strpos($cc_license, 'CC') === 0) {
                if ($cc_license_link) {
                    $schema['license'] = $cc_license_link;
                }
                
                // More descriptive usage info for CC licenses
                $usage_descriptions = array(
                    'CC BY' => 'CC BY - Attribution required',
                    'CC BY-SA' => 'CC BY-SA - Attribution and ShareAlike required',
                    'CC BY-NC' => 'CC BY-NC - Attribution required, non-commercial use only',
                    'CC BY-NC-SA' => 'CC BY-NC-SA - Attribution and ShareAlike required, non-commercial use only',
                    'CC BY-ND' => 'CC BY-ND - Attribution required, no derivatives allowed',
                    'CC BY-NC-ND' => 'CC BY-NC-ND - Attribution required, non-commercial use only, no derivatives allowed',
                    'CC0' => 'CC0 - Public Domain, no rights reserved'
                );
                
                $schema['usageInfo'] = isset($usage_descriptions[$cc_license]) ? $usage_descriptions[$cc_license] : $cc_license;
                
                if ($photographer) {
                    $schema['creditText'] = "Photo by " . $photographer . " / " . $cc_license;
                }
            } else {
                // Non-CC licenses
                $schema['usageInfo'] = $cc_license;
            }
        }
        
        // Add upload date
        $upload_date = get_the_date('c', $image_id);
        if ($upload_date) {
            $schema['datePublished'] = $upload_date;
        }
        
        return $schema;
    }
    
    /**
     * Get article schema
     */
    private function get_article_schema() {
        global $post;
        
        $featured_image_id = get_post_thumbnail_id();
        $featured_image_schema = null;
        
        if ($featured_image_id) {
            $featured_image_schema = $this->build_image_schema($featured_image_id);
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            '@id' => get_permalink() . '#article',
            'headline' => get_the_title(),
            'description' => get_the_excerpt() ?: wp_trim_words(strip_tags($post->post_content), 20),
            'url' => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => get_author_posts_url(get_the_author_meta('ID'))
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            )
        );
        
        if ($featured_image_schema) {
            $schema['image'] = $featured_image_schema;
        }
        
        // Add categories
        $categories = get_the_category();
        if ($categories) {
            $schema['about'] = array();
            foreach ($categories as $category) {
                $schema['about'][] = array(
                    '@type' => 'Thing',
                    'name' => $category->name,
                    'url' => get_category_link($category->term_id)
                );
            }
        }
        
        return $schema;
    }
    
    /**
     * Get post images for sitemap
     */
    private function get_post_images_for_sitemap($post) {
        preg_match_all('/wp-image-(\d+)/', $post->post_content, $matches);
        if (empty($matches[1])) {
            return array();
        }
        
        $image_ids = array_unique($matches[1]);
        $images = array();
        
        foreach ($image_ids as $image_id) {
            $photographer = get_field('photographer', $image_id);
            $cc_license = get_field('cc_license', $image_id);
            
            if ($photographer || $cc_license) {
                $image_data = array(
                    'loc' => wp_get_attachment_url($image_id),
                    'caption' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    'title' => get_the_title($image_id)
                );
                
                if ($photographer) {
                    $image_data['credit'] = $photographer;
                }
                
                if ($cc_license) {
                    $image_data['license'] = $cc_license;
                }
                
                $images[] = $image_data;
            }
        }
        
        return $images;
    }
    
    /**
     * Build credit text
     */
    private function build_credit_text($photographer, $cc_license) {
        $credit_text = '';
        
        if ($photographer) {
            $credit_text .= 'Photo by ' . $photographer;
        }
        
        if ($cc_license) {
            $credit_text .= ($photographer ? ' / ' : '') . $cc_license;
        }
        
        return $credit_text;
    }
    
    /**
     * Get CC license URL
     */
    private function get_cc_license_url($license) {
        $urls = array(
            'CC BY' => 'https://creativecommons.org/licenses/by/4.0/',
            'CC BY-SA' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'CC BY-NC' => 'https://creativecommons.org/licenses/by-nc/4.0/',
            'CC BY-NC-SA' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            'CC BY-ND' => 'https://creativecommons.org/licenses/by-nd/4.0/',
            'CC BY-NC-ND' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
            'CC0' => 'https://creativecommons.org/publicdomain/zero/1.0/'
        );
        
        return isset($urls[$license]) ? $urls[$license] : '';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('ACF Photo Credits Schema Settings', self::TEXT_DOMAIN),
            __('Photo Credits Schema', self::TEXT_DOMAIN),
            'manage_options',
            self::PLUGIN_SLUG,
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(self::PLUGIN_SLUG . '_settings', self::PLUGIN_SLUG . '_settings');
        
        add_settings_section(
            'acf_photo_credits_main',
            __('Main Settings', self::TEXT_DOMAIN),
            array($this, 'settings_section_callback'),
            self::PLUGIN_SLUG
        );
        
        add_settings_field(
            'target_categories',
            __('Target Categories', self::TEXT_DOMAIN),
            array($this, 'target_categories_callback'),
            self::PLUGIN_SLUG,
            'acf_photo_credits_main'
        );
        
        add_settings_field(
            'auto_fill_cc_links',
            __('Auto-fill CC License Links', self::TEXT_DOMAIN),
            array($this, 'auto_fill_cc_links_callback'),
            self::PLUGIN_SLUG,
            'acf_photo_credits_main'
        );
        
        add_settings_field(
            'include_sitemap_data',
            __('Include Image Data in Sitemap', self::TEXT_DOMAIN),
            array($this, 'include_sitemap_data_callback'),
            self::PLUGIN_SLUG,
            'acf_photo_credits_main'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure which categories should include photo credits schema markup.', self::TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Target categories callback
     */
    public function target_categories_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_categories = isset($settings['target_categories']) ? $settings['target_categories'] : array('photolog');
        
        $categories = get_categories();
        
        echo '<fieldset>';
        foreach ($categories as $category) {
            // Check against both slug and name
            $checked = (in_array($category->slug, $target_categories) || in_array($category->name, $target_categories)) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="' . self::PLUGIN_SLUG . '_settings[target_categories][]" value="' . esc_attr($category->slug) . '" ' . $checked . '>';
            echo ' ' . esc_html($category->name) . ' (' . esc_html($category->slug) . ')';
            echo '</label><br>';
        }
        echo '</fieldset>';
        echo '<p class="description">' . __('Select which categories should include photo credits in schema markup.', self::TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Auto-fill CC links callback
     */
    public function auto_fill_cc_links_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $auto_fill = isset($settings['auto_fill_cc_links']) ? $settings['auto_fill_cc_links'] : true;
        
        echo '<input type="checkbox" name="' . self::PLUGIN_SLUG . '_settings[auto_fill_cc_links]" value="1" ' . checked(1, $auto_fill, false) . '>';
        echo '<p class="description">' . __('Automatically fill CC license links when a license is selected.', self::TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Include sitemap data callback
     */
    public function include_sitemap_data_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $include_sitemap = isset($settings['include_sitemap_data']) ? $settings['include_sitemap_data'] : true;
        
        echo '<input type="checkbox" name="' . self::PLUGIN_SLUG . '_settings[include_sitemap_data]" value="1" ' . checked(1, $include_sitemap, false) . '>';
        echo '<p class="description">' . __('Include image credit information in WordPress sitemaps.', self::TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::PLUGIN_SLUG . '_settings');
                do_settings_sections(self::PLUGIN_SLUG);
                submit_button();
                ?>
            </form>
            
            <h2><?php _e('Plugin Information', self::TEXT_DOMAIN); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e('Plugin Version', self::TEXT_DOMAIN); ?></th>
                    <td><?php echo self::VERSION; ?></td>
                </tr>
                <tr>
                    <th><?php _e('ACF Status', self::TEXT_DOMAIN); ?></th>
                    <td><?php echo function_exists('get_field') ? '<span style="color: green;">✓ ' . __('Active', self::TEXT_DOMAIN) . '</span>' : '<span style="color: red;">✗ ' . __('Not Active', self::TEXT_DOMAIN) . '</span>'; ?></td>
                </tr>
            </table>
            
            <h2><?php _e('Testing', self::TEXT_DOMAIN); ?></h2>
            <p><?php _e('Test your schema markup with these tools:', self::TEXT_DOMAIN); ?></p>
            <ul>
                <li><a href="https://search.google.com/test/rich-results" target="_blank"><?php _e('Google Rich Results Test', self::TEXT_DOMAIN); ?></a></li>
                <li><a href="https://validator.schema.org/" target="_blank"><?php _e('Schema.org Validator', self::TEXT_DOMAIN); ?></a></li>
            </ul>
        </div>
        <?php
    }
}

// Initialize plugin
new ACF_Photo_Credits_Schema();

?>
