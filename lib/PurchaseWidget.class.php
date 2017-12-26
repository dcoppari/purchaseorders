<?php

class PurchaceOrdersWidget extends WP_Widget 
{
 
    function PurchaceOrdersWidget() {
        parent::WP_Widget(false, $name = 'Purchase Orders Widget');	
    }
 
    function widget($args, $instance) 
    {	
    
        extract( $args );
        
        $title = apply_filters('widget_title', $instance['title']); 
        ?>
              <?php echo $before_widget; ?>
                  <?php if ( $title ) echo $before_title . $title . $after_title; ?>
                  <div class="purchaceorderswidget">
                	<?php echo self::listCart($instance); ?>
                	<span class="purchaceorderswidgetwait"></span>
                  </div>
              <?php echo $after_widget; ?>
        <?php
    }
 
    function update($new_instance, $old_instance) 
    {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['profit'] = $new_instance['profit'];
		
        return $instance;
    }
 
    function form($instance) 
    {
        $title  = esc_attr($instance['title']); 
        $profit = esc_attr($instance['showprofit']); 
        
        ?>

        <p>
        	<label for="<?=$this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
        	<input type="text" class="widefat" id="<?=$this->get_field_id('title'); ?>" name="<?=$this->get_field_name('title'); ?>" value="<?=$title; ?>" />
        </p>

        <p>
        	<label for="<?=$this->get_field_id('showprofit'); ?>"><?php _e('Show Profit Input'); ?></label>
        	<input type="checkbox" id="<?=$this->get_field_id('showprofit'); ?>" name="<?=$this->get_field_name('showprofit'); ?>" value="1" <?php checked( '1', $showprofit ); ?> />
        </p>

        <?php 
    }
 
    public static function listCart($instance)
    {
        $hidePrices  = (get_option('purchaseorders_hideprices', '') != '');
		$cart = array();
		$profit = 0;
		$order = '';
		
		$showprofit = checked( '1', esc_attr($instance['showprofit']) ); 
		
        if ( isset($_SESSION['cart']) && isset($_SESSION['cart']['profit']) ) 
		   $profit = $_SESSION['cart']['profit'] + 0;
		
        if ( isset($_SESSION['cart']) && is_array($_SESSION['cart']['items']) ) 
		   $cart = apply_filters('purchaseorders_widget_order_items', $_SESSION['cart']['items']);	

		ob_start(); ?>

		<?php if($showprofit) : ?>

		<div class="purchaseorderswidgetprofit">
	        <label for="profit"><?php _e('Profit','purchase-orders'); ?></label>
	        <input type="text" name="profit" value="<?=$profit; ?>">
		</div>
		
		<?php endif; ?>
		
		<div class="purchaseorderswidgetlist">
		
		  <?php if( count($cart) ) : ?>

			<table class="purchaseorderswidgettable">

				<thead>
					<tr>
						<td>Producto</td>
						<td><?php _e('Qty','purchase-orders') ?></td>
						<?php if(!$hidePrices) : ?>
						<td><?php _e('Price','purchase-orders') ?></td>
						<td><?php _e('Total','purchase-orders') ?></td>
						<?php endif; ?>
						<td>&nbsp;</td>
					</tr>
				</thead>
				
				<tbody><?php foreach($cart as $key => $item) : ?>
				
					<?php
					$removeArr = json_encode( array( 'cmd' => '_remove', 'item_order' => $key ) );
					$remove = "onclick='purchaseOrders($removeArr)'";
					$price  = $profit != 0 ? ($item['amount'] * (1 + ($profit / 100))) : $item['amount'];
					$total  = $profit != 0 ? ($item['total'] * (1 + ($profit / 100))) : $item['total'];
					$grandtotal = $grandtotal + $total;
					?>

	          	    <tr>
						<td><span class="cart-itemname"><?=$item['item_name']; ?></span></td>
		          		<td><input class="cart-itemquantity" data-itemorder="<?=$key ?>" name="quantity" type="number" min="1" max="999" value="<?=$item['quantity']; ?>" /></td>
		          		<?php if(!$hidePrices) : ?>
						  <td style="text-align: right;"><span class="cart-itemprice"><?=number_format($price,2); ?></span></td>
						  <td style="text-align: right;"><span class="cart-itemprice"><?=number_format($total,2); ?></span></td>
						<?php endif; ?>
						<td><span class="cart-remove" style="cursor: pointer" <?=$remove; ?> data-itemorder="<?=$key; ?>"><i class="fa fa-times-circle" aria-hidden="true"></i></span></td>					
				    </tr>

				<?php endforeach; ?></tbody>
				
			    <tfoot>
			    	<tr>
			    		<td colspan="5" class="purchaseorderswidgettotal"><?=number_format($grandtotal,2); ?></td>
			    	</tr>
			    </tfoot>
		    
			</table>
		
		  <?php else : ?>

			<p><?php _e('Cart is empty','purchase-orders'); ?></p>

		  <?php endif; ?>

        </div>
		
		<?php 

		$order = ob_get_contents(); ob_end_clean();

		return $order;
    } 
}

add_action('widgets_init', create_function('', 'return register_widget("PurchaceOrdersWidget");'));
