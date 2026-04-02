# TPUploader

TPUploader adds ZIP upload support for custom Botble CMS plugins and themes directly from the admin panel.

## Features

- Upload custom plugins from a ZIP file in the plugins screen
- Upload custom themes from a ZIP file in the themes screen
- Optional activation immediately after installation
- ZIP validation before install
- Protection against unsafe archive paths
- UI injected from the plugin without modifying Botble core packages

## Requirements

- Botble CMS `7.6.3` or higher
- PHP `8.2` or higher
- ZIP extension enabled

## Installation

1. Copy the plugin into `platform/plugins/tpuploader`
2. Activate it from Botble admin or run:

```bash
php artisan cms:plugin:activate tpuploader
```

## Usage

After activation:

- Go to `Admin -> Plugins` to upload a plugin ZIP
- Go to `Admin -> Themes` to upload a theme ZIP
- Optionally enable activation during upload

## Notes

- Plugin ZIPs must contain a valid `plugin.json`
- Theme ZIPs must contain a valid `theme.json`
- Existing plugin or theme folders are not overwritten

## License

MIT
