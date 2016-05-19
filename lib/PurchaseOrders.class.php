<?php
/**
 * Purchase Orders
 *
 * @package PurchaseOrders
 * @author diego2k [at] gmail.com
 */
 
class PurchaseOrders
{

    public static function processEvent()
    {

		$cb_options = get_option('purchaseorders',array());

		if ( !count($cb_options) && !isset($cb_options['public_post_slug']) ) return false;

		$catablog_item_page = strpos($_SERVER['REQUEST_URI'], $cb_options['public_post_slug']) !== false 
		                   || strpos($_SERVER['REQUEST_URI'], $cb_options['public_tax_slug'])  !== false 
		                   || strpos($_SERVER['REQUEST_URI'], 'catablog-items') !== false 
		                   || strpos($_SERVER['REQUEST_URI'], 'catablog-terms') !== false
		                   || (isset($_REQUEST['cmd']) && $_REQUEST['cmd'] !== '')  ;

	    if ( $catablog_item_page || isset($_REQUEST['catablogcartprocess']) )
	    {
		    $post_vars = array_map('stripslashes_deep', $_REQUEST);
		    $post_vars = array_map('trim', $post_vars);

			$redirect = true;
			$redirect_id = home_url() . '?page_id='.get_option('purchaseorders_pageid',0);
			
			$command = $post_vars['cmd'];
			
			do_action('purchaseorders_before_event', $command);

			switch( $post_vars['cmd'] )
		    {
		        case '_cart':
		            self::addToCart($post_vars);
                    break;
                    
                case '_empty':
                    self::emptyCart();
		            break;

                case '_thanks':
                    self::thanksCart();
		            break;
		            
		        case '_remove':
		            self::removeFromCart($post_vars);
		            break;		            

		        case '_checkout':
		            self::checkOut($post_vars);
		            break;
					
				default:
					$redirect = false;
		    }
			
			do_action('purchaseorders_after_event', $command, $redirect);
			
			$redirect    = apply_filters('purchaseorders_redirect',$redirect);
			
			$redirect_id = apply_filters('purchaseorders_redirect_id',$redirect_id);
			
			if($redirect)
			{
				wp_redirect($redirect_id);
				exit();			
			}
			
	    }
	 
    }
    
    public static function addToCart($post_vars = null)
    {
        
        if ( !is_array($post_vars) ) return false;

        session_start();
        
        if ( !isset($_SESSION['cart']) ) 
        {
            session_destroy();
            session_start();
            $_SESSION['cart']['seed'] = uniqid();
        } 
        
        $item_info = array();
     
        // Parse item information Only add the one that begins with item_
        foreach($post_vars as $post_item_key => $post_item_value)
        {
            if( strstr($post_item_key,'item_') !== false )
            {
               $item_info[$post_item_key] = $post_item_value;
            }
        }

		$item_info['quantity'] = $post_vars['quantity'];
		
		// Checks Whenever or not add Prices
		$hidePrices  = (get_option('purchaseorders_hideprices', '') != '');
		
        if(!$hidePrices)
        {
            // Add Amount Qty and calculates Total
	        $item_info['amount']   = $post_vars['amount'];    
			$item_info['total']    = $post_vars['amount'] * $post_vars['quantity'];
        }
        
		$item_info = apply_filters('purchaseorders_add_item',$item_info);
		
        $_SESSION['cart']['items'][] = $item_info;

        return true;
    }
    
    
    public static function removeFromCart($post_vars = null)
    {
        if ( !is_array($post_vars) ) return false;

        session_start();
        
        if ( !isset($_SESSION['cart']) ) return false;
        
        $itm = $post_vars['item_order'];
        
        unset($_SESSION['cart']['items'][$itm]);
        
        if ( count($_SESSION['cart']['items']) < 1 )         
            unset($_SESSION['cart']['items']);
        
        return true;
        
    }
        
    public static function showCart()
    {
		@session_start();

		$hidePrices  = (get_option('purchaseorders_hideprices', '') != '');

		// Mercado Pago 
		$mp          = false;
		$mpcid       = get_option('purchaseorders_mpclientid','');
		$mpcscrt     = get_option('purchaseorders_mpclientscrt','');
		$mpsandbox   = get_option('purchaseorders_mpsandbox','') == 'yes';

		$permalink   = get_permalink( get_option('purchaseorders_pageid',0) );
		$preference_data = [];
        $sent = false;
        $message = '';

        if ( !isset($_SESSION['cart']) ) 
            $empty = true;        
        else
            if ( !is_array($_SESSION['cart']['items']) ) 
				$empty = true;
		
        if ($empty) 
        {    
			return '<div class="purchaseorders-cart-message cart-empty">'. __('Cart is empty','purchase-orders') .'</div>';
        }
		
		if (isset($_SESSION['cart']['submit']))
		{
			if ($_SESSION['cart']['submit'] == true)
			{
				$sent = true;
				if ( self::emptyCart() )
					return '<div class="purchaseorders-cart-message order-sent">'. __('Your order was sent, you will receive an email notifying you that your order has been sent.','purchase-orders') .'</div>';
			}
			else
			{
				$message = __('Unable to precess your order, please report this issue using contact form','purchase-orders');
			}
		}

		$cart = $_SESSION['cart']['items'];

		if(!$hidePrices && $mpcid != '' && $mpcscrt != '')  
		{
			include dirname(__FILE__)."/mercadopago.php";
  			$mp = new MP($mpcid, $mpcscrt);
  			//$mp->sandbox_mode($mpsandbox);
    			
		}
		
		$preference_data["back_urls"]["success"] = $permalink.'?purchaseorders&cmd=_thanks';
		$preference_data["back_urls"]["pending"] = $permalink.'?purchaseorders&cmd=_thanks';
		$preference_data["back_urls"]["failure"] = $permalink;
		
		ob_start();
		?>
		
		<div class="purchaseorders-cart">

			<div class="purchaseorders-cart-message"><?php echo $message; ?></div>
			
			<table class="purchaseorders-cart-table">
				<thead>
					<tr>
						<td><?php _e('Code','purchase-orders') ?></td>
						<td><?php _e('Description','purchase-orders') ?></td>
						<td><?php _e('Qty','purchase-orders') ?></td>
						<?php if(!$hidePrices) { ?>
						<td><?php _e('Price','purchase-orders') ?></td>
						<td><?php _e('Total','purchase-orders') ?></td>
						<?php } ?>
						<td>&nbsp;</td>
					</tr>
				</thead>
				
				<tbody>
					<?php foreach($cart as $order => $item) { ?>
					<tr>
						<td><?php echo $item['item_number']; ?></td>
						<td><?php echo $item['item_name'];   ?></td>
						<td><?php echo $item['quantity'];    ?></td>
						<?php if(!$hidePrices) : ?>
						<td align="right"><?php echo number_format($item['amount'],2); ?></td>
						<td align="right"><?php echo number_format($item['total'],2);  ?></td>
						<?php endif; ?>
						<td><a class="purchaseorders-cart-action cart-remove" href="?purchaseorders&cmd=_remove&item_order=<?php echo $order; ?>">X</a></td>
					</tr>
					<?php 

						$preference_data["items"][] = array("title" => $item['item_name'], 
															"currency_id" => "ARS", 
															"quantity" => $item['quantity'] + 0,
															"category_id" => "computing",
															"unit_price" => $item['amount'] + 0 );
															
						
						$total = $total + $item['total']; 
					} 
					?>
				</tbody>
				
				<?php if(!$hidePrices) : ?>
				<tfoot>
					<tr>
						<td colspan="4"></td>
						<td class="purchaseorders-cart-tabletotal" align="right"><?php echo number_format($total,2); ?></td>
						<td>&nbsp;</td>
					</tr>
				</tfoot>
				<?php endif; ?>
			</table>               
		    <br/>
			
			
			<?php if(!$hidePrices && $mp !== false) {
				
					try 
					{
						$preference = $mp->create_preference($preference_data); 
						$mphref = $mpsandbox ? $preference["response"]["sandbox_init_point"] : $preference["response"]["init_point"];
						
					?>

						<a href="<?php echo $mphref; ?>" name="MP-Checkout" class="orange-ar-m-sq-arall"><?php _e('CONFIRM','purchase-orders'); ?></a>
						
						<script type="text/javascript" src="http://mp-tools.mlstatic.com/buttons/render.js"></script>

					<?php
					} 
					catch(Exception $e) 
					{
					  echo $e->getMessage(); 
					}

			} ?>

			<a class="button MP-common-orange-CDm MP-ar-m-sq purchase-orders-action cart-empty-cart" href="?purchaseorders&cmd=_empty"><?php _e('Empty Cart','purchase-orders'); ?></a>
			
			<?php if($mp === false) { ?>

			<h2><?php _e('Order Now','purchase-orders'); ?></h2>
			
			<form method="POST" action="?purchaseorders" class="purchase-orders-form" >

				<?php do_action('purchaseorders_before_form_fields'); ?>
			
				<span class="purchaseorders-checkout-row">
					<label for="email"><?php _e('E-Mail','purchase-orders'); ?></label>
					<input type="email" pattern="[^ @]*@[^ @]*" size="60" id="email" name="email" required="true" />
				</span>
			
				<span class="purchaseorders-checkout-row">
					<label for="firstlast"><?php _e('Name','purchase-orders'); ?></label>
					<input type="text" size="60" id="username" name="firstlast" required="true" />
				</span>
								
				<span class="purchaseorders-checkout-row">
					<label for="phone"><?php _e('Phone','purchase-orders'); ?></label>
					<input type="text" size="60" id="phone" name="phone" required="true" />
				</span>
				
				<span class="purchaseorders-checkout-row">
					<label for="address"><?php _e('Address','purchase-orders'); ?></label>
					<input type="text" size="60" id="address" name="address" />
				</span>
				
				<span class="purchaseorders-checkout-row">
					<label for="note"><?php _e('Note','purchase-orders'); ?></label>
					<textarea type="text" size="60" id="note" name="note"></textarea>
				</span>
				
				<?php do_action('purchaseorders_after_form_fields'); ?>
				
				<span class="purchaseorders-checkout-row">
					<center>
						<input type="hidden" name="cmd" value="_checkout" />
						<input type="hidden" name="formseed" value="<?php echo $_SESSION['cart']['seed']; ?>" />
						<input type="submit" name="submit" value="<?php _e('CONFIRM','purchase-orders'); ?>" />
					</center>
				</span>
			
			</form>
			<?php } ?>

		</div>
		<?php
		
		$data = ob_get_contents(); 
		ob_end_clean();
		
		return $data;
		
    }
    
    public static function checkOut($post_vars)
    {
    
        @session_start();

        if ( !isset($_SESSION['cart']) ) 
            return false;        
        else
            if ( !is_array($_SESSION['cart']['items']) ) 
                return false;
        
        if( $_SESSION['cart']['seed'] != $post_vars['formseed'] )
            return false;

		$standard_subject = __('ORDER','purchase-orders') . ' ' . get_option('blogname');	
		$standard_from    = get_option('admin_email');
		$standard_order   = '%EMAIL% %NAME% %PHONE% %ADDRESS% %NOTE% %ORDER%';
		
        $to       = $post_vars['email'];
        $from     = get_option('purchaseorders_emailfrom',    $standard_from);
		$fromName = get_bloginfo('name');
        $subject  = get_option('purchaseorders_emailsubject', $standard_subject);      
        $isHtml   = (get_option('purchaseorders_emailhtml', '') != '') ? 'html' : 'plain';
		$cart     = $_SESSION['cart']['items']; 

		$to       = apply_filters('purchaseorders_email_order_to',      $to);
		$from     = apply_filters('purchaseorders_email_order_from',    $from);
		$fromName = apply_filters('purchaseorders_email_order_fromname',$fromName);
		$subject  = apply_filters('purchaseorders_email_order_subject', $subject);
		$cart     = apply_filters('purchaseorders_email_order_items',   $cart);
	
		$order = '';	

		if($isHtml)
		{
			// Introducing HTML email send

			$order .= '<table width="100%" border="1">';

			foreach($cart as $key => $item)
			{
				// Make Titles
				if($key == 0) 
				{
					$order .= '<tr>';
					foreach( array_keys($item) as $title )
						$order .= '<th>'.str_replace('item_','',$title).'</th>';
					$order .= '</tr>';
				}
				
				// Content
				$order .= '<tr>';
				foreach($item as $value)
				{
					// Fix Empty Value Cells
					if ($value == '') 
						$value = '&nbsp;';
					
					// Right Align Numbers
					if( is_numeric($value) )
						$order .= "<td align='right'>$value</td>";
					
					// Default Format
					else
						$order .= "<td>$value</td>";
				}
				$order .= '</tr>';				
			}
			$order .= '</table>';
		}		
		else
		{
			// Introducing ArrayToTextTable to render text table
			$order = new ArrayToTextTable($cart);
			$order->showHeaders(true);
			$order = $order->render(true);
		}		

		$order = apply_filters('purchaseorders_email_order_table', $order);

        $headers = "From: $fromName <$from>"                   . PHP_EOL .
                   "To: $to"                                   . PHP_EOL .
                   "BCC: $from"                                . PHP_EOL .
                   "MIME-Version 1.0"                          . PHP_EOL .
                   "Content-type: text/$isHtml; charset=utf-8" . PHP_EOL .
		           "X-Mailer: PHP-" . phpversion()             . PHP_EOL ;

		$upload_dir = wp_upload_dir();		
		$template = $upload_dir['basedir'] . "/purchase-orders/templates/order.htm";
		
		if( is_file($template) )
			$message = file_get_contents($template);
		else
			$message = get_option('purchaseorders_emailtemplate', $standard_order);
               
        $message = str_replace('%EMAIL%',   $post_vars['email'],     $message );
        $message = str_replace('%NAME%',    $post_vars['firstlast'], $message );
        $message = str_replace('%PHONE%',   $post_vars['phone'],     $message );
        $message = str_replace('%ADDRESS%', $post_vars['address'],   $message );
        $message = str_replace('%NOTE%',    $post_vars['note'],      $message );
        $message = str_replace('%ORDER%',   $order,                  $message );

		$message = apply_filters('purchaseorders_email_body', $message, $post_vars);
		
        $ok = wp_mail( $to, $subject, $message, $headers );

		$_SESSION['cart']['submit'] = $ok;

		return $ok;

    }

    public static function emptyCart()
    {
        @session_start();
        if ( isset($_SESSION['cart']) ) 
		{
            unset($_SESSION['cart']['items']);
			@session_destroy();
        }       
        return true;
        
    }

    public static function thanksCart()
    {
        @session_start();
        if ( isset($_SESSION['cart']) ) 
		{
            unset($_SESSION['cart']['items']);
			@session_destroy();
        }       
		return '<div class="purchaseorders-cart-message order-sent">'. __('Your order was sent, you will receive an email notifying you that your order has been sent.','catablogcart') . '</div>';
        
    }
	
	// ADMIN AREA
	public static function admin_settings_menu() 
	{
		add_submenu_page(	'purchaseorders',   
							__('Purchase Orders Options','purchase-orders'),  
							__('Purchase Orders Options','purchase-orders'), 
							'administrator', 
							'purchaseorders' , 
							array(self,'admin_settings_page') 
						);
						
		add_action( 'admin_init', array(self,'admin_settings_register') );
	}

	public static function admin_settings_register() 
	{
		register_setting('purchaseorders-group', 'purchaseorders_pageid'       );
		register_setting('purchaseorders-group', 'purchaseorders_emailfrom'    );
		register_setting('purchaseorders-group', 'purchaseorders_emailsubject' );
		register_setting('purchaseorders-group', 'purchaseorders_emailtemplate');
		register_setting('purchaseorders-group', 'purchaseorders_emailhtml'    );
		register_setting('purchaseorders-group', 'purchaseorders_hideprices'   );

		register_setting('purchaseorders-group', 'purchaseorders_mpclientid'   );
		register_setting('purchaseorders-group', 'purchaseorders_mpclientscrt' );
		register_setting('purchaseorders-group', 'purchaseorders_mpsandbox'    );
	}
    
    public static function admin_settings_page()
    {
    ?>

		<div class="wrap">
		<h2><?php _e('Purchase Orders Options','purchase-orders'); ?></h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'purchaseorders-group' ); ?>
			<table class="form-table">
				<tr valign="top">
				<th scope="row"><?php _e('Cart Page ID','purchase-orders'); ?></th>
				<td>
					<?php wp_dropdown_pages( array('name' => 'purchaseorders_pageid', 'selected' => get_option('purchaseorders_pageid',0) ) ); ?>
					<small><?php _e('Create a page with the <strong>[purchaseorders]</strong> short code','purchase-orders'); ?></small>
				</td>
				</tr>
				 
				<tr valign="top">
				<th scope="row"><?php _e('Order E-mail From','purchase-orders'); ?></th>
				<td><input type="text" name="purchaseorders_emailfrom" value="<?php echo get_option('purchaseorders_emailfrom'); ?>" /></td>
				</tr>
				
				<tr valign="top">
				<th scope="row"><?php _e('Order E-mail Subject','purchase-orders'); ?></th>
				<td><input type="text" size="50" name="purchaseorders_emailsubject" value="<?php echo get_option('purchaseorders_emailsubject'); ?>" /></td>
				</tr>

				<tr valign="top">
				<th scope="row"><?php _e('Order E-Mail Template','purchase-orders'); ?></th>
				<td>
					<textarea cols="60" rows="10" name="purchaseorders_emailtemplate"><?php echo get_option('purchaseorders_emailtemplate'); ?></textarea><br/>
					<small><?php _e('Special variables %ORDER%, %EMAIL%, %NAME%, %PHONE%, %ADDRESS%, %NOTE%','purchase-orders'); ?></small>				
				</td>
				</tr>
				
				<tr valign="top">
				<th scope="row"><?php _e('Send HTML E-mail','purchase-orders'); ?></th>
				<td>
				    <input type="checkbox" name="purchaseorders_emailhtml" value="yes" <?php echo get_option('purchaseorders_emailhtml','') != '' ? 'checked="checked"' : ''; ?>" />
				    <small><?php _e('If checked the email will be sent as HTML, otherwise it will be plain text','purchase-orders'); ?></small>
				</td>
				</tr>

				<tr valign="top">
				<th scope="row"><?php _e('Hide Prices','purchase-orders'); ?></th>
				<td>
				    <input type="checkbox" name="purchaseorders_hideprices" value="yes" <?php echo get_option('purchaseorders_hideprices','') != '' ? 'checked="checked"' : ''; ?>" />
				    <small><?php _e('Enable this option to hide prices and totals','purchase-orders'); ?></small>
				</td>
				</tr>
							
				<tr valign="top">
				<th colspan="2" bgcolor="#BBB"><?php _e('Mercadopago','purchase-orders'); ?></th>
				</tr>

				<tr valign="top">
				<th scope="row"><?php _e('Client_ID','purchase-orders'); ?></th>
				<td>
				    <input type="text" name="purchaseorders_mpclientid" value="<?php echo get_option('purchaseorders_mpclientid','') ?>" />
				    <small><?php _e('This is MERCADOPAGO Client ID','purchase-orders'); ?></small>
				</td>
				</tr>

				<tr valign="top">
				<th scope="row"><?php _e('Client_SECRET','purchase-orders'); ?></th>
				<td>
				    <input type="password" name="purchaseorders_mpclientscrt" value="<?php echo get_option('purchaseorders_mpclientscrt','') ?>" />
				    <small><?php _e('This is MERCADOPAGO Client SECRET','purchase-orders'); ?></small>
				</td>
				</tr>

				<tr valign="top">
				<th scope="row"><?php _e('Enable Sandbox','purchase-orders'); ?></th>
				<td>
				    <input type="checkbox" name="purchaseorders_mpsandbox" value="yes" <?php echo get_option('purchaseorders_mpsandbox','yes') != '' ? 'checked="checked"' : ''; ?>" />
				    <small><?php _e('Enable this option to use Mercadopago in Sandbox mode','purchase-orders'); ?></small>
				</td>
				</tr>


			</table>
		
			<p class="submit">
			  <input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
			</p>

		</form>

				  <div style="text-align: center;">
					<small><?php _e('If you enjoy this plugin please donate!','purchase-orders')</small>
					<br />
					<form method="post" action="https://www.paypal.com/cgi-bin/webscr" target="_blank">
					  <input type="hidden" name="cmd" value="_s-xclick">
					  <input type="hidden" name="hosted_button_id" value="C2PZ5SY3T8PYN">
					  <input type="image" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" >
					  <img alt="" border="0" src="https://www.paypalobjects.com/es_XC/i/scr/pixel.gif" width="1" height="1">
					</form>
				  </div>

		</div>
		
	<?php
    }

}
