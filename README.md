# ACF Photo Credits Schema

A WordPress plugin that adds Schema.org markup for photographer credits and Creative Commons licenses from Advanced Custom Fields.

This plugin adds structured data to your website that tells search engines about image attribution and licensing information. This helps search engines understand photographer credits and usage rights for images on your website.

## Features

- Automatically generates Schema.org ImageObject markup from ACF fields
- Supports photographer attribution and Creative Commons licensing
- Integrates with WordPress sitemaps
- Compatible with existing SEO plugins
- Auto-fills Creative Commons license URLs
- Configurable target categories
- Multilingual ready

## Installation

1. Download the plugin files
2. Upload the `acf-photo-credits-schema` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings → Photo Credits Schema to configure

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Advanced Custom Fields (free version)
- Works alongside existing SEO plugins

## Setup

### 1. Create ACF Field Group

Create a new ACF field group called "Photo Credits" with these fields:

- **Photographer** (Text Field) - field name: `photographer`
- **Photographer Website** (URL Field) - field name: `photographer_website`  
- **CC License** (Select Field) - field name: `cc_license`
- **CC License Link** (URL Field) - field name: `cc_license_link`

**Location Rules:** Set to `Attachment` equals `image`

### 2. Configure Plugin

Navigate to Settings → Photo Credits Schema in your WordPress admin to:

- Choose target categories where schema should be applied
- Enable/disable auto-filling of CC license links
- Configure sitemap integration

## Usage

Once configured, the plugin will automatically:

1. **Generate Schema Markup** - Add JSON-LD structured data to posts in target categories
2. **Enhance Meta Tags** - Include photographer credits in Open Graph and Twitter Card meta tags
3. **Update Sitemaps** - Add image attribution data to WordPress XML sitemaps
4. **Auto-fill License URLs** - Automatically populate Creative Commons license links

## Generated Schema Example

The plugin generates JSON-LD structured data like this:

```json
{
  "@context": "https://schema.org",
  "@type": "ImageObject",
  "contentUrl": "https://yoursite.com/wp-content/uploads/photo.jpg",
  "creator": {
    "@type": "Person",
    "name": "Photographer Name",
    "url": "https://photographer-website.com"
  },
  "license": "https://creativecommons.org/licenses/by/4.0/",
  "usageInfo": "CC BY",
  "creditText": "Photo by Photographer Name / CC BY"
}
```

## Supported Creative Commons Licenses

- CC BY (Attribution)
- CC BY-SA (Attribution-ShareAlike)  
- CC BY-NC (Attribution-NonCommercial)
- CC BY-NC-SA (Attribution-NonCommercial-ShareAlike)
- CC BY-ND (Attribution-NoDerivatives)
- CC BY-NC-ND (Attribution-NonCommercial-NoDerivatives)
- CC0 (Public Domain)
- All Rights Reserved

## Testing

Test your schema markup with these tools:

- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)

## Compatibility

- Works with Yoast SEO
- Works with RankMath
- Compatible with WordPress multisite
- Translation ready

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please create an issue on the [GitHub repository](https://github.com/thomasgerdes/acf-photo-credits-schema).

## Author

**Thomas Gerdes**
- Website: [https://thomasgerdes.de](https://thomasgerdes.de)
- GitHub: [@thomasgerdes](https://github.com/thomasgerdes)

## Changelog

### 1.0.0
- Initial release
- Schema.org ImageObject markup generation
- Creative Commons license support
- WordPress sitemap integration
- Admin settings interface
