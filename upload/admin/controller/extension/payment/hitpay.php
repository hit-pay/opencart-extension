<?php
require_once DIR_SYSTEM.'library/hitpay-php-sdk/Request.php';
require_once DIR_SYSTEM.'library/hitpay-php-sdk/Client.php';
require_once DIR_SYSTEM.'library/hitpay-php-sdk/Response/CreatePayment.php';
require_once DIR_SYSTEM.'library/hitpay-php-sdk/Response/PaymentStatus.php';
require_once DIR_SYSTEM.'library/hitpay-php-sdk/Response/Refund.php';

class ControllerExtensionPaymentHitPay extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/payment/hitpay');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_hitpay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['api_key'])) {
            $data['error_api_key'] = $this->error['api_key'];
        } else {
            $data['error_api_key'] = '';
        }

        if (isset($this->error['signature'])) {
            $data['error_signature'] = $this->error['signature'];
        } else {
            $data['error_signature'] = '';
        }

        if (isset($this->error['type'])) {
            $data['error_type'] = $this->error['type'];
        } else {
            $data['error_type'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/hitpay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/hitpay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_hitpay_api_key'])) {
            $data['payment_hitpay_api_key'] = $this->request->post['payment_hitpay_api_key'];
        } else {
            $data['payment_hitpay_api_key'] = $this->config->get('payment_hitpay_api_key');
        }

        if (isset($this->request->post['payment_hitpay_signature'])) {
            $data['payment_hitpay_signature'] = $this->request->post['payment_hitpay_signature'];
        } else {
            $data['payment_hitpay_signature'] = $this->config->get('payment_hitpay_signature');
        }

        if (isset($this->request->post['payment_hitpay_mode'])) {
            $data['payment_hitpay_mode'] = $this->request->post['payment_hitpay_mode'];
        } else {
            $data['payment_hitpay_mode'] = $this->config->get('payment_hitpay_mode');
        }

        if (isset($this->request->post['payment_hitpay_total'])) {
            $data['payment_hitpay_total'] = $this->request->post['payment_hitpay_total'];
        } else {
            $data['payment_hitpay_total'] = $this->config->get('payment_hitpay_total');
        }

        if (isset($this->request->post['payment_hitpay_order_status_id'])) {
            $data['payment_hitpay_order_status_id'] = $this->request->post['payment_hitpay_order_status_id'];
        } else {
            $data['payment_hitpay_order_status_id'] = $this->config->get('payment_hitpay_order_status_id');
        }

        if (isset($this->request->post['payment_hitpay_logging'])) {
            $data['payment_hitpay_logging'] = $this->request->post['payment_hitpay_logging'];
        } else {
            $data['payment_hitpay_logging'] = $this->config->get('payment_hitpay_logging');
        }
        if (isset($this->request->post['payment_hitpay_title'])) {
            $data['payment_hitpay_title'] = $this->request->post['payment_hitpay_title'];
        } else {
            $data['payment_hitpay_title'] = $this->config->get('payment_hitpay_title');
        }

        $data['payment_logos'] = $this->get_payment_logos();
        if (isset($this->request->post['payment_hitpay_logo'])) {
            $data['payment_hitpay_logo'] = $this->request->post['payment_hitpay_logo'];
        } else {
            $payment_hitpay_logo = $this->config->get('payment_hitpay_logo');
            if (empty($payment_hitpay_logo)) {
                $payment_hitpay_logo = [];
            }
            $data['payment_hitpay_logo'] = $payment_hitpay_logo;
        }

        $data['payment_methods'] = ['paynow_online' , 'card', 'wechat', 'alipay'];

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->getNewOrderStatuses($this->model_localisation_order_status->getOrderStatuses());

        if (isset($this->request->post['payment_hitpay_geo_zone_id'])) {
            $data['payment_hitpay_geo_zone_id'] = $this->request->post['payment_hitpay_geo_zone_id'];
        } else {
            $data['payment_hitpay_geo_zone_id'] = $this->config->get('payment_hitpay_geo_zone_id');
        }

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->request->post['payment_hitpay_status'])) {
            $data['payment_hitpay_status'] = $this->request->post['payment_hitpay_status'];
        } else {
            $data['payment_hitpay_status'] = $this->config->get('payment_hitpay_status');
        }

        if (isset($this->request->post['payment_hitpay_sort_order'])) {
            $data['payment_hitpay_sort_order'] = $this->request->post['payment_hitpay_sort_order'];
        } else {
            $data['payment_hitpay_sort_order'] = $this->config->get('payment_hitpay_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');


        $this->response->setOutput($this->load->view('extension/payment/hitpay', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/hitpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_hitpay_api_key']) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }

        if (!$this->request->post['payment_hitpay_signature']) {
            $this->error['signature'] = $this->language->get('error_signature');
        }

        return !$this->error;
    }
    
    public function get_payment_logos() {
        $list = array(
            array(
                'value' => 'visa',
                'label' => 'Visa'
            ),
            array(
                'value' => 'master',
                'label' => 'Mastercard'
            ),
            array(
                'value' => 'american_express',
                'label' => 'American Express'
            ),
            array(
                'value' => 'apple_pay',
                'label' => 'Apple Pay'
            ),
            array(
                'value' => 'google_pay',
                'label' => 'Google Pay'
            ),
            array(
                'value' => 'paynow',
                'label' => 'PayNow QR'
            ),
            array(
                'value' => 'grabpay',
                'label' => 'GrabPay'
            ),
            array(
                'value' => 'wechatpay',
                'label' => 'WeChatPay'
            ),
            array(
                'value' => 'alipay',
                'label' => 'AliPay'
            ),
            array(
                'value' => 'shopeepay',
                'label' => 'Shopee Pay'
            )
        );
        return $list;
    }
    
    public function getNewOrderStatuses($statuses) {
        $result = array();
        $skipStatuses = array(
            'Canceled',
            'Canceled Reversal',
            'Chargeback',
            'Denied',
            'Expired',
            'Failed',
            'Refunded',
            'Reversed',
            'Voided'
        );
        foreach ($statuses as $key => $status) {
            if (!in_array($status['name'], $skipStatuses)) {
                $result[] = $status;
            }
        }
        return $result;
    }
    
    public function install() {
        $this->load->model('extension/payment/hitpay');
        $this->model_extension_payment_hitpay->install();
    }

    public function uninstall() {
            $this->load->model('extension/payment/hitpay');
            $this->model_extension_payment_hitpay->uninstall();
    }
        
    public function order_info(&$route, &$data, &$output) {
        $order_id = $this->request->get['order_id'];
        $this->load->model('extension/payment/hitpay');
        $order = $this->model_extension_payment_hitpay->getOrder($order_id);

        if ($order) {
            $metaData = $order['response'];
            if (!empty($metaData)) {
                $metaData = json_decode($metaData, true);

                if(isset($metaData['payment_id']) && !empty($metaData['payment_id'])) {
                    $this->load->model('sale/order');
                    $order_info = $this->model_sale_order->getOrder($order_id);
                    $params = $metaData;
                    
                    /* The below block to add hitpay refund tab to the order page */
                    $tab['title'] = 'HitPay Refund';
                    $tab['code'] = 'hitpay_refund';
                    if(isset($metaData['is_refunded']) && $metaData['is_refunded'] == 1) {
                        $params['amount_refunded'] = $this->currency->format($metaData['refundData']['amount_refunded'], $order_info['currency_code'], $order_info['currency_value']);
                        $params['total_amount'] = $this->currency->format($metaData['refundData']['total_amount'], $order_info['currency_code'], $order_info['currency_value']);
                    } else {
                        $params['is_refunded'] = 0;
                        $params['amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);
                    }

                    $params['user_token'] = $this->session->data['user_token'];
                    $params['order_id'] = $order_id;

                    $content = $this->load->view('extension/payment/hitpay_refund', $params);

                    $tab['content'] = $content;
                    $data['tabs'][] = $tab;
                    
                    /* The below block to display hitpay payment details to order totals */
                    $payment_method = '';
                    $fees = '';
                    $payment_request_id = $metaData['payment_request_id'];
                    if (!empty($payment_request_id)) {
                        $payment_method = isset($metaData['payment_type']) ? $metaData['payment_type'] : '';
                        $fees = isset($metaData['fees']) ? $metaData['fees'] : '';
                        if (empty($payment_method) || empty($fees)) {
                            
                            try {
                                if ($this->config->get('payment_hitpay_mode') == 'live') {
                                    $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
                                } else {
                                    $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
                                }
 
                                $paymentStatus = $hitPayClient->getPaymentStatus($payment_request_id);
                                if ($paymentStatus) {
                                    $payments = $paymentStatus->payments;
                                    if (isset($payments[0])) {
                                        $payment = $payments[0];
                                        $payment_method = $payment->payment_type;
                                        $fees = $payment->fees;
                                        $this->model_extension_payment_hitpay->updatePaymentData($order_id, 'payment_type', $payment_method);
                                        $this->model_extension_payment_hitpay->updatePaymentData($order_id, 'fees', $fees);
                                    }
                                }
                            } catch (\Exception $e) {
                                $payment_method = $e->getMessage();
                            }
                        }
                        
                        if (!empty($payment_method)) {
                            $data['totals'][] = array('title' => 'HitPay Payment Type', 'text' => ucwords(str_replace("_", " ", $payment_method)));
                            $data['totals'][] = array('title' => 'HitPay Fee', 'text' => $this->currency->format($fees, $order_info['currency_code'], $order_info['currency_value']));
                        }
                    }
                }
            }
        }
    }

    public function refund() {
        $response = array();
        $status = 0;

        try {
            if (isset($this->request->post['order_id'])) {
                $order_id = $this->request->post['order_id'];
            } else {
                $order_id = 0;
            }

            if (isset($this->request->post['hitpay_amount'])) {
                $hitpay_amount = $this->request->post['hitpay_amount'];
            } else {
                $hitpay_amount = 0;
            }

            if (isset($this->request->post['payment_id'])) {
                $transaction_id = $this->request->post['payment_id'];
            } else {
                $transaction_id = 0;
            }

            $this->load->model('sale/order');
            $order_info = $this->model_sale_order->getOrder($order_id);

            $order_total_paid = $order_info['total'];
            $amount = $hitpay_amount;

            if ($amount <= 0) {
                throw new \Exception('Refund amount shoule be greater than 0');
            }

            if ($amount > $order_total_paid) {
                throw new \Exception('Refund amount shoule be less than or equal to order paid total ('.$order_total_paid.')');
            }

            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $result = $hitPayClient->refund($transaction_id, $amount);

            $this->load->model('extension/payment/hitpay');
            $this->model_extension_payment_hitpay->updatePaymentData($order_id, 'refundData', array(
                'order_id' => (int) $order_id,
                'refund_id' =>  $result->getId(),
                'payment_id' => $result->getPaymentId(),
                'status' => $result->getStatus(),
                'amount_refunded' => $result->getAmountRefunded(),
                'total_amount' => $result->getTotalAmount(),
                'currency' => $result->getCurrency(),
                'payment_method' => $result->getPaymentMethod(),
                'created_at' => $result->getCreatedAt()
            ));
            $order = $this->model_extension_payment_hitpay->updatePaymentData($order_id, 'is_refunded', 1);

            $message = 'Refund successful. Refund Reference Id: '.$result->getId().', '
                    . 'Payment Id: '.$transaction_id.', Amount Refunded: '.$result->getAmountRefunded().', '
                    . 'Payment Method: '.$result->getPaymentMethod().', Created At: '.$result->getCreatedAt();

            $total_refunded = $result->getAmountRefunded();
            if ($total_refunded >= $order_total_paid) {
                //$message .= ' Order status changed, please reload the page';
            }
            $status = 1;
        } catch (\Exception $e) {
            $message = 'Refund Payment Failed: '.$e->getMessage();
        }

        $response['status'] = $status;
        $response['message'] = $message;

        echo json_encode($response);
        exit;
    }
}