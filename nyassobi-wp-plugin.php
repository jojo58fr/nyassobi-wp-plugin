<?php
/**
 * Plugin Name: Nyassobi WP Plugin
 * Plugin URI: https://nyassobi.com
 * Description: Adds Nyassobi configuration options for headless usage and exposes them via WPGraphQL.
 * Version: 1.0.0
 * Author: Nyassobi
 * License: GPL-2.0-or-later
 *
 * @package NyassobiWPPlugin
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require __DIR__ . '/includes/class-nyassobi-wp-plugin.php';

Nyassobi_WP_Plugin::instance();
