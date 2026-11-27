<?php
/**
 * Plugin Name: WP REST CRM Sync Bridge
 * Description: Custom WordPress REST API endpoints for signed inbound CRM webhooks and admin-triggered outbound user sync.
 * Version: 1.0.0
 * Author: Amirhossein · CodexaWP
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: wp-rest-crm-sync-bridge
 * License: GPL-2.0-or-later
 *
 * @package CodexaWP\RestCrmBridge
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPRB_VERSION', '1.0.0' );
define( 'WPRB_PATH', plugin_dir_path( __FILE__ ) );

require_once WPRB_PATH . 'includes/class-auth-verifier.php';
require_once WPRB_PATH . 'includes/class-crm-client.php';
require_once WPRB_PATH . 'includes/class-rest-controller.php';
require_once WPRB_PATH . 'includes/class-plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		\CodexaWP\RestCrmBridge\Plugin::instance()->boot();
	}
);
