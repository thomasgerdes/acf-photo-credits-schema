<?php
/**
 * Plugin Name: ACF Photo Credits Schema
 * Plugin URI: https://github.com/thomasgerdes/acf-photo-credits-schema
 * Description: WordPress plugin that adds Schema.org markup for photographer credits and Creative Commons licenses from Advanced Custom Fields
 * Version: 1.4.0
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

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class for the ACF Photo Credits Schema plugin.
 *
 * This plugin automatically generates Schema.org markup for images based on
 * data stored in Advanced Custom Fields (ACF). It supports photographer credits,
 * Creative Commons licenses, and enhances WordPress sitemaps with image metadata.
 *
 * This version generates a comprehensive ImageObject schema that complements,
 * rather than conflicts with, other SEO plugins like Yoast SEO.
 *
 * @since 1.0.0
 * @version 1.4.0
 */
class ACF_Photo_Credits_Schema {

    /**
     * Plugin version number
     *
     * @since 1.4.0
     * @var string
     */
    const VERSION = '1.4.0';

    /**
     * Plugin slug used for options and hooks
     *
     * @since 1.0.0
     * @var string
     */
    const PLUGIN_SLUG = 'acf-photo-credits-schema';

    /**
     * Text domain for internationalization
     *
     * @since 1.0.0
     * @var string
     */
    const TEXT_DOMAIN = 'acf-photo-credits-schema';

    /**
     * Constructor.
     *
     * Registers all necessary hooks and activation/deactivation callbacks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('admin_notices', array($this, 'schema_update_reminder'));
    }

    /**
     * Initializes the plugin.
     *
     * Loads the text domain, checks for ACF dependency, and sets up all core hooks.
     *
     * @since 1.0.0
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Check if ACF is active (required dependency)
        if (!function_exists('get_field')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
            return;
        }

        // Core functionality hooks
        // Use higher priority to ensure schema is rendered after other plugins
        add_action('wp_head', array($this, 'add_combined_schema'), 20);
        add_filter('wp_sitemaps_posts_entry', array($this, 'enhance_sitemap'), 10, 3);

        // Admin interface hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * Plugin activation hook.
     *
     * Initializes default plugin settings upon activation.
     *
     * @since 1.0.0
     */
    public function activate() {
        $default_settings = array(
            'target_categories' => array('photolog'),
            'target_tags' => array(),
            'auto_fill_cc_links' => true,
            'include_sitemap_data' => true,
            'auto_generate_copyright' => true,
            'default_license_page' => '',
            'genre_mappings' => ''
        );
        
        add_option(self::PLUGIN_SLUG . '_settings', $default_settings);
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook.
     *
     * Performs cleanup tasks upon plugin deactivation.
     *
     * @since 1.0.0
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Displays reminder notice for schema updates after ACF changes.
     *
     * @since 1.4.0
     */
    public function schema_update_reminder() {
        // Only show on media library and post edit screens
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('upload', 'post', 'edit'))) {
            return;
        }
        
        // Check if we're editing an attachment or if ACF was recently saved
        $show_notice = false;
        
        if ($screen->base === 'upload' || 
            ($screen->base === 'post' && $screen->post_type === 'attachment') ||
            (isset($_GET['message']) && $_GET['message'] === '1')) {
            $show_notice = true;
        }
        
        if ($show_notice) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('ACF Photo Credits Schema:', self::TEXT_DOMAIN) . '</strong> ';
            echo __('Remember to re-save posts containing updated images to refresh their schema markup.', self::TEXT_DOMAIN);
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * Displays admin notice when ACF is not active.
     *
     * Shows an error notice in the WordPress admin if Advanced Custom Fields
     * is not installed or activated.
     *
     * @since 1.0.0
     */
    public function acf_missing_notice() {
        $class = 'notice notice-error';
        $message = __('ACF Photo Credits Schema: This plugin requires Advanced Custom Fields to be installed and activated.', self::TEXT_DOMAIN);
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Adds settings link to plugin action links.
     *
     * Adds a "Settings" link to the plugin's action links on the plugins page.
     *
     * @since 1.0.0
     * @param array $links Existing plugin action links.
     * @return array Modified links array.
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=' . self::PLUGIN_SLUG) . '">' . __('Settings', self::TEXT_DOMAIN) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Outputs combined schema markup in the document head.
     *
     * Generates and outputs ImageObject schema in a single JSON-LD block.
     *
     * @since 1.7.0
     */
    public function add_combined_schema() {
        if (!$this->should_add_schema()) {
            return;
        }

        $schemas = array();
        
        // Get all ImageObject schemas with enhanced SEO features
        $image_schemas = $this->get_image_schemas();
        if (!empty($image_schemas)) {
            $schemas = array_merge($schemas, $image_schemas);
        }

        // The Article schema is no longer generated to avoid conflicts with other SEO plugins.

        // Output combined schema if we have any data
        if (!empty($schemas)) {
            echo '' . "\n";
            echo '<script type="application/ld+json">';
            echo json_encode($schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo '</script>' . "\n";
        }
    }

    /**
     * Determines if schema markup should be added to the current page.
     *
     * Checks if the current page is a single post and belongs to one of the
     * configured target categories OR has one of the configured target tags.
     *
     * @since 1.0.0
     * @return bool True if schema should be added, false otherwise.
     */
    private function should_add_schema() {
        if (!is_single()) {
            return false;
        }

        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_categories = isset($settings['target_categories']) ? $settings['target_categories'] : array('photolog');
        $target_tags = isset($settings['target_tags']) ? $settings['target_tags'] : array();
        
        // Check categories (OR logic within categories)
        $category_match = false;
        foreach ($target_categories as $category) {
            if (in_category($category) || in_category(strtolower($category)) || in_category(ucfirst(strtolower($category)))) {
                $category_match = true;
                break;
            }
        }
        
        // Check tags (OR logic within tags)
        $tag_match = false;
        if (!empty($target_tags)) {
            foreach ($target_tags as $tag) {
                if (has_tag($tag) || has_tag(strtolower($tag)) || has_tag(ucfirst(strtolower($tag)))) {
                    $tag_match = true;
                    break;
                }
            }
        }
        
        // Return true if either categories OR tags match
        return $category_match || $tag_match;
    }

    /**
     * Checks if a post belongs to one of the target categories or has one of the target tags.
     *
     * @since 1.0.0
     * @param int $post_id The post ID to check.
     * @return bool True if the post is in a target category or has a target tag, false otherwise.
     */
    private function is_target_category($post_id) {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_categories = isset($settings['target_categories']) ? $settings['target_categories'] : array('photolog');
        $target_tags = isset($settings['target_tags']) ? $settings['target_tags'] : array();
        
        // Check categories (OR logic within categories)
        $category_match = false;
        foreach ($target_categories as $category) {
            if (in_category($category, $post_id) || in_category(strtolower($category), $post_id) || in_category(ucfirst(strtolower($category)), $post_id)) {
                $category_match = true;
                break;
            }
        }
        
        // Check tags (OR logic within tags)
        $tag_match = false;
        if (!empty($target_tags)) {
            foreach ($target_tags as $tag) {
                if (has_tag($tag, $post_id) || has_tag(strtolower($tag), $post_id) || has_tag(ucfirst(strtolower($tag)), $post_id)) {
                    $tag_match = true;
                    break;
                }
            }
        }
        
        // Return true if either categories OR tags match
        return $category_match || $tag_match;
    }

    /**
     * Retrieves and builds image schemas from post content and featured image.
     *
     * Scans the current post for images and generates Schema.org ImageObject
     * markup for each image that has ACF photo credit data with enhanced SEO features.
     *
     * @since 1.7.0
     * @return array Array of ImageObject schema arrays.
     */
    private function get_image_schemas() {
        global $post;
        
        $image_ids = array();
        
        // Include featured image to ensure separate ImageObject schema generation
        $featured_image_id = get_post_thumbnail_id();
        if ($featured_image_id) {
            $image_ids[] = $featured_image_id;
        }
        
        // Enhanced image detection: Extract image IDs from post content using both 
        // traditional WordPress image CSS classes and modern Gutenberg data attributes
        preg_match_all('/(wp-image|data-id)-(\d+)/', $post->post_content, $matches);
        if (!empty($matches[2])) {
            $content_image_ids = array_unique($matches[2]);
            $image_ids = array_merge($image_ids, $content_image_ids);
        }
        
        // Remove duplicates and process each unique image
        $image_ids = array_unique($image_ids);
        
        if (empty($image_ids)) {
            return array();
        }
        
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
     * Builds Schema.org ImageObject markup for a single image with enhanced SEO properties.
     *
     * Creates comprehensive ImageObject schema including creator information,
     * licensing details, acquisition page data, and contextual metadata from post
     * tags and categories for improved image search visibility.
     *
     * @since 1.7.0
     * @param int $image_id WordPress attachment ID.
     * @return array|null ImageObject schema array or null if no relevant data.
     */
    private function build_image_schema($image_id) {
        global $post;
        
        // Retrieve ACF field data
        $photographer = get_field('photographer', $image_id);
        $photographer_website = get_field('photographer_website', $image_id);
        $cc_license = get_field('cc_license', $image_id);
        $cc_license_link = get_field('cc_license_link', $image_id);
        $acquire_license_page = get_field('acquire_license_page', $image_id);
        $copyright_notice = get_field('copyright_notice', $image_id);
        
        // Skip images without relevant credit data
        if (!$photographer && !$cc_license) {
            return null;
        }
        
        // Get basic image information
        $image_url = wp_get_attachment_url($image_id);
        $image_meta = wp_get_attachment_metadata($image_id);
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $image_title = get_the_title($image_id);
        $image_caption = wp_get_attachment_caption($image_id);
        
        // Build base schema structure
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            '@id' => $image_url . '#image',
            'contentUrl' => $image_url,
            'url' => $image_url,
            'caption' => $image_caption ?: ($image_alt ?: $image_title),
            'name' => $image_title
        );
        
        // Add image dimensions if available
        if (isset($image_meta['width']) && isset($image_meta['height'])) {
            $schema['width'] = $image_meta['width'];
            $schema['height'] = $image_meta['height'];
        }
        
        // Add file format and size information for better SEO
        if (isset($image_meta['file'])) {
            $file_info = pathinfo($image_meta['file']);
            if (isset($file_info['extension'])) {
                $schema['encodingFormat'] = 'image/' . strtolower($file_info['extension']);
            }
        }
        
        if (isset($image_meta['filesize'])) {
            $schema['contentSize'] = $image_meta['filesize'];
        }
        
        // Add alternative text as description for better accessibility and SEO
        if ($image_alt) {
            $schema['description'] = $image_alt;
        }
        
        // Add creator information
        if ($photographer) {
            $schema['creator'] = array(
                '@type' => 'Person',
                'name' => $photographer
            );
            
            if (!empty($photographer_website)) {
                $schema['creator']['url'] = $photographer_website;
            }
        }
        
        // Process Creative Commons license information
        if ($cc_license) {
            $this->add_license_schema($schema, $cc_license, $cc_license_link, $photographer);
        }
        
        // Handle license acquisition page with fallback support
        $this->add_acquisition_page_schema($schema, $acquire_license_page, $photographer_website, $image_id);
        
        // Add copyright notice with automatic generation
        $this->add_copyright_notice_schema($schema, $copyright_notice, $photographer);
        
        // Add publication date
        $upload_date = get_the_date('c', $image_id);
        if ($upload_date) {
            $schema['datePublished'] = $upload_date;
        }
        
        // NEW: Add contextual information from post for better image SEO
        $this->add_contextual_schema($schema, $post, $image_id);
        
        // Removed: mainEntityOfPage property to avoid conflict with other SEO plugins
        /*
        if ($post && $post->ID) {
            $schema['mainEntityOfPage'] = array(
                '@type' => 'Article',
                '@id' => get_permalink($post->ID) . '#article'
            );
        }
        */
        
        return $schema;
    }

    /**
     * Adds contextual information from the post to image schema for improved SEO.
     *
     * Includes post tags, categories, and other metadata to help search engines
     * understand the context and subject matter of images for better visibility
     * in image search results.
     *
     * @since 1.7.0
     * @param array $schema Reference to the schema array being built.
     * @param object $post The post object containing the image.
     * @param int $image_id The image attachment ID.
     */
    private function add_contextual_schema(&$schema, $post, $image_id) {
        if (!$post || !$post->ID) {
            return;
        }
        
        $about_entities = array();
        
        // Add post categories as 'about' entities
        $categories = get_the_category($post->ID);
        if ($categories) {
            foreach ($categories as $category) {
                $about_entities[] = array(
                    '@type' => 'Thing',
                    'name' => $category->name,
                    'url' => get_category_link($category->term_id),
                    'identifier' => array(
                        '@type' => 'PropertyValue',
                        'name' => 'category',
                        'value' => $category->slug
                    )
                );
            }
        }
        
        // Add post tags as 'about' entities for better topic association
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $about_entities[] = array(
                    '@type' => 'Thing',
                    'name' => $tag->name,
                    'url' => get_tag_link($tag->term_id),
                    'identifier' => array(
                        '@type' => 'PropertyValue',
                        'name' => 'tag',
                        'value' => $tag->slug
                    )
                );
            }
        }
        
        // Add 'about' property if we have entities
        if (!empty($about_entities)) {
            $schema['about'] = $about_entities;
        }
        
        // Add keywords from post tags and categories for better discoverability
        $keywords = array();
        if ($categories) {
            foreach ($categories as $category) {
                $keywords[] = $category->name;
            }
        }
        if ($tags) {
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
        }
        
        if (!empty($keywords)) {
            $schema['keywords'] = implode(', ', array_unique($keywords));
        }
        
        // Add geographic information if available
        $this->add_geographic_schema($schema, $post->ID, $image_id);
        
        // Add subject matter classification
        $this->add_subject_matter_schema($schema, $post, $image_id);
        
        // Add representativeOfPage property for featured images
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if ($image_id == $featured_image_id) {
            $schema['representativeOfPage'] = true;
        }
    }

    /**
     * Adds geographic information to image schema if available.
     *
     * Priority order: 1) ACF location field, 2) EXIF GPS data
     * Only adds contentLocation if data is found, otherwise skips completely.
     *
     * @since 1.7.0
     * @param array $schema Reference to the schema array being built.
     * @param int $post_id The post ID.
     * @param int $image_id The image attachment ID.
     */
    private function add_geographic_schema(&$schema, $post_id, $image_id) {
        $location_added = false;
        
        // Priority 1: Check ACF location field (image first, then post)
        $location = get_field('location', $image_id);
        if (!$location || empty(trim($location))) {
            $location = get_field('location', $post_id);
        }
        
        // If we have a valid location string, use it
        if ($location && !empty(trim($location))) {
            $schema['contentLocation'] = array(
                '@type' => 'Place',
                'name' => sanitize_text_field(trim($location))
            );
            $location_added = true;
        }
        
        // Priority 2: Check for GPS coordinates in EXIF data (only if no location text found)
        if (!$location_added) {
            $image_meta = wp_get_attachment_metadata($image_id);
            if (isset($image_meta['image_meta']['latitude']) && isset($image_meta['image_meta']['longitude'])) {
                $lat = floatval($image_meta['image_meta']['latitude']);
                $lng = floatval($image_meta['image_meta']['longitude']);
                
                // Validate coordinates are within valid ranges and not (0,0)
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat != 0 || $lng != 0)) {
                    $schema['contentLocation'] = array(
                        '@type' => 'Place',
                        'geo' => array(
                            '@type' => 'GeoCoordinates',
                            'latitude' => $lat,
                            'longitude' => $lng
                        )
                    );
                }
            }
        }
        
        // If we have location text AND valid GPS coordinates, add GPS to existing location
        elseif ($location_added) {
            $image_meta = wp_get_attachment_metadata($image_id);
            if (isset($image_meta['image_meta']['latitude']) && isset($image_meta['image_meta']['longitude'])) {
                $lat = floatval($image_meta['image_meta']['latitude']);
                $lng = floatval($image_meta['image_meta']['longitude']);
                
                // Validate and add GPS coordinates to existing location
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat != 0 || $lng != 0)) {
                    $schema['contentLocation']['geo'] = array(
                        '@type' => 'GeoCoordinates',
                        'latitude' => $lat,
                        'longitude' => $lng
                    );
                }
            }
        }
    }

    /**
     * Adds subject matter classification to image schema.
     *
     * Analyzes post content and metadata to determine the primary subject
     * matter of images for better categorization in search results.
     *
     * @since 1.7.0
     * @param array $schema Reference to the schema array being built.
     * @param object $post The post object.
     * @param int $image_id The image attachment ID.
     */
    private function add_subject_matter_schema(&$schema, $post, $image_id) {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $custom_mappings_raw = isset($settings['genre_mappings']) ? $settings['genre_mappings'] : '';
        $custom_mappings = array();
        
        // Parse custom genre mappings from settings
        if (!empty($custom_mappings_raw)) {
            $lines = explode("\n", $custom_mappings_raw);
            foreach ($lines as $line) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $custom_mappings[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        
        // Conservative default mappings - only clear, unambiguous photography genres
        $default_mappings = array(
            'portrait' => 'Portrait Photography',
            'landscape' => 'Landscape Photography',
            'street' => 'Street Photography',
            'street-photography' => 'Street Photography',
            'architecture' => 'Architectural Photography',
            'architectural' => 'Architectural Photography',
            'nature' => 'Nature Photography',
            'travel' => 'Travel Photography',
            'black-and-white' => 'Monochrome Photography',
            'bw' => 'Monochrome Photography',
            'b/w-photography' => 'Monochrome Photography',
            'macro' => 'Macro Photography',
            'wildlife' => 'Wildlife Photography',
            'documentary' => 'Documentary Photography',
            'fine-art' => 'Fine Art Photography',
            'urban' => 'Urban Photography'
        );
        
        // Combine default and custom mappings
        $subject_mappings = array_merge($default_mappings, $custom_mappings);
        
        $detected_subjects = array();
        
        // Analyze tags and categories
        $all_terms = array();
        
        // Get tags
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $all_terms[] = strtolower($tag->slug);
            }
        }
        
        // Get categories
        $categories = get_the_category($post->ID);
        if ($categories) {
            foreach ($categories as $category) {
                $all_terms[] = strtolower($category->slug);
            }
        }
        
        // Match terms to subject matter
        foreach ($subject_mappings as $term => $subject) {
            if (in_array($term, $all_terms)) {
                $detected_subjects[] = $subject;
            }
        }
        
        // Add genre property only if subjects were detected
        if (!empty($detected_subjects)) {
            $schema['genre'] = array_unique($detected_subjects);
        }
        
        // Add representativeOfPage property for featured images
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if ($image_id == $featured_image_id) {
            $schema['representativeOfPage'] = true;
        }
    }

    /**
     * Adds license-related schema properties to an image schema.
     *
     * Processes Creative Commons licenses and adds appropriate license,
     * usageInfo, and creditText properties to the schema.
     *
     * @since 1.1.0
     * @param array $schema Reference to the schema array being built.
     * @param string $cc_license The Creative Commons license designation.
     * @param string $cc_license_link The license URL.
     * @param string $photographer The photographer's name.
     */
    private function add_license_schema(&$schema, $cc_license, $cc_license_link, $photographer) {
        // Clean license name (remove parenthetical descriptions like "(Attribution)")
        $clean_license = preg_replace('/\s*\([^)]+\)/', '', $cc_license);
        
        // Handle Creative Commons licenses
        if (strpos($clean_license, 'CC') === 0) {
            // Add license URL
            $license_url = $cc_license_link ?: $this->get_cc_license_url($clean_license);
            if ($license_url) {
                $schema['license'] = $license_url;
            }
            
            // Add human-readable usage information
            $schema['usageInfo'] = $this->get_cc_usage_description($clean_license);
            
            // Generate credit text for CC licenses
            if ($photographer) {
                $formatted_license = $this->format_cc_license($clean_license);
                $schema['creditText'] = "Photo by " . $photographer . " / " . $formatted_license;
            }
        } else {
            // Handle non-Creative Commons licenses
            $schema['usageInfo'] = $cc_license;
        }
    }

    /**
     * Adds license acquisition page to schema with multiple fallback options.
     *
     * Tries to find a valid license acquisition page using the following priority:
     * 1. Individual image ACF field value
     * 2. ACF field default value
     * 3. Plugin settings default
     * 4. Photographer website as fallback
     *
     * @since 1.1.0
     * @param array $schema Reference to the schema array being built.
     * @param string $acquire_license_page Direct field value.
     * @param string $photographer_website Photographer's website URL.
     * @param int $image_id Image attachment ID for accessing field defaults.
     */
    private function add_acquisition_page_schema(&$schema, $acquire_license_page, $photographer_website, $image_id) {
        $license_page_url = '';

        // Priority 1: Use direct field value if available
        if (!empty($acquire_license_page) && filter_var($acquire_license_page, FILTER_VALIDATE_URL)) {
            $license_page_url = $acquire_license_page;
        } else {
            // Priority 2: Try to get ACF field default value
            $field_object = get_field_object('acquire_license_page', $image_id);
            if (is_array($field_object) && !empty($field_object['default_value'])) {
                $default_value = $field_object['default_value'];
                if (filter_var($default_value, FILTER_VALIDATE_URL)) {
                    $license_page_url = $default_value;
                }
            }
        }

        // Priority 3: Use plugin settings default if still empty
        if (empty($license_page_url)) {
            $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
            $default_license_page = isset($settings['default_license_page']) ? trim($settings['default_license_page']) : '';
            
            if (!empty($default_license_page) && filter_var($default_license_page, FILTER_VALIDATE_URL)) {
                $license_page_url = $default_license_page;
            }
        }

        // Priority 4: Use photographer website as last resort
        if (empty($license_page_url) && !empty($photographer_website) && filter_var($photographer_website, FILTER_VALIDATE_URL)) {
            $license_page_url = $photographer_website;
        }

        // Add to schema if we found a valid URL
        if (!empty($license_page_url)) {
            $schema['acquireLicensePage'] = $license_page_url;
        }
    }

    /**
     * Adds copyright notice to schema with automatic generation fallback.
     *
     * Uses manual copyright notice if provided, otherwise generates one
     * automatically from available credit information.
     *
     * @since 1.1.0
     * @param array $schema Reference to the schema array being built.
     * @param string $copyright_notice Manual copyright notice.
     * @param string $photographer Photographer's name.
     */
    private function add_copyright_notice_schema(&$schema, $copyright_notice, $photographer) {
        if (!empty($copyright_notice)) {
            $schema['copyrightNotice'] = $copyright_notice;
            return;
        }
        
        // Check if automatic generation is enabled
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $auto_generate = isset($settings['auto_generate_copyright']) ? $settings['auto_generate_copyright'] : true;
        
        if (!$auto_generate) {
            return;
        }
        
        // Use credit text if available, otherwise create simple notice
        if (isset($schema['creditText']) && !empty($schema['creditText'])) {
            $schema['copyrightNotice'] = $schema['creditText'];
        } elseif ($photographer) {
            $schema['copyrightNotice'] = "Photo by " . $photographer;
        }
    }

    /**
     * Gets human-readable usage description for Creative Commons licenses.
     *
     * @since 1.1.0
     * @param string $clean_license Clean CC license designation.
     * @return string Usage description or the license designation itself.
     */
    private function get_cc_usage_description($clean_license) {
        $usage_descriptions = array(
            'CC BY' => 'CC BY 4.0 - Attribution required',
            'CC BY 4.0' => 'CC BY 4.0 - Attribution required',
            'CC BY-SA' => 'CC BY-SA 4.0 - Attribution and ShareAlike required',
            'CC BY-SA 4.0' => 'CC BY-SA 4.0 - Attribution and ShareAlike required',
            'CC BY-NC' => 'CC BY-NC 4.0 - Attribution required, non-commercial use only',
            'CC BY-NC 4.0' => 'CC BY-NC 4.0 - Attribution required, non-commercial use only',
            'CC BY-NC-SA' => 'CC BY-NC-SA 4.0 - Attribution and ShareAlike required, non-commercial use only',
            'CC BY-NC-SA 4.0' => 'CC BY-NC-SA 4.0 - Attribution and ShareAlike required, non-commercial use only',
            'CC BY-ND' => 'CC BY-ND 4.0 - Attribution required, no derivatives allowed',
            'CC BY-ND 4.0' => 'CC BY-ND 4.0 - Attribution required, no derivatives allowed',
            'CC BY-NC-ND' => 'CC BY-NC-ND 4.0 - Attribution required, non-commercial use only, no derivatives allowed',
            'CC BY-NC-ND 4.0' => 'CC BY-NC-ND 4.0 - Attribution required, non-commercial use only, no derivatives allowed',
            'CC0' => 'CC0 - Public Domain, no rights reserved'
        );
        
        $formatted_license = $this->format_cc_license($clean_license);
        
        return isset($usage_descriptions[$clean_license]) ? $usage_descriptions[$clean_license] :
               (isset($usage_descriptions[$formatted_license]) ? $usage_descriptions[$formatted_license] : $formatted_license);
    }

    /**
     * Formats Creative Commons license to include version number.
     *
     * Ensures CC licenses include the 4.0 version number for consistency.
     *
     * @since 1.0.0
     * @param string $license The raw license designation.
     * @return string Formatted license with version number.
     */
    private function format_cc_license($license) {
        $clean_license = preg_replace('/\s*\([^)]+\)/', '', $license);
        
        if (strpos($clean_license, 'CC') === 0 && strpos($clean_license, '4.0') === false && $clean_license !== 'CC0') {
            return $clean_license . ' 4.0';
        }
        
        return $clean_license;
    }

    /**
     * Gets the official URL for a Creative Commons license.
     *
     * Maps license designations to their official Creative Commons URLs.
     *
     * @since 1.0.0
     * @param string $license The license designation.
     * @return string The official license URL or empty string if not found.
     */
    private function get_cc_license_url($license) {
        $urls = array(
            'CC BY' => 'https://creativecommons.org/licenses/by/4.0/',
            'CC BY-SA' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'CC BY-NC' => 'https://creativecommons.org/licenses/by-nc/4.0/',
            'CC BY-NC-SA' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            'CC BY-ND' => 'https://creativecommons.org/licenses/by-nd/4.0/',
            'CC BY-ND 4.0' => 'https://creativecommons.org/licenses/by-nd/4.0/',
            'CC BY-NC-ND' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
            'CC BY-NC-ND 4.0' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
            'CC0' => 'https://creativecommons.org/publicdomain/zero/1.0/'
        );
        
        $clean_license = preg_replace('/\s*\([^)]+\)/', '', $license);
        
        return isset($urls[$clean_license]) ? $urls[$clean_license] : '';
    }

    /**
     * Builds Schema.org Article markup for the current post.
     *
     * Creates Article schema with basic post information, author details,
     * and category relationships.
     *
     * @since 1.0.0
     * @return array|null Article schema array or null if no post available.
     */
    private function get_article_schema() {
        global $post;
        
        if (!is_object($post)) {
            return null;
        }
        
        // Get featured image URL for the article
        $featured_image_url = null;
        $featured_image_id = get_post_thumbnail_id();
        if ($featured_image_id) {
            $featured_image_url = wp_get_attachment_url($featured_image_id);
        }
        
        // Build Article schema
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
        
        // Add featured image if available
        if ($featured_image_url) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $featured_image_url
            );
        }
        
        // Add categories as 'about' property
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
     * Enhances WordPress sitemap entries with image credit data.
     *
     * Adds image metadata including credits and licenses to sitemap entries
     * for better search engine understanding.
     *
     * @since 1.0.0
     * @param array $sitemap_entry The original sitemap entry.
     * @param object $post The post object.
     * @param string $post_type The post type.
     * @return array Modified sitemap entry.
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
     * Extracts image data from post content for sitemap inclusion.
     *
     * @since 1.7.0
     * @param object $post The post object to process.
     * @return array Array of image data for sitemap.
     */
    private function get_post_images_for_sitemap($post) {
        // Enhanced image detection: Use improved regex to match both traditional 
        // WordPress image CSS classes and modern Gutenberg data attributes
        preg_match_all('/(wp-image|data-id)-(\d+)/', $post->post_content, $matches);
        if (empty($matches[2])) {
            return array();
        }
        
        $image_ids = array_unique($matches[2]);
        $images = array();
        
        foreach ($image_ids as $image_id) {
            $photographer = get_field('photographer', $image_id);
            $cc_license = get_field('cc_license', $image_id);
            $location = get_field('location', $image_id);
            
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

                if ($location) {
                    $image_data['geo_location'] = $location;
                }
                
                $images[] = $image_data;
            }
        }
        
        return $images;
    }

    /**
     * Adds the plugin settings page to WordPress admin menu.
     *
     * @since 1.0.0
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
     * Registers plugin settings and creates settings sections and fields.
     *
     * @since 1.0.0
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
            'target_tags',
            __('Target Tags', self::TEXT_DOMAIN),
            array($this, 'target_tags_callback'),
            self::PLUGIN_SLUG,
            'acf_photo_credits_main'
        );
        
        add_settings_field(
            'auto_generate_copyright',
            __('Auto-generate Copyright Notice', self::TEXT_DOMAIN),
            array($this, 'auto_generate_copyright_callback'),
            self::PLUGIN_SLUG,
            'acf_photo_credits_main'
        );
        
        add_settings_field(
            'default_license_page',
            __('Default License Acquisition Page', self::TEXT_DOMAIN),
            array($this, 'default_license_page_callback'),
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

        add_settings_field(
            'genre_mappings',
            __('Custom Genre Mappings', self::TEXT_DOMAIN),
            array($this, 'genre_mappings_callback'),
            self::PLUGIN_SLUG,
            'acf_photo_credits_main'
        );
    }

    /**
     * Settings section description callback.
     *
     * @since 1.0.0
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure which categories and tags should include photo credits schema markup and how the plugin should behave. Schema will be generated if the post matches ANY selected category OR ANY selected tag.', self::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Renders the target categories setting field.
     *
     * @since 1.0.0
     */
    public function target_categories_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_categories = isset($settings['target_categories']) ? $settings['target_categories'] : array('photolog');
        $categories = get_categories();
        
        echo '<fieldset>';
        foreach ($categories as $category) {
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
     * Renders the target tags setting field.
     *
     * @since 1.1.0
     */
    public function target_tags_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $target_tags = isset($settings['target_tags']) ? $settings['target_tags'] : array();
        $tags = get_tags();
        
        echo '<fieldset>';
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $checked = (in_array($tag->slug, $target_tags) || in_array($tag->name, $target_tags)) ? 'checked' : '';
                echo '<label>';
                echo '<input type="checkbox" name="' . self::PLUGIN_SLUG . '_settings[target_tags][]" value="' . esc_attr($tag->slug) . '" ' . $checked . '>';
                echo ' ' . esc_html($tag->name) . ' (' . esc_html($tag->slug) . ')';
                echo '</label><br>';
            }
        } else {
            echo '<p><em>' . __('No tags found. Tags will appear here once you create them.', self::TEXT_DOMAIN) . '</em></p>';
        }
        echo '</fieldset>';
        echo '<p class="description">' . __('Select which tags should include photo credits in schema markup. Schema will be generated if the post has ANY selected category OR ANY selected tag.', self::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Renders the auto-generate copyright setting field.
     *
     * @since 1.1.0
     */
    public function auto_generate_copyright_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $auto_generate = isset($settings['auto_generate_copyright']) ? $settings['auto_generate_copyright'] : true;
        
        echo '<input type="checkbox" name="' . self::PLUGIN_SLUG . '_settings[auto_generate_copyright]" value="1" ' . checked(1, $auto_generate, false) . '>';
        echo '<p class="description">' . __('Automatically generate copyright notice from photographer name and license if not manually set.', self::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Renders the default license page setting field.
     *
     * @since 1.1.0
     */
    public function default_license_page_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $default_page = isset($settings['default_license_page']) ? $settings['default_license_page'] : '';
        
        echo '<input type="url" name="' . self::PLUGIN_SLUG . '_settings[default_license_page]" value="' . esc_attr($default_page) . '" class="regular-text">';
        echo '<p class="description">' . __('Default URL for license acquisition (used when individual image license page is not set).', self::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Renders the sitemap data inclusion setting field.
     *
     * @since 1.0.0
     */
    public function include_sitemap_data_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $include_sitemap = isset($settings['include_sitemap_data']) ? $settings['include_sitemap_data'] : true;
        
        echo '<input type="checkbox" name="' . self::PLUGIN_SLUG . '_settings[include_sitemap_data]" value="1" ' . checked(1, $include_sitemap, false) . '>';
        echo '<p class="description">' . __('Include image credit information in WordPress sitemaps.', self::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Renders the custom genre mappings setting field.
     *
     * @since 1.7.0
     */
    public function genre_mappings_callback() {
        $settings = get_option(self::PLUGIN_SLUG . '_settings', array());
        $mappings = isset($settings['genre_mappings']) ? $settings['genre_mappings'] : '';
        
        echo '<textarea name="' . self::PLUGIN_SLUG . '_settings[genre_mappings]" rows="10" cols="50" class="large-text code">' . esc_textarea($mappings) . '</textarea>';
        echo '<p class="description">' . __('Enter custom genre mappings as "tag=Genre Name" on separate lines. Example: street=Urban Photography', self::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Renders the main plugin settings page.
     *
     * @since 1.0.0
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
            
            <h2><?php _e('Required ACF Fields', self::TEXT_DOMAIN); ?></h2>
            <p><?php _e('For the plugin to work properly, create an ACF field group with the following fields for images/attachments:', self::TEXT_DOMAIN); ?></p>
            <ul>
                <li><strong>photographer</strong> (Text field) - Photographer name</li>
                <li><strong>photographer_website</strong> (URL field) - Photographer website</li>
                <li><strong>cc_license</strong> (Select field) - Creative Commons license</li>
                <li><strong>cc_license_link</strong> (URL field) - License URL (can be auto-filled)</li>
                <li><strong>acquire_license_page</strong> (URL field) - Page to acquire license</li>
                <li><strong>copyright_notice</strong> (Text field) - Custom copyright notice</li>
                <li><strong>location</strong> (Text field) - Geographic location (optional)</li>
            </ul>
            
            <h2><?php _e('Schema Generation Logic', self::TEXT_DOMAIN); ?></h2>
            <p><?php _e('Schema markup is generated when:', self::TEXT_DOMAIN); ?></p>
            <ul>
                <li><?php _e('Post belongs to ANY selected category, OR', self::TEXT_DOMAIN); ?></li>
                <li><?php _e('Post has ANY selected tag', self::TEXT_DOMAIN); ?></li>
            </ul>
            <p><strong><?php _e('Example:', self::TEXT_DOMAIN); ?></strong> <?php _e('If you select categories "photolog" and "travel", and tags "photography" and "creative-commons", then schema will be generated for posts that:', self::TEXT_DOMAIN); ?></p>
            <ul>
                <li><?php _e('Are in category "photolog" OR "travel", OR', self::TEXT_DOMAIN); ?></li>
                <li><?php _e('Have tag "photography" OR "creative-commons"', self::TEXT_DOMAIN); ?></li>
            </ul>
            
            <h2><?php _e('New Features (v1.7.0)', self::TEXT_DOMAIN); ?></h2>
            <ul>
                <li><strong><?php _e('Enhanced Image SEO:', self::TEXT_DOMAIN); ?></strong> <?php _e('ImageObjects now include "about" entities and "keywords" from post tags and categories for better search visibility.', self::TEXT_DOMAIN); ?></li>
                <li><strong><?php _e('Geographic Data:', self::TEXT_DOMAIN); ?></strong> <?php _e('Location information from ACF fields and EXIF data with intelligent priority system.', self::TEXT_DOMAIN); ?></li>
                <li><strong><?php _e('Genre Detection:', self::TEXT_DOMAIN); ?></strong> <?php _e('Automatic photography genre classification with custom mapping support.', self::TEXT_DOMAIN); ?></li>
                <li><strong><?php _e('Technical Metadata:', self::TEXT_DOMAIN); ?></strong> <?php _e('File format, size, dimensions, and enhanced image properties.', self::TEXT_DOMAIN); ?></li>
                <li><strong><?php _e('Featured Image Support:', self::TEXT_DOMAIN); ?></strong> <?php _e('Featured images marked as "representativeOfPage" for better SEO.', self::TEXT_DOMAIN); ?></li>
            </ul>
            
            <h2><?php _e('Workflow Recommendation', self::TEXT_DOMAIN); ?></h2>
            <p><?php _e('For best results, follow this workflow:', self::TEXT_DOMAIN); ?></p>
            <ol>
                <li><?php _e('Upload images to Media Library first', self::TEXT_DOMAIN); ?></li>
                <li><?php _e('Fill out ACF fields for each image (photographer, license, location) and save', self::TEXT_DOMAIN); ?></li>
                <li><?php _e('Insert images into posts using "Add Media" button or Gutenberg blocks', self::TEXT_DOMAIN); ?></li>
                <li><?php _e('Assign appropriate categories and/or tags to your posts', self::TEXT_DOMAIN); ?></li>
                <li><?php _e('Publish posts - schema markup will be automatically generated', self::TEXT_DOMAIN); ?></li>
                <li><strong><?php _e('Important:', self::TEXT_DOMAIN); ?></strong> <?php _e('When updating image ACF data later, re-save the posts containing those images to refresh schema markup', self::TEXT_DOMAIN); ?></li>
            </ol>
            
            <h2><?php _e('Schema Properties Generated', self::TEXT_DOMAIN); ?></h2>
            <p><?php _e('The plugin generates comprehensive schemas including:', self::TEXT_DOMAIN); ?></p>
            
            <h3><?php _e('Article Schema:', self::TEXT_DOMAIN); ?></h3>
            <p>
                <div class="notice notice-warning inline">
                    <p><?php _e('Note: Article schema generation has been disabled to avoid conflicts with SEO plugins like Yoast SEO or Rank Math. A separate ImageObject schema will be generated.', self::TEXT_DOMAIN); ?></p>
                </div>
            </p>
            
            <h3><?php _e('ImageObject Schema:', self::TEXT_DOMAIN); ?></h3>
            <ul>
                <li><strong>Basic Properties:</strong> contentUrl, caption, name, dimensions, filesize, encodingFormat</li>
                <li><strong>Creator Info:</strong> photographer name and website</li>
                <li><strong>Licensing:</strong> Creative Commons licenses with proper URLs and usage descriptions</li>
                <li><strong>Context:</strong> related tags and categories via "about" entities and "keywords" property</li>
                <li><strong>Location:</strong> geographic information and GPS coordinates via "contentLocation"</li>
                <li><strong>Genre:</strong> photography style classification with custom mapping support</li>
                <li><strong>Acquisition:</strong> license purchase page information with intelligent fallbacks</li>
                <li><strong>Copyright:</strong> automatic copyright notice generation</li>
                <li><strong>Relationship:</strong> (no longer generated) mainEntityOfPage linking to parent article</li>
            </ul>
            
            <h2><?php _e('Testing Your Schema', self::TEXT_DOMAIN); ?></h2>
            <p><?php _e('Test your schema markup with these tools:', self::TEXT_DOMAIN); ?></p>
            <ul>
                <li><a href="https://search.google.com/test/rich-results" target="_blank"><?php _e('Google Rich Results Test', self::TEXT_DOMAIN); ?></a></li>
                <li><a href="https://validator.schema.org/" target="_blank"><?php _e('Schema.org Validator', self::TEXT_DOMAIN); ?></a></li>
            </ul>
            
            <div class="notice notice-success inline">
                <p><strong><?php _e('Reliable Schema Generation:', self::TEXT_DOMAIN); ?></strong> 
                <?php _e('This version focuses on generating a clean, comprehensive ImageObject schema that complements, rather than conflicts with, your existing SEO setup.', self::TEXT_DOMAIN); ?></p>
            </div>
            
            <div class="notice notice-warning inline">
                <p><strong><?php _e('Schema Update Reminder:', self::TEXT_DOMAIN); ?></strong> 
                <?php _e('When you update ACF data for images, remember to re-save the posts containing those images to refresh schema markup', self::TEXT_DOMAIN); ?></p>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new ACF_Photo_Credits_Schema();
?>
