<?php
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
        /*
        if (isset($this->request->post['payment_hitpay_payment_method'])) {
            $data['payment_hitpay_payment_method'] = $this->request->post['payment_hitpay_payment_method'];
        } else {
            $data['payment_hitpay_payment_method'] = $this->config->get('payment_hitpay_payment_method');
        }*/

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

        $data['payment_methods'] = ['paynow_online' , 'card', 'wechat', 'alipay'];

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

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
}