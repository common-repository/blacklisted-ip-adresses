<?php
/*
Plugin Name: Blacklisted IP Adresses
Plugin URI: https://boldizart.com/?blacklisted-ip-addresses
Description: This plugin allows you to block specific IP addresses from accessing your WordPress site.
Version: 1.0.1
Author: BoldizArt
Author URI: https://boldizart.com/
License: GPL2+
Text Domain: blacklistedipaddresses
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * @package BoldizArt - Blacklisted IP Adresses
 */
require __DIR__ . '/src/BlacklistedIPAddresses.php';

use BoldizArt\BlacklistedIPAddresses;

// Plugin init
$bia = new BlacklistedIPAddresses(plugin_basename(__FILE__));

// Activation 
register_activation_hook(__FILE__, [$bia, 'activate']);

// Deactivation 
register_deactivation_hook(__FILE__, [$bia, 'deactivate']);
