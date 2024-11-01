<?php

/*
Plugin Name: ZipTax Woocommerce Sales Tax
Plugin URI:  http://www.zip-tax.com/woocommerce-plugin
Description: Sales tax rate automation for Woocommerce
Version:     1.0.0
Author:      Vyke Media, LLC.
Author URI:  http://www.vykemedia.com
*/
include_once 'inc/ziptax-utils.php';

defined( 'ABSPATH' ) or die( 'No direct access allowed!' );

if ( ! class_exists( 'WC_Ziptax' ) ) :

    class WC_Ziptax {

        public function __construct() {
            $this->utils = new ZT_Utils();
            $this->utils->logger("-----------------------");
            $this->utils->logger("Called class: WC_Ziptax");
            add_action( 'plugins_loaded', array( $this, 'init' ) );
            register_activation_hook( __FILE__, array( 'WC_Ziptax', 'plugin_registration_hook' ) );
        }

        public function init() {
            global $woocommerce;

            if ( class_exists( 'WC_Integration' ) ) {
                $this->utils->logger("Found class: WC_Integration");
                include_once 'inc/ziptax-wc-integration.php';
                add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ), 20 );
            }
        }

        public function add_integration( $integrations ) {
            $this->utils->logger("Adding integration");
            $integrations[] = 'WC_Ziptax_Integration';
            return $integrations;
        }

        static function plugin_registration_hook() {
            if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
                exit(sprintf('<strong>ZipTax requires PHP 5.3 or higher. You are currently using %s.</strong>',PHP_VERSION));
            }

            if ( !class_exists( 'Woocommerce' ) ) {
                exit('<strong>ZipTax requires an active Woocommerce installation. Please install WooCommerce before using this plugin.</strong>');
            }

            global $wp_database;
        }

        function plugin_settings_link($links) {
         	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=ziptax-integration">Settings</a>';
          	array_unshift($links, $settings_link);
          	return $links;
        }
    }

    add_filter( 'plugin_action_links_'. plugin_basename( __FILE__ ), 'plugin_settings_link' );

    $WC_Ziptax = new WC_Ziptax( __FILE__ );

endif;
