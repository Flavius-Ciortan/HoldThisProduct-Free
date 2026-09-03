# WordPress.org Directory Assets

This repository folder is the staging location for optional WordPress.org directory artwork. It is excluded from the release ZIP. After the plugin is approved, copy finalized files to the top-level `/assets` directory of the assigned WordPress.org SVN repository, not to `/trunk/assets`.

## Supported Assets

Directory artwork is optional for initial code submission. Use these exact names when publishing it:

### Icons
- `icon-128x128.png` - Small icon (128x128px)
- `icon-256x256.png` - Large icon (256x256px)

### Banners
- `banner-772x250.png` - Standard banner (772x250px)
- `banner-1544x500.png` - Retina banner (1544x500px)

### Screenshots

Only add a screenshot section to `readme.txt` after the matching files exist here. Use sequential names:
- `screenshot-1.png` - Plugin settings page
- `screenshot-2.png` - Product page with Reserve button
- `screenshot-3.png` - Reservation modal
- `screenshot-4.png` - My Account reservations page
- `screenshot-5.png` - Admin reservations dashboard
- `screenshot-6.png` - Product inventory reservations
- `screenshot-7.png` - Basic reservation analytics

## Design Guidelines

### Icon
- Use the established blue, navy, and amber product palette
- Should be recognizable at small sizes
- Use the existing hand-and-package mark without small text
- PNG format with transparent background

### Banner
- Feature product reservation concept
- Use the established blue, navy, and amber product palette
- Use the product name "Hold This Product"
- Keep essential artwork and text inside the central safe area

### Screenshots
- Use actual plugin screenshots
- Crop each image to the relevant interface rather than including unnecessary browser chrome
- Show real functionality, not mockups
- Include clear examples of features in action

## Upload Instructions

When submitting to WordPress.org:
1. Do not include these assets in the plugin ZIP.
2. Upload them to the WordPress.org SVN repository's top-level `/assets` directory.
3. Keep screenshot numbering identical to the captions in `readme.txt`.
4. Confirm image dimensions and file-size limits against the current Plugin Handbook before upload.

## Notes

The local release builder excludes this entire folder through `.distignore`.
