<?php

/*
Plugin Name: ZipTax Woocommerce Sales Tax
Plugin URI:  http://www.zip-tax.com/woocommerce-plugin
Description: Sales tax rate automation for Woocommerce
Version:     1.0.0
Author:      Vyke Media, LLC.
Author URI:  http://www.vykemedia.com
*/

defined( 'ABSPATH' ) or die( 'No direct access allowed!' );

if ( ! class_exists( 'ZT_Utils' ) ) :

  class ZT_Utils {
    public function __construct( ) {
      $this->logfile = "/var/log/zt-debug.log";
    }

    public function logger($msg) {
      file_put_contents($this->logfile,date(DATE_ATOM, time()) . " :: " . $msg . "\r\n",FILE_APPEND);
    }
  }

endif;
