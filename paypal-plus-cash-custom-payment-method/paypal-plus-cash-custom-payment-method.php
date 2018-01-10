<?php

/**
 * Plugin Name: Custom WooCommerce PayPal Gateway
 * Version: 1.0.1
 * Author: I. Kancijan
 * Author URI: https://github.com/IKancijan
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('WOO_PAYMENT_DIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', 'woo_payment_gateway');

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\ExecutePayment;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;

/**
 * Add Gateway class to all payment gateway methods
 */
function woo_add_gateway_class($methods){
    
    $methods[] = 'PayPal_Plus_Cash_Gateway';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'woo_add_gateway_class');

function woo_payment_gateway(){
    
    if(!class_exists('WC_Payment_Gateway')) return;

    class PayPal_Plus_Cash_Gateway extends WC_Payment_Gateway{
        
        /**
         * API Context used for PayPal Authorization
         * @var null
         */
        public $apiContext = null;
        
        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public function __construct(){

            $this->id                 = 'PayPal_Plus_Cash_Gateway';
            $this->method_title       = __('Paypal + Cash', 'woo_paypal');
            $this->method_description = __('Allows payments with custom gateway.', 'woo_paypal');
            
            $this->has_fields = false;

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            
            $this->supports = array(
                'products'
            );
            
            $this->get_paypal_sdk();
            
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            $this->enabled = $this->get_option('enabled');
            
            add_action('check_ppc_paypal_plus_cash', array(
                $this,
                'check_response'
            ));
            
            // Save settings
            if (is_admin()) {
                // Versions over 2.0
                // Save our administration options. Since we are not going to be doing anything special
                // we have not defined 'process_admin_options' in this class so the method in the parent
                // class will be used instead
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    $this,
                    'process_admin_options'
                ));
            }
        }

		public function payment_fields(){

            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }
        }
        
        private function get_paypal_sdk()
        {
            require_once WOO_PAYMENT_DIR . 'includes/paypal-sdk/autoload.php';
        }
        
        
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable', 'woo_paypal'),
                    'type' => 'checkbox',
                    'label' => __('Enable Paypal + Cash', 'woo_paypal'),
                    'default' => 'yes'
                ),
                'client_id' => array(
                    'title' => __('Client ID', 'woo_paypal'),
                    'type' => 'text',
                    'default' => ''
                ),
                'client_secret' => array(
                    'title' => __('Client Secret', 'woo_paypal'),
                    'type' => 'password',
                    'default' => ''
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woo_paypal' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woo_paypal' ),
                    'default'     => __( 'Paypal + Cash', 'woo_paypal' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woo_paypal'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'woo_paypal'),
                    'default' => __('Pay 20% now and the rest at arrival.', 'woo_paypal'),
                    'desc_tip' => true
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', 'woo_paypal' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woo_paypal' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'amount_online' => array(
                    'title' => __('Percentage to pay online', 'woo_paypal'),
                    'type' => 'number',
                    //'description' => __( 'This controls the title which the user sees during checkout.', 'woo_paypal' ),
                    'default' => __('20', 'woo_paypal'),
                    'desc_tip' => false
                ),
            );
        }
        
        private function get_api_context()
        {
            
            $client_id           = $this->get_option('client_id');
            $client_secret       = $this->get_option('client_secret');
            $this->apiContext    = new ApiContext(new OAuthTokenCredential($client_id, $client_secret));
            $this->amount_online = $this->get_option('amount_online');
            $this->description   = $this->get_option('description');
            $this->instructions  = $this->get_option('instructions', $this->description );
 
            $this->apiContext->setConfig(
                array(
                    'mode' => 'live'
                )
            );
        }
        
        public function process_payment($order_id)
        {
            
            global $woocommerce;
            $order = new WC_Order($order_id);

            $this->get_api_context();
            
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");
            
            $all_items = array();
            $subtotal  = 0;
            // Products
            foreach ($order->get_items(array('line_item', 'fee')) as $item) {
                
                $itemObject = new Item();
                $itemObject->setCurrency(get_woocommerce_currency());
                
                if ('fee' === $item['type']) {
                    
                    $itemObject->setName(__('Fee', 'woo_paypal'));
                    $itemObject->setQuantity(1);

                    $order_custom_totals = 0;
                    if(!empty($this->amount_online) && $this->amount_online !== 0){
                        $order_custom_totals = (($order->total*$this->amount_online)/100);
                        $order_custom_totals = number_format($order_custom_totals, 2, '.', '');

                        $itemObject->setPrice($order_custom_totals);
                        $order->set_total($order_custom_totals);
                    }else{
                        $itemObject->setPrice($item['line_total']);
                    }
                    $subtotal += $item['line_total'];
                } else {
                    
                    $product = $order->get_product_from_item($item);
                    $sku     = $product ? $product->get_sku() : '';
                    $itemObject->setName($item['name']);

                    $order_custom_totals = 0;
                    if(!empty($this->amount_online) && $this->amount_online !== 0){
                        $order_custom_totals = (($order->total*$this->amount_online)/100);
                        $order_custom_totals = number_format($order_custom_totals, 2, '.', '');

                        $itemObject->setPrice($order_custom_totals);
                        $order->set_total($order_custom_totals);

                    }else{
                        $itemObject->setPrice($order->get_item_subtotal($item, false));
                    }

                    $subtotal += $order->get_item_subtotal($item, false) * $item['qty'];
                    if ($sku) {
                        $itemObject->setSku($sku);
                    }
                }
    
                $itemObject->setQuantity(1);
                $all_items[] = $itemObject;
            }

            $itemList = new ItemList();
            $itemList->setItems($all_items);
            // ### Additional payment details
            // Use this optional field to set additional
            // payment information such as tax, shipping
            // charges etc.
            $details = new Details();
            $details->setShipping($order->get_total_shipping())->setTax($order->get_total_tax())->setSubtotal($order->get_total());
            
            $amount = new Amount();
            $amount->setCurrency(get_woocommerce_currency())->setTotal($order->get_total())->setDetails($details);

            update_post_meta( $order_id, 'amount_online', $order->get_total() );

            $transaction = new Transaction();
            $transaction->setAmount($amount)->setItemList($itemList)->setInvoiceNumber(uniqid());

            $baseUrl = $this->get_return_url($order);
            
            if (strpos($baseUrl, '?') !== false) {
                $baseUrl .= '&';
            } else {
                $baseUrl .= '?';
            }
            
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl($baseUrl . 'kT_paypal_plus_cash=true&order_id=' . $order_id)->setCancelUrl($baseUrl . 'kT_paypal_plus_cash=cancel&order_id=' . $order_id);
            
            $payment = new Payment();
            $payment->setIntent("sale")->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array($transaction));
            
            try {
                
                $payment->create($this->apiContext);
                
                $approvalUrl = $payment->getApprovalLink();
                
                return array(
                    'result' => 'success',
                    'redirect' => $approvalUrl
                );
                
            }
            catch (Exception $ex) {
                
                // wc_add_notice("CODE: ".$ex->getCode(), 'error');
                // wc_add_notice("DATA: ".$ex->getData(), 'error');
                 wc_add_notice($ex->getMessage(), 'error');
                // wc_add_notice("TRANSACTION: ".$transaction, 'error');
                // wc_add_notice("itemList: ".$itemList, 'error');
            }
            
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
            
        }
        
        public function check_response()
        {
            global $woocommerce;

            if (isset($_GET['kT_paypal_plus_cash'])) {
                
                $kT_paypal_plus_cash = $_GET['kT_paypal_plus_cash'];
                $order_id  = $_GET['order_id'];

                if ($order_id == 0 || $order_id == '') {
                    return;
                }
                
                $order = new WC_Order($order_id);

               
                if ($order->has_status('completed') || $order->has_status('processing')) {
                    return;
                }
                
                if ($kT_paypal_plus_cash == 'true') {
                    $this->get_api_context();
                    
                    $paymentId = $_GET['paymentId'];
                    $payment   = Payment::get($paymentId, $this->apiContext);
                    
                    $execution = new PaymentExecution();
                    $execution->setPayerId($_GET['PayerID']);
                    
                    $transaction = new Transaction();
                    $amount      = new Amount();
                    $details     = new Details();
                    
                   /* $subtotal = 0;
                    // Products
                    foreach ($order->get_items(array('line_item', 'fee')) as $item) {
                        
                        if ('fee' === $item['type']) {
                            
                            $subtotal += $item['line_total'];
                        } else {
                            
                            $order_custom_totals = (($item['line_total'] * $this->amount_online)/100);
                            $subtotal += $order_custom_totals;
                        }
                    }*/

                    //$subtotal = number_format($subtotal, 2, '.', '');

                    $order_custom_totals = (($order->get_total() * $this->amount_online)/100);

                    $details->setShipping($order->get_total_shipping())->setTax($order->get_total_tax())->setSubtotal($order_custom_totals);
                    
                    $amount = new Amount();
                    $amount->setCurrency(get_woocommerce_currency())->setTotal($order_custom_totals)->setDetails($details);
                    
                    $transaction->setAmount($amount);
                    
                    $execution->addTransaction($transaction);

                    
                    try {
                        
                        $result = $payment->execute($execution, $this->apiContext);
                        
                    }
                    catch (Exception $ex) {
                        
                        wc_add_notice($ex->getMessage(), 'error');
                        
                        $order->update_status('failed', sprintf(__('%s payment failed! Transaction ID: %d', 'woocommerce'), $this->title, $paymentId) . ' ' . $ex->getMessage());
                        return;
                    }
                    
                    // Payment complete
                    $order->payment_complete($paymentId);
                    // Add order note
                    $order->add_order_note(sprintf(__('%s payment approved! Trnsaction ID: %s', 'woocommerce'), $this->title, $paymentId));
                    $order->add_order_note('Paypal uplata: '.get_woocommerce_currency_symbol().$order_custom_totals );

                    // Remove cart
                    $woocommerce->cart->empty_cart();
                    
                }
                
                if ($kT_paypal_plus_cash == 'cancel') {
                    
                    $order = new WC_Order($order_id);
                    //dump_data("order", $order);

                    $order->update_status('failed', sprintf(__('%s payment failed! Transaction ID: %d', 'woocommerce'), $this->title));
                    wc_clear_notices();
                    wc_add_notice(esc_html( 'Your payment has been cancelled!' ), 'error');
                }
            }
            return;
        }
    }
}
add_action('init', 'check_for_ppc_paypal_plus_cash');

function check_for_ppc_paypal_plus_cash()
{
    if (isset($_GET['kT_paypal_plus_cash'])) {
        // Start the gateways
        WC()->payment_gateways();
        
        do_action('check_ppc_paypal_plus_cash');
    }
    
}

// tema ne koristi shop, pa ni nema potreba da se prikazuje korisniku
function woocommerce_disable_shop_page() {
    if (is_shop()):
    wp_redirect( home_url() ); exit;
    endif;
}
add_action( 'wp', 'woocommerce_disable_shop_page' );

// ispisuje polja na checkoutu
function get_amount_online(){
    
    global $woocommerce;
    $kt_gateway = new PayPal_Plus_Cash_Gateway();

    // hack da dobijem chosen_payment_method s protected objekta
    $obj_array = (Array)$woocommerce->session;
    $chosen_payment_method = $obj_array["\0*\0" . '_data']["chosen_payment_method"];

    // ako nije odaban PayPal_Plus_Cash_Gateway sakrij polja
    $visable = "";
    if($chosen_payment_method !== 'PayPal_Plus_Cash_Gateway'){
        $visable = "style='display:none'";
    }

    // izračunaj online
    $order_custom_totals = (($woocommerce->cart->total*$kt_gateway->settings['amount_online'])/100);
    $order_custom_totals = number_format($order_custom_totals, 2, '.', '');
    $cash = number_format(($woocommerce->cart->total - $order_custom_totals), 2, '.', '');

    $output .= '<tr class="cart-online" '.$visable.'>
                    <th>'. __( 'Paypal', 'woocommerce' ).'</th>
                    <td>'.get_woocommerce_currency_symbol().$order_custom_totals.'</td>
                </tr>';
    $output .= '<tr class="cart-cash" '.$visable.'>
                    <th>'. __( 'Cash', 'woocommerce' ).'</th>
                    <td>'.get_woocommerce_currency_symbol().$cash.'</td>
                </tr>';
    echo $output;

}
add_action( 'woocommerce_ppc_before_subtotal', 'get_amount_online' );

// js
function kt_plugin_scripts() {
    wp_enqueue_script( 'kt-plugin-script', plugin_dir_url( __FILE__ ) . '/js/main.js', array( 'jquery' ));
}
add_action( 'wp_enqueue_scripts', 'kt_plugin_scripts' );

function kt_admin_order_totals($order_id) {

    global $woocommerce;
    $order = new WC_Order( $order_id );

    $amount_online = get_post_meta($order_id, "amount_online")[0];
    $cash = $order->total - $amount_online;
    $cash = number_format($cash, 2, '.', '');

    //dump_data('kt_admin_order_totals', $cash);
    if( ! empty( $amount_online )) : ?>
        <tr>
            <td class="label"><?php _e( 'Paypal:', 'ktdizajn' ); ?></td>
            <td width="1%"></td>
            <td class="total">
                <span class="woocommerce-Price-amount amount">
                <?php echo get_woocommerce_currency_symbol().$amount_online; ?>
                </span>
            </td>
        </tr>
    <?php 

    if( ! empty( $cash )) : ?>
        <tr>
            <td class="label"><?php _e( 'Cash:', 'ktdizajn' ); ?></td>
            <td width="1%"></td>
            <td class="total">
                <span class="woocommerce-Price-amount amount">
                <?php echo get_woocommerce_currency_symbol().$cash; ?>
                </span>
            </td>
        </tr>

    <?php endif;
    endif;
}
add_action( 'woocommerce_admin_order_totals_after_discount', 'kt_admin_order_totals' );