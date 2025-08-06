# ACF Photo Credits Schema WordPress Plugin

A WordPress plugin that automatically generates Schema.org markup for photographer credits and Creative Commons licenses from Advanced Custom Fields data.

## Features

- **Automatic Schema.org generation**: Creates ImageObject and Article markup for enhanced SEO
- **Creative Commons support**: Full support for all CC license types with automatic URL generation
- **Enhanced Gutenberg compatibility**: Supports both traditional WordPress images and modern Block Editor formats
- **Featured image integration**: Includes featured images in schema generation
- **Flexible configuration**: Target specific categories and/or tags with OR logic
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
- **Target Tags**: Select which post tags should include schema markup
- **Auto-generate Copyright**: Automatically create copyright notices from credit data
- **Default License Page**: Fallback URL for license acquisition
- **Include Sitemap Data**: Add image credits to WordPress XML sitemaps

**Schema Generation Logic**: Schema markup is generated when posts match ANY selected category OR ANY selected tag (OR logic).

## Usage

### Basic Workflow

1. **Upload images** to Media Library first
2. **Add ACF data** to each image (photographer, license, etc.) and save
3. **Insert images** into posts using the media picker or Gutenberg blocks
4. **Assign categories and/or tags** to posts according to your plugin settings
5. **Publish posts** - schema markup is automatically generated

### Schema Generation Logic

Schema markup is generated when posts meet ANY of these conditions:
- Post belongs to ANY selected target category, OR
- Post has ANY selected target tag

**Example**: If you configure categories "photolog" and "travel", plus tags "photography" and "creative-commons", then schema will be generated for posts that:
- Are in category "photolog" OR "travel", OR  
- Have tag "photography" OR "creative-commons"

> **Important**: Always upload images to Media Library before adding ACF data. Due to how ACF works, the photo credit fields are only available after the image has been uploaded and saved as an attachment.

### Workflow Recommendations

For best results, follow this recommended workflow:

1. **Upload images to Media Library first** - This ensures ACF fields are available
2. **Fill out ACF fields for each image and save** - Complete all photographer and license information
3. **Insert images into posts** - Use "Add Media" button, Gutenberg Image blocks, or Gallery blocks
4. **Assign appropriate categories and/or tags** - Configure posts to match your plugin settings
5. **Publish posts** - Schema markup will be automatically generated based on your category and tag settings

> **Note**: The plugin detects images in post content using WordPress CSS classes (`wp-image-ID`) and modern Gutenberg data attributes (`data-id`). Images inserted from the Media Library or Gutenberg blocks will automatically have these identifiers.

### Gutenberg Compatibility

Version 1.3.0+ includes enhanced support for:

- **Image blocks**: Traditional single image blocks
- **Gallery blocks**: Multi-image gallery layouts
- **Media & Text blocks**: Combined text and image blocks
- **Cover blocks**: Background image detection
- **Classic Editor**: Full backward compatibility

The plugin automatically detects images regardless of how they were inserted into your content.

### Updating Existing Posts

For existing sites with many posts, you can trigger schema regeneration by:

- **Manual method**: Edit and re-save posts with updated image data
- **Batch method**: Use a one-time function in `functions.php` to programmatically update posts (advanced users)

> **Advanced**: Batch updates can be automated via custom PHP functions. Always backup your database before running batch operations.

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
- **Image Detection**: Enhanced regex supporting both traditional WordPress and Gutenberg formats
- **Performance**: Optimized batch processing of image data
- **Compatibility**: WordPress 5.0+ and ACF free version
- **Hook Priority**: Optimized for compatibility with SEO plugins

## Support

- **Documentation**: See plugin settings page for detailed field requirements
- **Issues**: Report bugs via GitHub issues
- **License**: MIT License

## Development

This plugin was created with AI assistance and follows WordPress coding standards. Contributions are welcome!

### Version History

- **1.3.0**: Enhanced Gutenberg compatibility, improved image detection for Gallery blocks and modern Block Editor formats
- **1.2.0**: Added tag support with OR logic, enhanced schema generation rules
- **1.1.0**: Enhanced schema generation, copyright automation, acquisition page fallbacks
- **1.0.0**: Initial release with basic ImageObject schema

## Credits

- **Author**: Thomas Gerdes
- **License**: MIT
- **Repository**: [GitHub](https://github.com/thomasgerdes/acf-photo-credits-schema)
