=== PiviGames Downloads ===
Contributors: pivigames
Tags: downloads, download links, games
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds one or more download links to a post, each with a source, size and a
customizable "Get Now" button. Built for pivigame.com and the Hubber theme.

== Description ==

Adds a **Descargas / Downloads** meta box to the post editor where you can add
as many download links as you need. Each link has:

* **Fuente de descarga / Download Source** — e.g. MediaFire, MEGA, Google Drive.
* **Tamaño / Size** — e.g. 752 MB.
* **Texto del botón / Button Text** — the "Get Now" button label (defaults to
  "Descargar ahora" if left empty).
* **Enlace de descarga / Download URL** — the actual link.

On single posts the download section is displayed in Spanish at the **end of
the post content**, below the system requirements (it hooks the_content at
priority 20, while the PiviGames Specs plugin appends requirements at priority
10). The download button uses the brand color #FE4A49.

Rows without a URL are ignored. Use the "+ Añadir descarga" button to add more
rows, and "Eliminar" to remove them.

== Installation ==

1. Upload the `pivigames-downloads` folder to `/wp-content/plugins/` (or upload
   the zip via Plugins → Add New → Upload Plugin).
2. Activate the plugin.
3. Edit a post, scroll to the "Descargas / Downloads" box, add your links and
   update the post.

== Usage ==

**Automatic:** The download section appears at the end of the post content.

**Manual (shortcode):** Place it anywhere in the content:

    [pivigames_downloads]

Or for a specific post:

    [pivigames_downloads id="123"]

== Changelog ==

= 1.0.0 =
* Initial release: repeatable download links with source, size and a
  customizable #FE4A49 "Get Now" button.
