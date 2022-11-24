<?php
/**
 * Plugin Name:       IndieBlocks
 * Description:       Leverage blocks and custom post types to easily "IndieWebify" your WordPress site.
 * Plugin URI:        https://jan.boddez.net/wordpress/indieblocks
 * Author:            Jan Boddez
 * Author URI:        https://jan.boddez.net/
 * License:           GNU General Public License v3
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       indieblocks
 * Version:           0.3.4
 * GitHub Plugin URI: https://github.com/janboddez/indieblocks
 * Primary Branch:    main
 *
 * @author  Jan Boddez <jan@janboddez.be>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 * @package IndieBlocks
 */

namespace IndieBlocks;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load dependencies.
require_once __DIR__ . '/build/vendor/scoper-autoload.php';

$indieblocks = IndieBlocks::get_instance();
$indieblocks->register();
