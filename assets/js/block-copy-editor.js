/**
 * ACF Block Copy Editor - Frontend functionality
 * Adds copy button to block toolbar and handles ID resolution
 */
const { createElement, Fragment } = wp.element;
(function () {
  const { registerBlockType } = wp.blocks;
  const { createHigherOrderComponent } = wp.compose;
  const { Fragment } = wp.element;
  const { BlockControls, useBlockProps } = wp.blockEditor;
  const { ToolbarGroup, ToolbarButton } = wp.components;
  const { select, subscribe } = wp.data;

  // Store for clipboard data
  let clipboardData = {
    blockContent: null,
    attachmentIds: [],
    isProcessing: false,
  };

  /**
   * Extract attachment IDs from block content
   * Looks for numeric values that are likely attachment IDs
   */
  function extractAttachmentIds(blockContent) {
    const attachmentIds = [];

    // Match all numeric IDs in JSON data attributes
    // This regex finds numbers that could be attachment IDs
    const jsonRegex = /"data"\s*:\s*({[\s\S]*?})\s*,\s*"mode"/g;
    let match;

    while ((match = jsonRegex.exec(blockContent)) !== null) {
      try {
        const data = JSON.parse(match[1]);
        extractIdsFromData(data, attachmentIds);
      } catch (e) {
        console.error('Error parsing block JSON:', e);
      }
    }

    return [...new Set(attachmentIds)]; // Return unique IDs
  }

  /**
   * Recursively extract numeric IDs from data object
   */
  function extractIdsFromData(obj, ids, visited = new Set()) {
    // Prevent circular references
    if (visited.has(obj)) return;
    if (typeof obj === 'object' && obj !== null) {
      visited.add(obj);
    }

    if (typeof obj !== 'object' || obj === null) return;

    for (const key in obj) {
      // Skip field mapping keys (starting with _)
      if (key.startsWith('_')) continue;

      const value = obj[key];

      // Check if value is a numeric ID
      if (Number.isInteger(value) && value > 0) {
        ids.push(value);
      }

      // Recurse into nested objects and arrays
      if (typeof value === 'object') {
        extractIdsFromData(value, ids, visited);
      }
    }
  }

  /**
   * Replace attachment IDs with URLs in block content
   */
  function replaceIdsWithUrls(blockContent, idToUrlMap) {
    let modified = blockContent;
    console.log(idToUrlMap);

    for (const id in idToUrlMap) {
      const url = idToUrlMap[id];

      modified = modified.replace(new RegExp(id), () => {
        return `"_image-url-start_${url}_image-url-end_"`;
      });
    }
    return modified;
  }

  /**
   * Resolve attachment IDs to URLs via REST API
   */
  async function resolveAttachmentIds(attachmentIds) {
    try {
      const response = await fetch(acfBlockCopyData.restApiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': acfBlockCopyData.nonce,
        },
        body: JSON.stringify({ attachment_ids: attachmentIds }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      return data.urls || {};
    } catch (error) {
      console.error('Error resolving attachment URLs:', error);
      return {};
    }
  }

  /**
   * Copy block with attachment resolution
   */
  async function copyBlockWithAttachments(blockContent) {
    clipboardData.isProcessing = true;

    // Extract attachment IDs from block
    const attachmentIds = extractAttachmentIds(blockContent);

    if (attachmentIds.length > 0) {
      // Resolve IDs to URLs
      const idToUrlMap = await resolveAttachmentIds(attachmentIds);

      // Replace IDs with URLs in block content
      blockContent = replaceIdsWithUrls(blockContent, idToUrlMap);
    }

    // Store in clipboard
    try {
      await navigator.clipboard.writeText(blockContent);
      clipboardData.blockContent = blockContent;
      showNotification('Block copied with resolved image URLs!');
    } catch (error) {
      console.error('Failed to copy to clipboard:', error);
      showNotification('Error copying block to clipboard', 'error');
    }

    clipboardData.isProcessing = false;
  }

  /**
   * Show notification to user
   */
  function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `acf-block-copy-notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.classList.add('show');
    }, 10);

    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => {
        document.body.removeChild(notification);
      }, 300);
    }, 3000);
  }

  /**
   * Higher-order component to add copy button to block toolbar
   */
  const withBlockCopyButton = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
      const { clientId, name } = props;

      // Get the block's inner HTML from the DOM
      const getBlockContent = () => {
        const store = select('core/block-editor');
        const block = store.getBlock(clientId);

        if (!block) return null;

        // Serialize the block to HTML
        const { serialize } = wp.blocks;
        return serialize(block);
      };

      const handleCopyBlock = async () => {
        const blockContent = getBlockContent();
        if (blockContent) {
          await copyBlockWithAttachments(blockContent);
        } else {
          showNotification('Error: Could not retrieve block content', 'error');
        }
      };

      return createElement(
        Fragment,
        null,
        createElement(
          BlockControls,
          null,
          createElement(
            ToolbarGroup,
            null,
            createElement(ToolbarButton, {
              label: 'Copy block with image URLs',
              icon: 'admin-page',
              onClick: handleCopyBlock,
              isActive: false,
              title: 'Copy this block and replace image IDs with URLs',
            }),
          ),
        ),
        createElement(BlockEdit, props),
      );
    };
  }, 'withBlockCopyButton');

  // Apply the block edit filter to all blocks
  wp.hooks.addFilter('editor.BlockEdit', 'acf-block-copy/with-block-copy-button', withBlockCopyButton);
})();
