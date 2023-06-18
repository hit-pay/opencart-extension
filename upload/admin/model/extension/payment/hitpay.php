<?php

class ModelExtensionPaymentHitpay extends Model {
        private $version = '1.2.1';
	public function install() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "hitpay_order` (
			  `order_id` int(11) NOT NULL,
			  `response` TEXT
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
                $this->load->model('setting/event');
                $this->model_setting_event->addEvent('payment_hitpay', 'admin/view/sale/order_info/before', 'extension/payment/hitpay/order_info');
                $this->model_setting_event->addEvent('payment_hitpay', 'catalog/view/common/success/after', 'extension/payment/hitpay/after_purchase');
                $this->model_setting_event->addEvent('payment_hitpay', 'catalog/controller/checkout/success/before', 'extension/payment/hitpay/before_checkout_success');
                $this->editSettingValue('payment_hitpay_current_version', $this->version);
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "hitpay_order`;");
                $this->load->model('setting/event');
                $this->model_setting_event->deleteEventByCode('payment_hitpay');
	}
        
        public function upgrade_120_121() {
                $this->model_setting_event->addEvent('payment_hitpay', 'catalog/view/common/success/after', 'extension/payment/hitpay/after_purchase');
                $this->model_setting_event->addEvent('payment_hitpay', 'catalog/controller/checkout/success/before', 'extension/payment/hitpay/before_checkout_success');
                $this->editSettingValue('payment_hitpay_current_version', $this->version);
	}

	public function getOrder($order_id) {
		$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "hitpay_order` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");

		if ($qry->num_rows) {
			$order = $qry->row;
			return $order;
		} else {
			return false;
		}
	}
        
        public function getPaymentData($order_id)
        {
            $qry = $this->db->query('select response FROM ' . DB_PREFIX.'hitpay_order WHERE order_id='.(int)($order_id));
            if ($qry->num_rows) {
                    $row = $qry->row;
                    return $row['response'];
            } else {
                    return false;
            }
        }

        public function addPaymentData($order_id, $response)
        {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "hitpay_order` SET `order_id` = '" . (int)$order_id . "',  `response` = '" . $this->db->escape($response) . "'");
        }

        public function updatePaymentData($order_id, $param, $value)
        {
            $metaData = $this->getPaymentData($order_id);
            if (!empty($metaData)) {
                $metaData = json_decode($metaData, true);
                $metaData[$param] = $value;
                $paymentData = json_encode($metaData);
                $this->db->query("UPDATE " . DB_PREFIX . "hitpay_order SET response = '" . $this->db->escape($paymentData) . "' WHERE order_id = '" . (int)$order_id . "'");
            }
        }

        public function deletePaymentData($order_id, $param)
        {
            $metaData = $this->getPaymentData($order_id);
            if (!empty($metaData)) {
                $metaData = json_decode($metaData, true);
                if (isset($metaData[$param])) {
                    unset($metaData[$param]);
                }
                $paymentData = json_encode($metaData);

                $this->db->query("UPDATE " . DB_PREFIX . "hitpay_order SET response = '" . $this->db->escape($paymentData) . "' WHERE order_id = '" . (int)$order_id . "'");
            }
        }
        
        public function getVersion()
        {
            return $this->version;
        }
        
        public function getSettingValue($key, $store_id = 0)
        {
            $query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `key` = '" . $this->db->escape($key) . "'");

            if ($query->num_rows) {
                    return $query->row['value'];
            } else {
                    return null;	
            }
	}
	
	public function editSettingValue($key = '', $value = '', $store_id = 0)
        {
            $exist = $this->getSettingValue($key);
            if ($exist) {
                    $this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "' WHERE `key` = '" . $this->db->escape($key) . "' AND store_id = '" . (int)$store_id . "'");
            } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = 'payment_hitpay_outside', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
            }
	}
}