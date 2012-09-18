<?php
/**
 * PayPal Advanced
 * @class PayPalAdvanced
 *
 * @author John Dillick
 * @version 1.0.2
 * @copyright Ingenesis Limited, 2012
 * @package Shopp
 * @since 1.2
 * @subpackage PayPalAdvanced
 *
 * $Id: PayPalAdvanced.php 11 2012-07-26 21:30:33Z jdillick $
 **/

class PayPalAdvanced extends GatewayFramework implements GatewayModule {
	// Gateway Framework Flags
	var $secure 	= false; // SSL Requirement
	var $saleonly	= true;

	var $host = array (
			'live' => 'payflowpro.paypal.com',
			'test' => 'pilot-payflowpro.paypal.com',
			'link' => 'payflowlink.paypal.com'
		);

	function __construct() {
		parent::__construct();
		$this->setup('username','vendor','partner','password','silenturl');

		// Order Event Handlers
		add_action('shopp_paypaladvanced_sale',		array($this,'sale'));
		add_action('shopp_txn_update',				array($this,'update'));
		add_action('shopp_save_payment_settings',	array($this,'silenturl'));
	}

	function actions () {
		add_action('shopp_remote_payment',			array($this,'remote'));
	}

	function update () {
		// Not a valid update
		if ( ! isset($_GET['_txnupdate']) ||
				$_GET['_txnupdate'] != strtolower($this->module) ||
				! isset($_GET['verify']) ||
				! $this->verify($_GET['verify'])
			) return;

		if(SHOPP_DEBUG) new ShoppError('Silent post update: '._object_r($_POST),false,SHOPP_DEBUG_ERR);

		$R = $this->response($_POST);

		add_filter('shopp_agent_is_robot', create_function('$isrobot','return false;'));
		// load the desired session, which leaves the previous/defunct Order object intact
		Shopping::resession($R->sessionid);

		// destroy the defunct Order object from defunct session and restore the Order object from the loaded session
		// also assign the restored Order object as the global Order object
		$this->Order = ShoppOrder( ShoppingObject::__new( 'Order', ShoppOrder() ) );

		$Shopping = ShoppShopping();

		// Couldn't load the session data
		if ($Shopping->session != $R->sessionid)
			return new ShoppError("Session could not be loaded: {$R->sessionid}",false,SHOPP_DEBUG_ERR);
		else new ShoppError("PayPal successfully loaded session: {$R->sessionid}",false,SHOPP_DEBUG_ERR);

		$Purchase = ShoppPurchase(shopp_order( (int) $R->order));
		$Purchase->listeners();

		if ( '0' != $R->result ) {
			// Transaction log the failure event
			shopp_add_order_event($R->order, 'auth-fail',array(
				'amount' => $Purchase->total,						// Amount of failure
				'error' => $R->result,								// Error code (if provided)
				'message' => $R->msg,								// Error message reported by the gateway
				'gateway' => $this->module,							// Gateway handler name (module name from @subpackage)
			));

			// Add code to debug log.
			if(SHOPP_DEBUG) new ShoppError("[Code {$R->result}] {$R->msg}",'paypal_advanced_debug',SHOPP_DEBUG_ERR);

			// Display the failure message
			return new ShoppError($R->msg, 'paypal_advanced_error', SHOPP_TRXN_ERR);
		}

		// Transaction log any warning messages
		if ( 'Approved' != $R->msg ) {
			shopp_add_order_event($R->order, 'review', array(
				'kind' => 'Warning',	// Warning message
				'note' => $R->msg 		// The message to log for the order
			));
		}

		// Transaction log any review information
		if ( ! empty($R->review) ) {
			foreach ( $R->review as $kind => $note ) {
				shopp_add_order_event($R->order, 'review', array(
					'kind' => $kind,	// The kind of fraud review: AVS (address verification system), CVN (card verification number), FRT (fraud review team)
					'note' => $note 	// The message to log for the order
				));
			}
		}

		add_action('shopp_captured_order_event',array($this->Order,'notify'));
		add_action('shopp_captured_order_event',array($this->Order,'accounts'));
		shopp_add_order_event( $R->order, 'authed', array(
			'txnid' => $R->txnid,						// Transaction ID
			'amount' => $Purchase->invoiced,			// Gross amount authorized
			'gateway' => $this->module,					// Gateway handler name (module name from @subpackage)
			'paymethod' => $this->settings['label'],	// Payment method (payment method label from payment settings)
			'paytype' => $this->settings['label'],		// Type of payment (check, MasterCard, etc)
			'payid' => '',								// Payment ID (last 4 of card or check number)
			'fees'	=> 0.00,
			'capture' => true
		));
		do_action('shopp_order_success',ShoppPurchase());
		die('OK');
	}

	function remote () {
		$R = $this->response($_POST);
		if ( isset($R->result) && '0' == $R->result ) {
			ShoppOrder()->inprogress = false;
			ShoppOrder()->purchase = (int) $R->order;
			Shopping::resession();
			shopp_redirect(shoppurl(false,'thanks'));
		}

		// Transaction log the failure event
		shopp_add_order_event($R->order, 'auth-fail',array(
			'amount' => $this->Order->Cart->total,				// Amount of failure
			'error' => $R->result,								// Error code (if provided)
			'message' => $R->msg,								// Error message reported by the gateway
			'gateway' => $this->module,							// Gateway handler name (module name from @subpackage)
		));

		// Add code to debug log.
		if(SHOPP_DEBUG) new ShoppError("[Code {$R->result}] {$R->msg}",'paypal_advanced_debug',SHOPP_DEBUG_ERR);

		// Display the failure message
		return new ShoppError($R->msg, 'paypal_advanced_error', SHOPP_TRXN_ERR);

	}

	/**
	 * Handler for auth, sale, capture, void, and refund order events
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return void
	 **/
	function sale ( OrderEventMessage $e ) {
		if ( 'sale' != $e->name ) return;
		// Already have secure token
		if ( empty($this->securetoken) || empty($this->securetokenid) ) {

			// build the request
			$this->build($e);

			$h = array();
			$h['Content-Length'] = strlen($this->transaction);
			$h['Content-Type'] = 'text/namevalue';
			$h['Host'] = (str_true($this->settings['testmode']) ? $this->host['test'] : $this->host['live']);
			$h['X-VPS-REQUEST-ID'] = md5($this->transaction.ShoppShopping()->session);
			$h['X-VPS-CLIENT-TIMEOUT'] = SHOPP_GATEWAY_TIMEOUT;
			$h['X-VPS-VIT-INTEGRATION-PRODUCT'] = 'Shopp plugin for WordPress';
			$h['X-VPS-VIT-INTEGRATION-VERSION'] = SHOPP_VERSION;

			$r = parent::send($this->transaction, 'https://'.$h['Host'], false, array( 'headers' => $h ));
			$R = $this->response($r);

			if ( "0" == $R->result || true ) { // remove true when done debugging
				$this->securetoken = $R->securetoken;
				$this->securetokenid = $R->securetokenid;
				shopp_set_meta($e->order,'purchase','securetoken',$this->securetoken);
				shopp_set_meta($e->order,'purchase','securetokenid',$this->securetokenid);

			} else {
				// Transaction log the failure event
				shopp_add_order_event($e->order, $e->name.'-fail',array(
					'amount' => (isset($e->amount)?$e->amount:false),	// Amount of failure
					'error' => $R->result,								// Error code (if provided)
					'message' => $R->msg,								// Error message reported by the gateway
					'gateway' => $this->module,							// Gateway handler name (module name from @subpackage)
				));

				// Add code to debug log.
				if(SHOPP_DEBUG) new ShoppError("[Code {$R->result}] {$R->msg}",'payflow_link_debug',SHOPP_DEBUG_ERR);

				// Display the failure message
				return new ShoppError($R->msg, 'payflow_link_error', SHOPP_TRXN_ERR);
			}
		}

		$form = array(
			'SECURETOKEN' => $this->securetoken,
			'SECURETOKENID' => $this->securetokenid,
			'MODE' => str_true($this->settings['testmode']) ? 'TEST' : 'LIVE',
		);
		shopp_redirect(add_query_arg($form, 'https://'.$this->host['link']));
	}

	function build ( OrderEventMessage $e ) {
		if ( 'sale' != $e->name ) return;

		// Transaction Types
		$trntypes = array(
			'sale' 		=> 'S'
		);
		$trntype = $trntypes[$e->name];

		$_ = array();

		// Payflow Account info
		$_['USER']					= (!empty($this->settings['username']) ? $this->settings['username'] : $this->settings['vendor']);
		$_['VENDOR']				= $this->settings['vendor'];
		$_['PARTNER']				= !empty($this->settings['partner']) ? $this->settings['partner'] : 'PayPal';
		$_['PWD']					= $this->settings['password'];

		// Amount only for sale
		$_['INVNUM']				= $e->order;
		$_['AMT']					= $e->amount;
		$_['TRXTYPE'] 				= $trntype;	// Transaction Type A for auth, S for sale

		// Create secure token
		$_['CREATESECURETOKEN']		= 'Y';
		$_['SECURETOKENID']			= md5($e->order.time());

		// Customer Contact
		$_['FIRSTNAME']				= $this->Order->Customer->firstname;
		$_['LASTNAME']				= $this->Order->Customer->lastname;
		$_['EMAIL']					= $this->Order->Customer->email;
		$_['PHONENUM']				= $this->Order->Customer->phone;

		// Billing info
		$_['BILLTOCOUNTRY']			= $this->Order->Billing->country;
		$_['STATE']					= $this->Order->Billing->state;
		$_['ZIP']					= $this->Order->Billing->postcode;
		$_['STREET']				= $this->Order->Billing->address;
		$_['CITY']					= $this->Order->Billing->city;

		if ( $this->Order->Cart->shipped() ) {
			$_['SHIPTOFIRSTNAME']		= $this->Order->Customer->firstname;
			$_['SHIPTOLASTNAME']		= $this->Order->Customer->lastname;
			$_['SHIPTOSTREET']			= $this->Order->Shipping->address;
			$_['SHIPTOCITY']			= $this->Order->Shipping->city;
			$_['SHIPTOCOUNTRY']			= $this->Order->Shipping->country;
			if ( in_array($this->Order->Shipping->country, array('US','CA')) )
				$_['SHIPTOSTATE']			= $this->Order->Shipping->state;
			$_['SHIPTOZIP']				= $this->Order->Shipping->postcode;
		} else {
			$_['SHIPTOFIRSTNAME']		= $this->Order->Customer->firstname;
			$_['SHIPTOLASTNAME']		= $this->Order->Customer->lastname;
			$_['SHIPTOSTREET']			= $this->Order->Billing->address;
			$_['SHIPTOCITY']			= $this->Order->Billing->city;
			$_['SHIPTOCOUNTRY']			= $this->Order->Billing->country;
			if ( in_array($this->Order->Billing->country, array('US','CA')) )
				$_['SHIPTOSTATE']			= $this->Order->Billing->state;
			$_['SHIPTOZIP']				= $this->Order->Billing->postcode;
		}
		$_['USER1']						= ShoppShopping()->session;

		if(SHOPP_DEBUG) new ShoppError('Request: '._object_r($_),false,SHOPP_DEBUG_ERR);

		$this->transaction = "";
		foreach($_ as $key => $value) {
			if (strlen($this->transaction) > 0) $this->transaction .= "&";
			$this->transaction .= "$key=".esc_attr($value);
		}
		return $this->transaction;
	}

	function settings () {
		$this->cancelurl = shoppurl(false,'checkout',false);
		$this->rmtpay = shoppurl(array('rmtpay'=>strtolower($this->module)),'checkout',false);

		$this->ui->text(0,array(
			'name' => 'username',
			'size' => 30,
			'value' => $this->settings['username'],
			'label' => __('Enter your PayPal Advanced or Payflow Link user account for this site.  Use your merchant login ID if you have not setup multiple users on your PayPal Advanced or Payflow Link account.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'vendor',
			'size' => 30,
			'value' => $this->settings['vendor'],
			'label' => __('Enter your PayPal Advanced or Payflow Link merchant login ID that you created when you registered your account.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'partner',
			'size' => 30,
			'value' => !empty($this->settings['partner']) ? $this->settings['partner'] : 'PayPal',
			'label' => __('Enter your PayPal Advanced or Payflow Link resellers ID.  If you purchased your account directly from PayPal, use PayPal','Shopp')
		));

		$this->ui->password(0,array(
			'name' => 'password',
			'size' => 16,
			'value' => $this->settings['password'],
			'label' => __('Enter your PayPal Advanced or Payflow Link account password.','Shopp')
		));

		$this->ui->checkbox(0,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));

		if (!empty($this->settings['silenturl'])) {
			$instructions =
				'<ul>'.
					'<li>'.sprintf(__("Copy the following URL to the field labeled %s: %s", 'Shopp'),'<strong>'.__('Enter Cancel URL','Shopp').'</strong>', 		'<strong>'.$this->cancelurl.'</strong>').'</li>'.
					'<li>'.sprintf(__("Copy the following URL to the field labeled %s: %s", 'Shopp'),'<strong>'.__('Enter Return URL','Shopp').'</strong>', 		'<strong>'.$this->rmtpay.'</strong>').'</li>'.
					'<li>'.sprintf(__("Copy the following URL to the field labeled %s: %s", 'Shopp'),'<strong>'.__('Enter Silent Post URL','Shopp').'</strong>', 	'<strong>'.$this->settings['silenturl'].'</strong>').'</li>'.
				'</ul>';

			$this->ui->p(1, array(
				'name' => 'complete',
				'label' => _x("PayPal Manager Set Up",'Header for PayPal Advanced instructions paragraph.','Shopp'),
				'content' => $instructions,
			));
		}
	}

	function response ($buffer) {
		if (empty($buffer)) return false;
		$_ = new stdClass();
		$r = array();
		$verifications = array(
			'AVSADDR' => array(
				'Y' => _x('Address is verified.','Credit card address verification response message','Shopp'),
				'N' => _x('Address is not verified.','Credit card address verification response message','Shopp'),
				'X' => _x('Unable to verify address.','Credit card address verification response message','Shopp')
			),
			'AVSZIP' => array(
				'Y' => _x('Postal code is verified.','Credit card postal code verification response message','Shopp'),
				'N' => _x('Postal code is not verified.','Credit card postal code verification response message','Shopp'),
				'X' => _x('Unable to verify postal code.','Credit card postal code verification response message','Shopp')
			),
			'IAVS' => array(
				'Y' => _x('International address is verified.','Credit card address verification response message','Shopp'),
				'N' => _x('International address is not verified.','Credit card address verification response message','Shopp'),
				'X' => _x('Unable to verify the international address.','Credit card address verification response message','Shopp')
			),
			'CVV2MATCH' => array(
				'Y' => _x('Card security code (CVV2) match.', 'Credit card security code (CVV2) check response.','Shopp'),
				'N' => _x('Card security code (CVV2) did not match.', 'Credit card security code (CVV2) check response.','Shopp'),
				'X' => _x('Unable to verify the Card security code (CVV2) match.', 'Credit card security code (CVV2) check response.','Shopp'),
			),
			'EMAILMATCH' => array(
				'Y' => _x('Email Address match.', 'Credit card email address verification response.','Shopp'),
				'N' => _x('Email Address did not match.', 'Credit card email address verification response.','Shopp'),
				'X' => _x('Unable to verify email address match.', 'Credit card email address verification response.','Shopp'),
			),
			'PHONEMATCH' => array(
				'Y' => _x('Phone number match.', 'Credit card phone number verification response.','Shopp'),
				'N' => _x('Phone number did not match.', 'Credit card phone number verification response.','Shopp'),
				'X' => _x('Unable to verify phone number match.', 'Credit card phone number verification response.','Shopp'),
			)
		);

		if ( ! is_array($buffer) ) {
			$pairs = explode("&",$buffer);
			foreach($pairs as $pair) {
				list($key,$value) = explode("=",$pair);

				if (preg_match("/(\w*?)(\d+)/",$key,$matches)) {
					if (!isset($r[$matches[1]])) $r[$matches[1]] = array();
					$r[$matches[1]][$matches[2]] = urldecode($value);
				} else $r[$key] = urldecode($value);
			}
		} else $r = $buffer;

		// RESULT=0 &PNREF=V19A2E49D876 &RESPMSG=Approved &AUTHCODE=041PNI &AVSADDR=Y &AVSZIP=Y &IAVS=N
		$_->order = $r['INVNUM'];
		$_->result = $r['RESULT'];
		$_->msg = $r['RESPMSG'];
		$_->txnid = $r['PNREF'];
		$_->securetokenid = $r['SECURETOKENID'];
		$_->securetoken = $r['SECURETOKEN'];
		$_->sessionid = $r['USER1'];
		if ( isset($r['AUTHCODE']) ) $_->auth = $r['AUTHCODE'];

		$_->review = array();
		foreach ( $verifications as $test => $responses ) {
			if ( isset($r[$test]) ) $_->review[$test] = $responses[$r[$test]];
		}

		return $_;
	}

	function silenturl () {
		$settings =& $_POST['settings'][$this->module];
		$creds = array('username','vendor','partner','password');
		if ( ! isset($settings['username']) || empty($settings['username']) )
			$settings['username'] = $settings['vendor'];

		$string = '';
		foreach( $creds as $key ) {
			if ( ! isset($settings[$key]) || empty($settings[$key]) ) return;
			$string .= $settings[$key];
		}

		$verify = md5($string);

		$url = add_query_arg(
			array(
				'_txnupdate' => strtolower($this->module),
				'verify' => $verify
			),
			shoppurl(false,'checkout',true));

		$_POST['settings'][$this->module]['silenturl'] = $url;
	}

	function verify ( $verify ) {
		$creds = array('username','vendor','partner','password');
		$string = '';
		foreach ( $creds as $key ) $string .= $this->settings[$key];
		return $verify === md5($string);
	}


}

?>