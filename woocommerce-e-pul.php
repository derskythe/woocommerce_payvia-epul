<?php
/* E-PUL PayViaEpul Payment Gateway Class */
class woo_EPUL extends WC_Payment_Gateway
{    
    // Setup our Gateway's id, description and other values
    function __construct()
    {        
        // The global ID for this Payment method
        $this->id = 'woo_epul';
        
        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __('E-PUL PayViaEpul', $this->id);
		
		
		$this->supports           = array(
			'products',
		);
        
        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __('E-PUL PayViaEpul Payment Gateway Plug-in for WooCommerce', $this->id);
        
        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __('E-PUL PayViaEpul', $this->id);
        
        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = $this->get_icon();
        
        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = false;
        
        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        
        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
        
        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }
		
		$this->callback_name = 'woocommerce_api_'.strtolower(get_class($this));
		add_action($this->callback_name, array($this, 'check_pay_response'), 10, 0);		        
        
        // Lets check for SSL
        add_action('admin_notices', array(
            $this,
            'do_ssl_check'
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
				
		
        $this->logger        = wc_get_logger();
        $this->loggerContext = array(
            'source' => $this->id
        );		
		
		if (isset($_GET['wc-api']) && $_GET['wc-api'] == $this->callback_name) {
			$this->check_pay_response();
		}
	} // End __construct()	
    
    // Build the administration fields for this specific Gateway
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', $this->id),
                'label' => __('Enable this payment gateway', $this->id),
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', $this->id),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', $this->id),
                'default' => __('Credit card', $this->id)
            ),
            'description' => array(
                'title' => __('Description', $this->id),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', $this->id),
                'default' => __('Pay securely using your credit card.', $this->id),
                'css' => 'max-width:350px;'
            ),
            'api_login' => array(
                'title' => __('E-PUL PayViaEpul API Login', $this->id),
                'type' => 'text',
                'desc_tip' => __('This is the API Login provided by E-PUL PayViaEpul when you signed up for an account.', $this->id)
            ),
            'api_password' => array(
                'title' => __('E-PUL PayViaEpul Password', $this->id),
                'type' => 'password',
                'desc_tip' => __('This is the password provided by E-PUL PayViaEpul when you signed up for an account.', $this->id)
            ),            
            'redirect_page_id' => array(
                'title' => __('Return Page', $this->id),
                'type' => 'select',
                'options' => $this->get_pages('Select Page'),
                'description' => __('URL of success page', $this->id)
            )
        );
    }
	
	/**
	 * Check if this gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}		

		if ( ! $this->api_login || ! $this->api_password ) {
			return false;
		}

		return true;
	}
    
    // Submit payment and handle response
    public function process_payment($order_id)
    {
        global $woocommerce, $wp_version;
        
		try {
			$this->logger->info('Start payment', $this->loggerContext);
			$customer_order  = new WC_Order($order_id);		
			$environment_url = 'https://www.e-pul.az/epay/pay_via_epul/register_transaction';
        			
			$redirect_url = $woocommerce->api_request_url($this->callback_name.'&epul=true&transaction_id=' . $order_id);
			$this->logger->info('Redirect URL: ' . $redirect_url, $this->loggerContext);
			
			$productinfo = "Order $order_id";
			
			$pay_args = array(
				// E-PUL PayViaEpul Credentials and API Info
				'username' 		=> $this->api_login,
				'password' 		=> $this->api_password,				
				'description' 	=> $productinfo,
				'amount' 		=> $customer_order->get_total() * 100.0,
				'backUrl' 		=> $redirect_url,
				'errorUrl' 		=> $redirect_url,
				'transactionId'	=> $order_id
			);
        
			$this->logger->info('Request params: ' . print_r($pay_args, TRUE), $this->loggerContext);
        
            // Send this payload to E-PUL PayViaEpul for processing            
            $response = wp_remote_post($environment_url, array(
				'user-agent'  	=> 'WordPress/' . $wp_version . '; ' . home_url(),
				'method'		=> 'POST',
                'body' 			=> $pay_args,
                'timeout' 		=> 90,
                'sslverify' 	=> true,
				'cookies' 		=> array()
            ));
            
            if (is_wp_error($response)) {
                $this->logger->error('Response: ' . $response->get_error_message(), $this->loggerContext);
                throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', $this->id));
            }
                        
            if (empty($response['body'])) {
                throw new Exception(__('E-PUL PayViaEpul\'s Response was empty.', $this->id));
            }
            
            // Retrieve the body's resopnse if no errors found
            $raw_body = wp_remote_retrieve_body($response);
            $this->logger->info('Reponse body: ' . $raw_body, $this->loggerContext);
            $response_body = json_decode($raw_body, true);
            
            
            if (($response_body['success'] == 'true') || ($response_body['success'] == '1')) {
				$customer_order->update_status( 'pending' );
                return array(
                    'result' => 'success',
                    'redirect' => $response_body['forwardUrl']
                );
            } else {
                wc_add_notice('Unknown error. Can\'t create order', 'error');
                $customer_order->add_order_note('Error: ' . 'Unknown error. Can\'t create order');
            }
        }
        catch (Exception $e) {
            $this->logger->error('Error occured: ' . $e->getMessage(), $this->loggerContext);
            wc_add_notice('Unknown error occured', 'error');
            $customer_order->add_order_note('Error: Unknown error occured');
        }
    }
    
    public function check_pay_response()
    {        
		$this->logger->info('check_pay_response', $this->loggerContext);
		global $woocommerce, $wp_version;
		$redirect_url = $woocommerce->cart->get_cart_url();
		
        if (!isset($_GET['transaction_id'])) {
			wp_redirect($redirect_url);
			exit;
        }
        
        $trans_id = $_GET['transaction_id'];
        if ($trans_id == '') {
			wp_redirect($redirect_url);
			exit;
        }		
		
        try {				
        	$this->logger->info('Starting to check ' . $trans_id, $this->loggerContext);
            $order = new WC_Order($trans_id);
			$redirect_url = $this->get_return_url($order);
			
			if ($order->get_status() == 'processing') {
				$this->logger->info('Already completed order! ' . $trans_id, $this->loggerContext);
				wp_redirect($redirect_url);
				exit;
			}
			
                        
            $environment_url = 'https://www.e-pul.az/epay/pay_via_epul/check_transaction';
            
            $order_id = $_GET['orderId'];
            $pay_args = array(
                // E-PUL PayViaEpul Credentials and API Info
                'password' => $this->api_password,
                'username' => $this->api_login,
                'orderId' => $order_id
            );
			
			$this->logger->info('Request params: ' . print_r($pay_args, TRUE), $this->loggerContext);
            
            // Send this payload to E-PUL PayViaEpul for processing
            $response = wp_remote_post($environment_url, array(
				'user-agent'  	=> 'WordPress/' . $wp_version . '; ' . home_url(),                
				'method'		=> 'POST',
                'body' 			=> $pay_args,
                'timeout' 		=> 90,
                'sslverify' 	=> true
            ));
            
            if (is_wp_error($response)) {
                throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', $this->id));
            }
            
            if (empty($response['body'])) {
                throw new Exception(__('E-PUL PayViaEpul\'s Response was empty.', $this->id));
            }
            
            // Retrieve the body's resopnse if no errors found
            $raw_body = wp_remote_retrieve_body($response);
            $this->logger->info('Reponse body: ' . $raw_body, $this->loggerContext);
            $response_body = json_decode($raw_body, true);
            
            if (($response_body['success'] == 'true') || ($response_body['success'] == '1')) {
				$this->logger->info('Payment successful '.$order_id, $this->loggerContext);
				$order->add_order_note( __('External OrderID: '.$order_id, $this->id) );
                $order->payment_complete();
                $order->reduce_order_stock();
                $order->add_order_note('Payment successful<br/>');
                $woocommerce->cart->empty_cart();
            } else {
				$this->logger->info('Transaction Declined '.$order_id, $this->loggerContext);
                $order->update_status('failed');
                $order->add_order_note('Failed');
                $order->add_order_note('Transaction Declined');
            }
        }
        catch (Exception $e) {
            $this->logger->error('Error occured: ' . $e->getMessage(), $this->loggerContext);
        }
		
		wp_redirect($redirect_url);
		exit;
    }
    
    // Validate fields
    public function validate_fields()
    {
        return true;
    }
    
    function get_pages($title = false, $indent = true) 
    {
        $wp_pages  = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title)
            $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page  = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
	
	public function get_icon() {
        return apply_filters( 'woocommerce_gateway_icon', '<img src="' . plugin_dir_url( __FILE__ ) . 'epul.png" alt="' . esc_attr( $this->get_title() ) . '" height="32" width="32" />', $this->id );
    }
	
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return false;
    }
    
    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check()
    {
        if ($this->enabled == 'yes') {
            if (get_option('woocommerce_force_ssl_checkout') == 'no') {
                echo "<div class=\"error\"><p>" 
				. sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }
    
} // End of woo_epul