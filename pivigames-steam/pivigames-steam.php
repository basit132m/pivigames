<?php
/**
 * Plugin Name:       PiviGames Steam
 * Plugin URI:        https://pivigame.com
 * Description:        Adds an SEO-optimized "Comprar en Steam" section to posts. Enter a Steam App ID and the plugin renders a native, crawlable card (title, Spanish description, price, platforms, Buy button) with schema.org VideoGame structured data, using live data from Steam's public API (cached). Optional official iframe widget.
 * Version:           1.0.0
 * Author:            PiviGames
 * Author URI:        https://pivigame.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pivigames-steam
 * Domain Path:       /languages
 *
 * @package PiviGames_Steam
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIVIGAMES_STEAM_VERSION', '1.0.0' );
define( 'PIVIGAMES_STEAM_FILE', __FILE__ );
define( 'PIVIGAMES_STEAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIVIGAMES_STEAM_URL', plugin_dir_url( __FILE__ ) );

require_once PIVIGAMES_STEAM_DIR . 'includes/class-pivigames-steam.php';

/**
 * Boot the plugin.
 */
function pivigames_steam() {
	return PiviGames_Steam::instance();
}
pivigames_steam();
