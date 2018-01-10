<?php
/**
 * Order Customer Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-customer.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_meta = get_post_meta($order->id);
$_billing_first_name = $order_meta['_billing_first_name'][0];
$_billing_last_name = $order_meta['_billing_last_name'][0];
$_billing_company = $order_meta['_billing_company'][0];
$_billing_address_1 = $order_meta['_billing_address_1'][0];
$_billing_address_2 = $order_meta['_billing_address_2'][0];
$_billing_city = $order_meta['_billing_city'][0];
$_billing_state = $order_meta['_billing_state'][0];
$_billing_postcode = $order_meta['_billing_postcode'][0];

//dump_data("asd", $order_meta);
?>

<section class="woocommerce-customer-details">

	<h2><?php _e( 'Customer details', 'woocommerce' ); ?></h2>

	<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">

		<?php if ( $order->get_customer_note() ) : ?>
			<tr>
				<th><?php _e( 'Note:', 'woocommerce' ); ?></th>
				<td><?php echo wptexturize( $order->get_customer_note() ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_first_name) ) : ?>
			<tr>
				<th><?php _e( 'Name:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_first_name.' '.$_billing_last_name ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( $order->get_billing_email() ) : ?>
			<tr>
				<th><?php _e( 'Email:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $order->get_billing_email() ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( $order->get_billing_phone() ) : ?>
			<tr>
				<th><?php _e( 'Phone:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $order->get_billing_phone() ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_company) ) : ?>
			<tr>
				<th><?php _e( 'Company:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_company ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_address_1) ) : ?>
			<tr>
				<th><?php _e( 'Address 1:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_address_1 ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_address_2) ) : ?>
			<tr>
				<th><?php _e( 'Address 2:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_address_2 ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_city) ) : ?>
			<tr>
				<th><?php _e( 'City:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_city ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_state) ) : ?>
			<tr>
				<th><?php _e( 'State:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_state ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( !empty($_billing_postcode) ) : ?>
			<tr>
				<th><?php _e( 'Postcode:', 'woocommerce' ); ?></th>
				<td><?php echo esc_html( $_billing_postcode ); ?></td>
			</tr>
		<?php endif; ?>

		<?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

	</table>

</section>
