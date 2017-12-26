<?php
/*
Plugin Name: Purchase Orders Generic
Plugin URI: https://github.com/diego2k/purchaseorders
Description: Create a purchase order from almost any list
Version: 0.1
Author: diego2k
Author URI: http://www.mundoit.com.ar/

Copyright 2012  Diego Coppari (email: diego2k@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/


// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) 
{
    echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
    exit;
}

require('lib/PurchaseOrders.class.php');
require('lib/PurchaseWidget.class.php');
require('lib/ArrayToTextTable.class.php');

load_plugin_textdomain('purchase-orders', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');

wp_enqueue_style( 'toastr', plugins_url('/css/toastr.min.css', __FILE__ ) );
wp_enqueue_style( 'purchase-orders', plugins_url('/css/purchase-orders.css', __FILE__ ), false, '1','all');
wp_enqueue_style( 'fontawesome',"//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css");

wp_register_script( 'toastr', plugins_url('/js/toastr.min.js', __FILE__ ), array('jquery'),'1.0', false);
wp_register_script( 'purchase-orders', plugins_url('/js/purchase-orders.js', __FILE__ ), array('toastr'), '0.1', false);

add_action('wp_enqueue_scripts', function() { 
  wp_enqueue_script( 'toastr');
  wp_enqueue_script( 'purchase-orders');
});

add_action( 'init',                           array('PurchaseOrders', 'processEvent'), 10);
add_action( 'wp_ajax_purchaseorders',         array('PurchaseOrders', 'processEvent'));
add_action( 'wp_ajax_nopriv_purchaseorders',  array('PurchaseOrders', 'processEvent'));
add_shortcode('purchaseorders',               array('PurchaseOrders', 'showCart') );

add_action( 'admin_menu',                     array('PurchaseOrders', 'admin_settings_menu'), 30 );

add_action( 'wp_head', function() { ?> <script type="text/javascript">var purchaseordersAjaxurl = '<?=admin_url('admin-ajax.php'); ?>';</script> <?php });

add_action('init',      'purchaseorders_StartSession', 1);
add_action('wp_logout', 'purchaseorders_EndSession');
add_action('wp_login',  'purchaseorders_EndSession');


function purchaseorders_StartSession() {
	
	if(!session_id()) session_start();

    if ( !isset($_SESSION['cart']) ) 
    {
		$profit = get_user_option( 'purchase_orders_profit', get_current_user_id() );
		
		if($profit === false ) $profit = 0;
		
        $_SESSION['cart']['seed'] = uniqid();
        $_SESSION['cart']['profit'] = $profit;
        $_SESSION['cart']['created'] = time();
		
    }

	// Hooks for Contact Form 7 integration
	if( class_exists('WPCF7_ContactForm') && get_option('purchaseorders_contactform7', 0) != 0 )
	{
		add_filter('wpcf7_form_hidden_fields', array('PurchaseOrders','addHiddenFields') );
		add_action('wpcf7_before_send_mail', array('PurchaseOrders', 'processEvent'));
	}        
}

function purchaseorders_EndSession() {
    session_destroy();
}
