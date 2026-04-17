# TPUploader

TPUploader adds multi-file ZIP upload support for custom Botble CMS plugins and themes directly from the admin panel.

## Features

- Upload multiple custom plugin ZIP files from the plugins screen
- Upload multiple custom theme ZIP files from the themes screen
- Replace existing plugins and run Botble's plugin update protocol by default
- Replace existing themes and refresh theme assets by default
- Skip the update or refresh step when you only need to upload or replace files
- Optional plugin activation immediately after installation
- Theme uploads do not auto-activate uploaded themes
- AJAX upload results without a full page reload
- Per-file upload status and log output for each uploaded ZIP
- ZIP validation before install
- `plugin.json` and `theme.json` manifest validation
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

- Go to `Admin -> Plugins` to upload one or more plugin ZIP files
- Go to `Admin -> Themes` to upload one or more theme ZIP files
- Select `No update, just replace files` only when you want to skip update hooks, migrations, asset publishing, or refresh steps
- Select `Activate immediately after upload` to activate uploaded plugins automatically

## Notes

- Plugin ZIPs must contain a valid `plugin.json`
- Theme ZIPs must contain a valid `theme.json`
- New plugins are installed from their ZIP archive
- New themes are installed from their ZIP archive
- Existing plugin folders are replaced through Botble's update protocol by default
- Existing theme folders are replaced and their assets are published by default
- `No update, just replace files` uploads or replaces the existing plugin or theme folder without running the update or refresh step
- Multi-upload submits each ZIP through AJAX and shows the result without reloading the page
- Each uploaded ZIP has its own status and toggleable log message

## License

MIT
