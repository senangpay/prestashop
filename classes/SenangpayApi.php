<?php
/**
 * 2007-2019 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2019 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class SenangpayApi
{
    private $endpoint_url;
    private $merchant_id;
    private $params;
    private $secret_key;

    public function __construct($merchant_id, $secret_key, $is_staging, $params)
    {
        $this->endpoint_url = $is_staging ? 'https://sandbox.senangpay.my/payment/' : 'https://app.senangpay.my/payment/'; 
        $this->merchant_id = $merchant_id;
        $this->params = $params;
        $this->secret_key = $secret_key;
    }

    public function getPaymentHash()
    {
        $params_string = $this->secret_key . urldecode($this->params['detail']) . urldecode($this->params['amount']) . urldecode($this->params['order_id']);
        return hash_hmac('sha256', $params_string, $this->secret_key);
    }

    public function getValidateHash()
    {
        return;
    }

    public function getPaymentUrl()
    {
        $this->params['hash'] = $this->getPaymentHash();

        unset($this->params['merchant_id']);
        $query_string = http_build_query($this->params);
        return $this->endpoint_url . $this->merchant_id . '?' . $query_string;
    }

    public static function getResponse($secret_key)
    {
        $data = array();

        if (isset($_GET['status_id'])
            && isset($_GET['order_id'])
            && isset($_GET['msg'])
            && isset($_GET['transaction_id'])
            && isset($_GET['hash'])
        ) {
            $keys = array('status_id', 'order_id', 'msg', 'transaction_id', 'hash');

            foreach ($keys as $key){
                if (isset($_GET[$key])){
                    $data[$key] = $_GET[$key];
                }
            } 
            $type = 'return';
        } elseif (isset($_POST['status_id'])
            && isset($_POST['order_id'])
            && isset($_POST['msg'])
            && isset($_POST['transaction_id'])
            && isset($_POST['hash'])
        ) {
            $keys = array('status_id', 'order_id', 'msg', 'transaction_id', 'hash');
            
            foreach ($keys as $key){
                if (isset($_POST[$key])){
                    $data[$key] = $_POST[$key];
                }
            } 
            $type = 'callback';
        } else {
            throw new \Exception('Response not valid.');
        }

        $data['paid'] = $data['status_id'] == 1 ? true : false;

        $params_string = $secret_key . urldecode($data['status_id']) . urldecode($data['order_id']) . urldecode($data['transaction_id']) . urldecode($data['msg']);
        $verification_hash = hash_hmac('sha256', $params_string, $secret_key);

        if ($data['hash'] === $verification_hash) {
            $data['type'] = $type;
            return $data;
        }

        throw new \Exception('Hashed value is not correct');
    }
}