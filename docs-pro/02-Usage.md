# TP Uploader Usage

Once installed and activated, TP Uploader runs largely behind the scenes, intercepting and optimizing media uploads throughout your Botble CMS interface.

## Configuration

Navigate to **Settings -> Media** (or **Settings -> TP Uploader** if available) to configure the upload limits and chunk sizes.

- Ensure your `PHP.ini` settings (`upload_max_filesize`, `post_max_size`) support the chunk chunks set in the configuration.
- Storage targets (S3, local, etc.) are respected based on your global environment settings.

## Developer Integration

You can utilize TP Uploader directly in your own plugins by calling its exposed helper functions or service classes when handling file uploads in custom forms.
