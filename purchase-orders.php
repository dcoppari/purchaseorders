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
require('lib/ArrayToTextTable.class.php');

load_plugin_textdomain('purchase-orders', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');

add_action('admin_menu', array('PurchaseOrders', 'admin_settings_menu'), 30 );

add_shortcode('purchaseorder', array('PurchaseOrders', 'showCart'));

wp_enqueue_style('purchase-orders-css', plugins_url( 'css/purchase-orders.css', __FILE__ ), false, '1','all');

add_action('init', array('PurchaseOrders', 'processEvent'), 10);
