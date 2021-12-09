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

        if ($this->request->get['status'] == 'completed') {
            $order_id = (int)($this->session->data['order_id']);
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id  = '" . (int)$this->config->get('payment_hitpay_order_status_id') . "' WHERE order_id = '" . (int)$order_id . "'");
            $this->response->redirect($this->url->link('checkout/success', '', true));
        } else {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
        }
	}

	public function webhook() {

        if ($this->config->get('payment_hitpay_logging')) {
            $logger = new log('hitpay.log');
            $logger->write('webhook post');
            $logger->write($this->request->post);
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
                    $this->load->model('extension/payment/hitpay');
                    $order_id = (int)$this->request->post['reference_number'];
                    if ($order_id > 0) {
                        $metaData = $this->model_extension_payment_hitpay->getPaymentData($order_id);
                        if (empty($metaData) || !$metaData) {
                            $paymentData = $this->request->post;
                            $paymentData = json_encode($paymentData);
                            $this->model_extension_payment_hitpay->addPaymentData($order_id, $paymentData);
                        }
                    }
                    
                    $this->load->model('checkout/order');
                    $this->model_checkout_order->addOrderHistory((int)$this->request->post['reference_number'], $this->config->get('payment_hitpay_order_status_id'));
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
                   // ->setPhone($order_info['telephone'])
                    ->setRedirectUrl($this->url->link('extension/payment/hitpay/callback', '', true))
                    ->setWebhook($this->url->link('extension/payment/hitpay/webhook', '', true))
                   // ->setAllowRepeatedPayments();
                   // ->setExpiryDate('false')
                   // ->setPaymentMethods($payment_methods)
                    ;
                $request->setChannel('api_opencart');

                $result = $hitPayClient->createPayment($request);
                header('Location: ' . $result->url);

            } catch (\Exception $e) {
                print_r($e->getMessage());
            }
        }
	}
}