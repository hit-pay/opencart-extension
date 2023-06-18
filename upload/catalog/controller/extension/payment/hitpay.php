<?php
// init hitpay
require_once './system/library/hitpay-php-sdk/Request/CreatePayment.php';
require_once './system/library/hitpay-php-sdk/Request.php';
require_once './system/library/hitpay-php-sdk/Response/CreatePayment.php';
require_once './system/library/hitpay-php-sdk/Response/PaymentStatus.php';
require_once './system/library/hitpay-php-sdk/Response/DeletePaymentRequest.php';
require_once './system/library/hitpay-php-sdk/Client.php';

class ControllerExtensionPaymentHitPay extends Controller {
	public function index() {

		$data['button_confirm'] = $this->language->get('button_confirm');

		$data['action'] = $this->url->link('extension/payment/hitpay/send', '', true);

		return $this->load->view('extension/payment/hitpay', $data);
	}

	public function callback() {
            if ($this->config->get('payment_hitpay_logging')) {
                $logger = new log('hitpay.log');
                $logger->write('callback get');
                $logger->write($this->request->get);
            }

            if ($this->request->get['status'] == 'canceled') {
                $this->response->redirect($this->url->link('checkout/failure', '', true));
            } else {
                $order_id = (int)($this->session->data['order_id']);
                if ($order_id == 0) {
                    $order_id = (int)$this->request->get['order_id'];
                }
                $this->response->redirect($this->url->link('checkout/success', 'order_id=' . $order_id, true));
            }
	}

	public function webhook() {

            if ($this->config->get('payment_hitpay_logging')) {
                $logger = new log('hitpay.log');
                $logger->write('webhook post');
                $logger->write($this->request->post);
            }
            
            $this->load->model('extension/payment/hitpay');
            $order_id = (int)$this->request->post['reference_number'];
            if ($order_id > 0) {
                $metaData = $this->model_extension_payment_hitpay->getPaymentData($order_id);
                if (!empty($metaData)) {
                    $metaData = json_decode($metaData, true);
                    if (isset($metaData['is_webhook_triggered']) && ($metaData['is_webhook_triggered'] == 1)) {
                        exit;
                    }
                }
            }

            $request = [];
            foreach ($this->request->post as $key=>$value) {
                if ($key != 'hmac'){
                    $request[$key] = $value;
                }
            }

            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $hmac = $hitPayClient::generateSignatureArray($this->config->get('payment_hitpay_signature'), (array)$request);

            if ($hmac == $this->request->post['hmac']) {
                if ($order_id > 0) {
                    $metaData = $this->model_extension_payment_hitpay->getPaymentData($order_id);
                    if (empty($metaData) || !$metaData) {
                        $paymentData = $this->request->post;
                        $paymentData = json_encode($paymentData);
                        $this->model_extension_payment_hitpay->addPaymentData($order_id, $paymentData);
                    }
                }

                $this->load->model('checkout/order');
                $this->model_checkout_order->addOrderHistory((int)$this->request->post['reference_number'], $this->config->get('payment_hitpay_order_status_id'), '', false);
                $this->model_extension_payment_hitpay->updatePaymentData($order_id, 'is_webhook_triggered', 1);
            }
	}

	public function send() {
            
            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $this->load->model('checkout/order');
            $this->load->model('extension/payment/hitpay');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            if ($order_info) {

                try {
                    
                    $redirect_url = $this->url->link('extension/payment/hitpay/callback', 'order_id=' . $this->session->data['order_id'], true);
                    
                    $payment_method = $this->config->get('payment_hitpay_title');
                    $this->model_extension_payment_hitpay->updateOrderData($order_info['order_id'], 'payment_method', $payment_method);

                    $request = new \HitPay\Request\CreatePayment();

                    $request
                        ->setAmount((float)$this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false))
                        ->setCurrency(strtoupper($order_info['currency_code']))
                        ->setEmail($order_info['email'])
                        ->setPurpose('Order #' . $order_info['order_id'])
                        ->setName(trim($order_info['firstname']) . ' ' . trim($order_info['lastname']))
                        ->setReferenceNumber($order_info['order_id'])
                        ->setRedirectUrl($redirect_url)
                        ->setWebhook($this->url->link('extension/payment/hitpay/webhook', '', true))
                        ->setChannel('api_opencart')
                        ;
                    $request->setChannel('api_opencart');
                    
                    if ($this->config->get('payment_hitpay_logging')) {
                        $logger = new log('hitpay.log');
                        $logger->write('create payment request');
                        $logger->write((array)($request));
                    }

                    $result = $hitPayClient->createPayment($request);
                    header('Location: ' . $result->url);

                } catch (\Exception $e) {
                    print_r($e->getMessage());
                }
            }
        }
        
        public function isHitpayOrder($status = false)
        {
            $order = false;
            if (isset($this->request->get['order_id'])) {
                $order_id = (int)($this->request->get['order_id']);
            } else if (isset($this->request->get['amp;order_id'])) {
                $order_id = (int)($this->request->get['amp;order_id']);
            }
            
            if ($order_id > 0) {
                $this->load->model('checkout/order');
                $order = $this->model_checkout_order->getOrder($order_id);
                
                $this->load->model('setting/setting');
                $setting = $this->model_setting_setting;
                
                if ($order && $order['payment_code'] != 'hitpay') {
                    $order = false;
                }
            }
            return $order;
        }
        
        public function getOrderId()
        {
            $order_id = false;
            if (isset($this->request->get['order_id'])) {
                $order_id = (int)($this->request->get['order_id']);
            } else if (isset($this->request->get['amp;order_id'])) {
                $order_id = (int)($this->request->get['amp;order_id']);
            }
            return $order_id;
        }
        
        public function before_checkout_success(&$route, &$data)
        {
            $this->load->model('setting/setting');
            $setting = $this->model_setting_setting;
            
             // In case the extension is disabled, do nothing
            if (!$setting->getSettingValue('payment_hitpay_status')) {
                return;
            }
            
            $order = $this->isHitpayOrder();
            
            if (!$order) {
                return;
            }
            
            $this->document->addScript('catalog/view/javascript/hitpay/js/payment.js');
            $this->document->addStyle('catalog/view/javascript/hitpay/css/loader.css');
        }

        public function after_purchase(&$route, &$data, &$output)
        {
            $this->load->model('setting/setting');
            $setting = $this->model_setting_setting;
            
             // In case the extension is disabled, do nothing
            if (!$setting->getSettingValue('payment_hitpay_status')) {
                return;
            }
            
            $order = $this->isHitpayOrder();
            
            if (!$order) {
                return;
            }

            $this->load->model('extension/payment/hitpay');

            $ajaxUrl = $this->url->link('extension/payment/hitpay/status', 'order_id=' . $order['order_id'], true);
            $ajaxUrl = str_replace('&amp;', '&', $ajaxUrl);
  
            $params = [
                'image_path' => HTTPS_SERVER .'catalog/view/theme/default/image/payment/hitpay/',
                'ajax_url' => $ajaxUrl

            ];

            $content = $this->load->view('extension/payment/hitpay_success', $params);
            $output = str_replace('<div class="buttons">', $content . '<div class="buttons">', $output);
        }

        public function status()
        {
            $status = 'wait';
            $message = '';

            try {
                $order_id = $this->getOrderId();

                if (empty($order_id) || $order_id == 0) {
                    throw new \Exception('Empty order id received.');
                }
                
                $this->load->model('extension/payment/hitpay');
                
                $metaData = $this->model_extension_payment_hitpay->getPaymentData($order_id);
                if ($metaData && !empty($metaData)) {
                    $metaData = json_decode($metaData);
                    $status = $metaData->status;
                }
            } catch (\Exception $e) {
                $status = 'error';
                $message = $e->getMessage();
            }

            $data = [
                'status' => $status,
                'message' => $message
            ];

            echo json_encode($data);
            die();
        }
}