=== PiviGames Steam ===
Contributors: pivigames
Tags: steam, buy on steam, structured data, seo, games
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an SEO-optimized "Comprar en Steam" section to posts from a Steam App ID,
with native crawlable HTML and schema.org VideoGame structured data.

== Description ==

Enter a game's **Steam App ID** and this plugin renders a native "Comprar en
Steam" card on the post — title, Spanish description, cover image, platforms,
live price and a green "Buy on Steam" button — using live data from Steam's
public store API (cached for 12 hours).

Why native instead of the iframe? The official Steam iframe widget is served
from Steam's domain, so its content is NOT indexed as part of your page. This
plugin instead outputs:

* Real, crawlable HTML with a proper H2 heading and an outbound link.
* **schema.org/VideoGame + Offer JSON-LD** (name, description, image, platforms,
  developer, publisher, genre and price) — eligible for rich results in Google.
* A lazy-loaded image (no Core Web Vitals hit).

An option to use the official iframe widget is included for those who prefer the
exact Steam look (structured data and a crawlable link are still output).

== Installation ==

1. Upload the `pivigames-steam` folder to `/wp-content/plugins/` (or upload the
   zip via Plugins → Add New → Upload Plugin).
2. Activate the plugin.
3. Edit a post — in the "Comprar en Steam / Buy on Steam" box (right sidebar)
   enter the Steam App ID (the number in the store URL,
   store.steampowered.com/app/**2670630**/). Update the post.

== Usage ==

**Automatic:** The section appears at the end of the post content, between the
system requirements and the download links.

**Manual (shortcode):**

    [pivigames_steam]
    [pivigames_steam id="123"]

**Price currency:** set a 2-letter country code (us, es, mx, ar, cl…) in the box
to control which currency Steam returns. Change the site-wide default with the
`pivigames_steam_default_cc` filter.

== Frequently Asked Questions ==

= Where do I find the App ID? =

Open the game's Steam store page. The number in the URL is the App ID, e.g.
https://store.steampowered.com/app/2670630/Supermarket_Simulator/ → 2670630.

= The price is wrong / in the wrong currency =

Set the country code field (e.g. "es" for EUR). Prices are cached for 12 hours;
saving the post refreshes the cache.

== Changelog ==

= 1.0.0 =
* Initial release: native SEO "Comprar en Steam" card + VideoGame JSON-LD,
  live Steam API data with caching, and an optional official iframe widget.
