=== PiviGames Gallery ===
Contributors: pivigames
Tags: gallery, screenshots, lightbox, games
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Capturas" (Screenshots) gallery to posts with a Nintendo-style lightbox
and full-size zoom. Built for pivigame.com and the Hubber theme.

== Description ==

Pick images from the media library and they show as a responsive grid under a
"Capturas" heading. Clicking a screenshot opens a lightbox:

* The clicked image is shown large, framed on top.
* A thumbnail strip below lets you jump between screenshots (Nintendo style).
* Arrow keys / on-screen arrows navigate; Esc closes.
* **Click the large image to zoom to full 1280x720 size** (scroll/pan around);
  click again (or Esc) to zoom back out.

Each thumbnail is a real, crawlable link to the full-size image (good for SEO
and image search), and works even if JavaScript is disabled. Images are
lazy-loaded and the lightbox is a lightweight, dependency-free script.

Recommended screenshot size: **1280x720 px**.

== Installation ==

1. Upload the `pivigames-gallery` folder to `/wp-content/plugins/` (or upload
   the zip via Plugins → Add New → Upload Plugin).
2. Activate the plugin.
3. Edit a post → "Capturas / Screenshots" box → "Añadir imágenes", pick your
   screenshots, drag to reorder, and update the post.

== Usage ==

**Automatic:** The gallery appears in the post (before the requirements/Steam/
download sections).

**Manual (shortcode):**

    [pivigames_gallery]
    [pivigames_gallery id="123"]

== Changelog ==

= 1.0.0 =
* Initial release: media-library gallery, responsive grid, Nintendo-style
  lightbox with thumbnail strip and full-size click-to-zoom.
