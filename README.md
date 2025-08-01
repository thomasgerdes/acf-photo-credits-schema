# ACF Photo Credits Schema

A WordPress plugin that automatically generates Schema.org markup for photographer credits and Creative Commons licenses from Advanced Custom Fields data.

## Features

- **Automatic Schema.org generation**: Creates ImageObject and Article markup for enhanced SEO
- **Creative Commons support**: Full support for all CC license types with automatic URL generation
- **Featured image integration**: Includes featured images in schema generation
- **Flexible configuration**: Target specific categories and customize behavior
- **Sitemap enhancement**: Adds image credit data to WordPress XML sitemaps
- **Automatic copyright generation**: Creates copyright notices from photographer and license data
- **License acquisition pages**: Configurable URLs for license purchases with intelligent fallbacks

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Advanced Custom Fields (free version)

## Installation

1. Download the plugin files
2. Upload the `acf-photo-credits-schema` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Create ACF field group with required fields (see Configuration below)
5. Configure plugin settings under Settings → Photo Credits Schema

## Configuration

### Required ACF Fields

Create an ACF field group for **Attachments** with these fields:

| Field Name | Type | Description | Required |
|------------|------|-------------|----------|
| `photographer` | Text | Photographer name | Yes* |
| `photographer_website` | URL | Photographer website | No |
| `cc_license` | Select | Creative Commons license | Yes* |
| `cc_license_link` | URL | License URL (auto-filled) | No |
| `acquire_license_page` | URL | License purchase page | No |
| `copyright_notice` | Text | Custom copyright text | No |

*At least one of `photographer` or `cc_license` is required for schema generation.

### CC License Options

Configure your CC License select field with these values:

- `CC BY` - Attribution
- `CC BY-SA` - Attribution-ShareAlike  
- `CC BY-NC` - Attribution-NonCommercial
- `CC BY-NC-SA` - Attribution-NonCommercial-ShareAlike
- `CC BY-ND` - Attribution-NoDerivatives
- `CC BY-NC-ND` - Attribution-NonCommercial-NoDerivatives
- `CC0` - Public Domain

### Plugin Settings

- **Target Categories**: Select which post categories should include schema markup
- **Auto-generate Copyright**: Automatically create copyright notices from credit data
- **Default License Page**: Fallback URL for license acquisition
- **Include Sitemap Data**: Add image credits to WordPress XML sitemaps

## Usage

### Basic Workflow

1. **Upload images** to Media Library first
2. **Add ACF data** to each image (photographer, license, etc.) and save
3. **Insert images** into posts using the media picker
4. **Publish posts** - schema markup is automatically generated

> **Important**: Always upload images to the Media Library before adding ACF data. Due to how ACF works, the photo credit fields are only available after the image has been uploaded and saved as an attachment.

### Workflow Recommendations

For best results, follow this recommended workflow:

1. **Upload images to Media Library first** - This ensures ACF fields are available
2. **Fill out ACF fields for each image and save** - Complete all photographer and license information
3. **Insert images into posts using "Add Media" button** - This maintains the `wp-image-ID` classes needed for schema detection
4. **Publish posts** - Schema markup will be automatically generated based on your category settings

> **Note**: The plugin detects images in post content using WordPress CSS classes (`wp-image-ID`). Images inserted from the Media Library will automatically have these classes.

### Updating Existing Posts

For existing sites with many posts, you can trigger schema regeneration by:

- **Manual method**: Edit and re-save posts with updated image data
- **Batch method**: Use a one-time function in `functions.php` to programmatically update posts (advanced users)

> **Advanced**: Batch updates can be automated via custom PHP functions. Always backup your database before running batch operations.

### Schema Output

### Schema Output

The plugin generates structured data including:

```json
{
  "@context": "https://schema.org",
  "@type": "ImageObject",
  "contentUrl": "https://example.com/image.jpg",
  "creator": {
    "@type": "Person", 
    "name": "Photographer Name"
  },
  "license": "https://creativecommons.org/licenses/by/4.0/",
  "creditText": "Photo by Photographer Name / CC BY 4.0",
  "acquireLicensePage": "https://example.com/licenses"
}
```

## License Acquisition Fallback

The plugin uses an intelligent fallback system for license acquisition pages:

1. Individual image `acquire_license_page` field
2. ACF field default value  
3. Plugin settings default license page
4. Photographer website as last resort

## Testing

Validate your schema markup with these tools:

- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)

## Technical Details

- **Schema Types**: ImageObject, Article
- **Output Format**: JSON-LD in document head
- **Performance**: Optimized batch processing of image data
- **Compatibility**: WordPress 5.0+ and ACF free version

## Support

- **Documentation**: See plugin settings page for detailed field requirements
- **Issues**: Report bugs via GitHub issues
- **License**: MIT License

## Development

This plugin was created with AI assistance and follows WordPress coding standards. Contributions are welcome!

### Version History

- **1.1.0**: Enhanced schema generation, copyright automation, acquisition page fallbacks
- **1.0.0**: Initial release with basic ImageObject schema

## Credits

- **Author**: Thomas Gerdes
- **License**: MIT
- **Repository**: [GitHub](https://github.com/thomasgerdes/acf-photo-credits-schema)
