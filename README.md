== Description ==

This plugin helps migrate Advanced Custom Fields (ACF) Gutenberg blocks across different WordPress environments effortlessly. When copying a block, it replaces image attachment IDs with URLs. When saving a post in the destination environment, it detects these image URLs, downloads and uploads the images to the media library, and replaces the URLs with new local attachment IDs in the block content.


== Usage ==

1. In the source WordPress environment, open the post or page editor and click on any ACF Gutenberg block.

2. A new toolbar button labeled "Copy block with image URLs" appears above the selected block. Click this button to copy the block content to your clipboard with all image attachment IDs replaced by URLs.

   ![Copy Button](./screenshot.png)

3. In the destination WordPress environment, open the editor and paste the copied block content (Ctrl+V or Cmd+V) into the post or page.

4. Save or publish the post. The plugin will automatically detect all specially tagged image URLs, download those images to the destination server, upload them to the media library, and replace the URLs with new attachment IDs in the block content.

5. The block will render correctly with images sourced from the local media library.
