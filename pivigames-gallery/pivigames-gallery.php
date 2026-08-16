<?php
/**
 * Plugin Name:       PiviGames Gallery
 * Plugin URI:        https://pivigame.com
 * Description:        Adds a "Capturas" (Screenshots) gallery to posts. Pick images from the media library; they show as a grid and open in a Nintendo-style lightbox — the clicked image framed on top with a thumbnail strip below. Click the large image to zoom to full 1280x720 size. Built for the Hubber theme.
 * Version:           1.1.1
 * Author:            PiviGames
 * Author URI:        https://pivigame.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pivigames-gallery
 * Domain Path:       /languages
 *
 * @package PiviGames_Gallery
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIVIGAMES_GALLERY_VERSION', '1.1.1' );
define( 'PIVIGAMES_GALLERY_FILE', __FILE__ );
define( 'PIVIGAMES_GALLERY_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIVIGAMES_GALLERY_URL', plugin_dir_url( __FILE__ ) );

require_once PIVIGAMES_GALLERY_DIR . 'includes/class-pivigames-gallery.php';

/**
 * Boot the plugin.
 */
function pivigames_gallery() {
	return PiviGames_Gallery::instance();
}
pivigames_gallery();
