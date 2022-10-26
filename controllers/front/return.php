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

require_once _PS_MODULE_DIR_ . 'senangpay/classes/SenangpayApi.php';

class SenangpayReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $secret_key = trim(Configuration::get('SENANGPAY_SECRET_KEY'));
        try {
            $data = SenangpayApi::getResponse($secret_key);
        } catch (Exception $e) {
            header('HTTP/1.1 403 Response not valid.', true, 403);
            exit();
        }

        $cart_id = $data['order_id'];
        $cart = new Cart($cart_id);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php');
        }

        if ($data['type'] === 'return') {
            if (!$data['paid']) {
                if (Tools::version_compare(_PS_VERSION_, '1.7.1.0', '>')) {
                    $order = Order::getByCartId($cart_id);
                } else {
                    $order_id = Order::getOrderByCartId($cart_id);
                    $order = new Order($order_id);
                }
                if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT')) {

                    $new_history = new OrderHistory();
                    $new_history->id_order = Order::getIdByCartId($cart_id);
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $order, true);
                    $new_history->addWithemail(true);

                    $payment = $order->getOrderPaymentCollection();
                    if (isset($payment[0]))
                    {
                        $payment[0]->transaction_id = $data['transaction_id'];
                        $payment[0]->save();
                    }
                }
                die('<p>Your payment was failed. Please go back to the store and try again. Go back to <a href="' . $this->context->link->getPageLink('index',true) . '">' . $this->context->link->getPageLink('index',true) . '</a></p>');
            } else {
                if (Tools::version_compare(_PS_VERSION_, '1.7.1.0', '>')) {
                    $order = Order::getByCartId($cart_id);
                } else {
                    $order_id = Order::getOrderByCartId($cart_id);
                    $order = new Order($order_id);
                }
                if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT')) {

                    $new_history = new OrderHistory();
                    $new_history->id_order = Order::getIdByCartId($cart_id);
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $order, true);
                    $new_history->addWithemail(true);

                    $payment = $order->getOrderPaymentCollection();
                    if (isset($payment[0]))
                    {
                        $payment[0]->transaction_id = $data['transaction_id'];
                        $payment[0]->save();
                    }
                }
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $cart_id . '&key=' . $customer->secure_key);
            }
        } else {
            if ($data['paid']) {
                if (Tools::version_compare(_PS_VERSION_, '1.7.1.0', '>')) {
                    $order = Order::getByCartId($cart_id);
                } else {
                    $order_id = Order::getOrderByCartId($cart_id);
                    $order = new Order($order_id);
                }
                if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT')) {

                    $new_history = new OrderHistory();
                    $new_history->id_order = Order::getIdByCartId($cart_id);
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $order, true);
                    $new_history->addWithemail(true);

                    $payment = $order->getOrderPaymentCollection();
                    if (isset($payment[0]))
                    {
                        $payment[0]->transaction_id = $data['transaction_id'];
                        $payment[0]->save();
                    }
                }
            } else {
                if (Tools::version_compare(_PS_VERSION_, '1.7.1.0', '>')) {
                    $order = Order::getByCartId($cart_id);
                } else {
                    $order_id = Order::getOrderByCartId($cart_id);
                    $order = new Order($order_id);
                }
                if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT')) {

                    $new_history = new OrderHistory();
                    $new_history->id_order = Order::getIdByCartId($cart_id);
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $order, true);
                    $new_history->addWithemail(true);

                    $payment = $order->getOrderPaymentCollection();
                    if (isset($payment[0]))
                    {
                        $payment[0]->transaction_id = $data['transaction_id'];
                        $payment[0]->save();
                    }
                }
            }
            echo 'OK';
            exit;
        }
    }
}
