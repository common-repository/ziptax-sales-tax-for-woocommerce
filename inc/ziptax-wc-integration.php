<?php

include_once 'ziptax-utils.php';

if ( ! class_exists( 'WC_Ziptax_Integration' ) ) :

class WC_Ziptax_Integration extends WC_Integration {

  public function __construct( ) {

    $this->utils = new ZT_Utils();

    $this->utils->logger("Called class: WC_Ziptax_Integration");

    global $woocommerce;

    $this->id                 = 'ziptax-integration';
    $this->method_title       = __( 'ZipTax Integration', 'wc-ziptax' );
    $this->method_description = __( 'Zip-Tax.com sales tax API for WooCommerce' );
    $this->app_uri            = 'https://zip-tax.com/';
    $this->integration_uri    = $this->app_uri. 'woocommerce';
    $this->regions_uri        = $this->app_uri. 'regions';
    $this->api_base           = 'https://api.zip-tax.com/request/v20';
    $this->ua                 = 'ZipTaxWordPressPlugin/1.0.0/WordPress/' . get_bloginfo( 'version' ) . '+WooCommerce/' . $woocommerce->version . '; ' . get_bloginfo( 'url' );
    $this->debug              = filter_var( $this->get_option( 'debug' ), FILTER_VALIDATE_BOOLEAN );

    $this->init_settings();

    // Define user variables
    $this->api_token        = $this->get_option( 'api_token' );
    $this->enabled          = filter_var( $this->get_option( 'enabled' ), FILTER_VALIDATE_BOOLEAN );

    // save plugin options
    add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

    $this->init_form_fields();

    $test = 'yes';

    if ($test == 'yes' ) {

      $this->utils->logger("ZT Settings enabled");

      $this->utils->logger("Adding filters and actions");

      add_action( 'woocommerce_calculate_totals', array( $this, 'zt_total' ), 20 );
      add_filter( 'woocommerce_ajax_calc_line_taxes', array( $this, 'zt_ajax' ), 99, 4 );

      add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_before' ),  9 );
      add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_after'  ),  11);

      $this->utils->logger("Updating options");

      update_option( 'woocommerce_calc_taxes', 'yes' );
      update_option( 'woocommerce_tax_based_on', "shipping" );
      update_option( 'woocommerce_prices_include_tax', 'no' );
      update_option( 'woocommerce_default_customer_address', '' );
      update_option( 'woocommerce_shipping_tax_class', '' );
      update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
      update_option( 'woocommerce_tax_display_shop', 'excl' );
      update_option( 'woocommerce_tax_display_cart', 'excl' );
      update_option( 'woocommerce_tax_total_display', 'single' );
    }
  }

  public function init_form_fields() {

    $this->utils->logger("Initializing form fields");

    $default_wc_settings = explode( ':', get_option('woocommerce_default_country') );

    if ( empty( $default_wc_settings[1] ) ){
      $default_wc_settings[1] = "N/A";
    }

    $this->form_fields   = array(
      'api_token' => array(
        'title'             => __( 'API Token', 'wc-ziptax' ),
        'type'              => 'text',
        'description'       => __( '<a href="'.$this->app_uri.'/woocommerce" target="_blank">Click here</a> to get your API token.', 'wc-ziptax' ),
        'desc_tip'          => false,
        'default'           => ''
      ),
      'debug' => array(
        'title'             => __( 'Debug Log', 'wc-ziptax' ),
        'type'              => 'checkbox',
        'label'             => __( 'Enable debug logging', 'wc-ziptax' ),
        'default'           => 'no',
        'description'       => __( 'Log plugin events.', 'wc-ziptax' ),
      )
    );
  }

  public function zt_ajax_rates( $items, $order_id, $country, $post ) {

    $this->utils->logger("Called: zt_ajax_rates for order id: " . $order_id);

    global $woocommerce;

    return true;

  }

  public function zt_tax_item_totals($total_rows, $myorder_obj) {

    $this->utils->logger("Called: zt_tax_item_totals");

    return true;

  }

  public function zt_total($wc_cart_object) {

    $this->utils->logger("Called: zt_get_taxes");

    global $woocommerce;

    $to_country = $woocommerce->customer->get_shipping_country();
    $to_state = $woocommerce->customer->get_shipping_state();
    $to_zip = $woocommerce->customer->get_shipping_postcode();
    $to_city = $woocommerce->customer->get_shipping_city();

    $this->utils->logger("Calling API with country: " . $to_country);
    $this->utils->logger("Calling API with state: " . $to_state);
    $this->utils->logger("Calling API with city: " . $to_city);
    $this->utils->logger("Calling API with zip code: " . $to_zip);

    $session_country = WC()->session->get( 'wc_country' );
    $session_state = WC()->session->get( 'wc_state' );
    $session_zip = WC()->session->get( 'wc_zip' );
    $session_cty = WC()->session->get( 'wc_city' );

    $this->ziptax_call( array(
      'city' =>      $to_city,
      'state' =>     $to_state,
      'country' =>   $to_country,
      'zip' =>       $to_zip,
      'amount' =>    $this->zt_taxable($woocommerce->cart),
      'shipping' =>  $woocommerce->shipping->shipping_total,
      'customer' =>  $woocommerce->customer
    ) );

    $wc_cart_object->tax_total = $this->item_collectable;
    $wc_cart_object->taxes = array($this->rate_id => $this->item_collectable);
  }

  public function zt_ajax($items, $order_id, $country, $post) {

    $this->utils->logger("Called: zt_ajax");

    global $woocommerce;

    WC_TAX::_update_tax_rate( 1, 209 );

    return $items;

  }

  private function ziptax_call($options = array()) {

    $this->utils->logger("Called ziptax API with key: " . $this->api_token);

    global $woocommerce;

    $order_total =  $options['amount'];

    if (!$this->api_token) {
      $this->utils->logger("Missing API key");
      return 0;
    }

    $this->utils->logger("Getting rate from session.");
    $session_state = WC()->session->get( 'wc_state' );
    $session_city = WC()->session->get( 'wc_city' );
    $session_zip = WC()->session->get( 'wc_zip' );
    $session_rate = WC()->session->get( 'wc_rate' );

    $ziptax_response = 0;

    $this->utils->logger("Session state: " . $session_state . " Option state: " . $options['state']);
    $this->utils->logger("Session city: " . $session_city . " Option city: " . $options['city']);
    $this->utils->logger("Session zip: " . $session_zip . " Option zip: " . $options['zip']);
    $this->utils->logger("Session rate: " . $session_rate);

    if ($session_state != $options['state'] ||
        $session_city != $options['city'] ||
        $session_zip != $options['zip'])
    {
      $this->utils->logger("No session match.");
      $url = $this->api_base . '?key='.$this->api_token.'&postalcode=' . $options['zip'] . '&format=JSON';
      $this->utils->logger("Calling API: " . $url);
      $request = new WP_Http;
      $ziptax_response = $request->request( $url );
    }
    else {
      $this->utils->logger("No rate change ... using session rate.");
      $r_salestax = $session_rate;
    }

    if (is_array($ziptax_response)) {

      foreach ($ziptax_response as $name => $value) {

        if ($name == "body") {

          $body_content = json_decode($value);

          foreach ($body_content as $items => $item) {

            $this->utils->logger($items . " -- " . $item);

            if ($items == "results") {

              $is_default = False;

              foreach ($item as $key => $obj) {

                if (!$is_default) {
                  $this->utils->logger("Setting default rate...");
                  $r_zip      = $obj->geoPostalCode;
                  $r_state    = $obj->geoState;
                  $r_city     = $obj->geoCity;
                  $r_salestax = $obj->taxSales;
                  $r_service  = $obj->txbService;
                  $r_freight  = $obj->txbFreight;

                  $this->utils->logger("Result postal code: " . $r_zip);
                  $this->utils->logger("Result state code: " . $r_state);
                  $this->utils->logger("Result city name: " . $r_city);
                  $this->utils->logger("Result sales tax: " . $r_salestax);
                  $this->utils->logger("Result taxable service: " . $r_service);
                  $this->utils->logger("Result taxable freight: " . $r_freight);

                  $is_default = True;
                }

                $result_city = str_replace(' ', '', strtolower($obj->geoCity));
                $customer_city = str_replace(' ', '', strtolower($options['city']));

                if ($result_city == $customer_city) {
                  $this->utils->logger("Override default based on match...");
                  $r_zip      = $obj->geoPostalCode;
                  $r_state    = $obj->geoState;
                  $r_city     = $obj->geoCity;
                  $r_salestax = $obj->taxSales;
                  $r_service  = $obj->txbService;
                  $r_freight  = $obj->txbFreight;

                  $this->utils->logger("Result postal code: " . $r_zip);
                  $this->utils->logger("Result state code: " . $r_state);
                  $this->utils->logger("Result city name: " . $r_city);
                  $this->utils->logger("Result sales tax: " . $r_salestax);
                  $this->utils->logger("Result taxable service: " . $r_service);
                  $this->utils->logger("Result taxable freight: " . $r_freight);

                }

              }

            }

          }

        }

      }

      // after the loop, set the session
      $this->utils->logger("Setting rate in session.");
      WC()->session->set( 'wc_state' , $options['state'] );
      WC()->session->set( 'wc_city' , $options['city'] );
      WC()->session->set( 'wc_zip' , $options['zip'] );
      WC()->session->set( 'wc_rate' , $r_salestax );
    }

    $this->tax_rate           = 0.20;
    $this->amount_to_collect  = $order_total;
    $this->item_collectable   = $order_total * $r_salestax;
    $this->shipping_collectable = $options['shipping'] * $r_salestax;
    $this->freight_taxable = ($r_freight == "Y") ? 1 : 0;
    $this->rate_id            = 1;

    return $this->rate_id;

  }

  private function zt_taxable( $wc_cart_object ) {

    $this->utils->logger("Called: zt_taxable");

    $taxable_amount = 0;

    foreach ( $wc_cart_object->cart_contents as $key => $item )
    {
      $_product = $item['data'];

      if ( $_product->is_taxable() )
      {
        $base_price = $_product->get_price();
        $price = $_product->get_price() * $item['quantity'];
        $discounted_price = $wc_cart_object->get_discounted_price( $item, $base_price, false );
        $taxable_amount += $discounted_price * $item['quantity'];
      }

    }

    if ( ! $process_line_items )
    {
      return $taxable_amount;
    }
    else
    {
      return $wc_cart_object;
    }

  }

  public function output_sections_before( ) {
    echo '<div class="ziptax"><h4>Tax Rates Powered by <a href="http://www.zip-tax.com" target="_blank">Zip-Tax.com</a> | <a href="admin.php?page=wc-settings&tab=integration">Configure the Zip-Tax.com sales tax API</a></h4></div>';
    echo '<div style="display:none;">';
  }

  public function output_sections_after( ) {
    echo '</div>';
  }

}
endif;
