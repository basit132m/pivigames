=== PiviGames Specs ===
Contributors: pivigames
Tags: specs, system requirements, games, technical information
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Información Técnica" (Technical Information) table and a "Requisitos del
Sistema" (System Requirements) section to the top of your posts. Built for
pivigame.com and the Hubber theme.

== Description ==

This plugin adds two meta boxes to the post editor:

1. **Información Técnica / Technical Information** — Plataforma, Peso total,
   Formato, Fecha de estreno, Fecha de actualización.
2. **Requisitos del Sistema / System Requirements** — Minimum and Recommended
   tiers, each with SO, Procesador, Memoria, Gráficos, DirectX and
   Almacenamiento.

Admin labels are shown in Spanish with an English hint underneath. The
front-end output is fully in Spanish.

On single posts the data is placed automatically:

* The **Technical Information** table appears at the **top** of the content.
* The **System Requirements** appear at the **end** of the content (just above
  anything a later plugin, such as a download button, adds).

Fields left empty are simply not shown.

Shortcodes are also available if you prefer to place blocks manually:

* `[pivigames_specs]` — both blocks together.
* `[pivigames_tech]` — only the Technical Information table.
* `[pivigames_requirements]` — only the System Requirements.

Each accepts an optional `id` attribute, e.g. `[pivigames_tech id="123"]`.

== Installation ==

1. Upload the `pivigames-specs` folder to `/wp-content/plugins/`.
   (Or zip the folder and upload it via Plugins → Add New → Upload Plugin.)
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Edit any post — you'll find the "Información Técnica" and "Requisitos del
   Sistema" boxes below the editor. Fill them in and update the post.

== Usage ==

**Automatic:** The specs appear at the top of the post content automatically.

**Manual (shortcode):** Place the specs anywhere in the content:

    [pivigames_specs]

Or show the specs of a specific post by ID:

    [pivigames_specs id="123"]

== Frequently Asked Questions ==

= The table doesn't appear at the top of my post =

Some themes / page builders render the content in a way that bypasses the
`the_content` filter. In that case use the `[pivigames_specs]` shortcode inside
the post, or add `echo do_shortcode('[pivigames_specs]');` in the theme's
single-post template.

= Can I use it on custom post types? =

Yes. Add this to your theme's functions.php:

    add_filter( 'pivigames_specs_post_types', function ( $types ) {
        $types[] = 'your_cpt';
        return $types;
    } );

== Changelog ==

= 1.0.0 =
* Initial release: Technical Information table and System Requirements section.
