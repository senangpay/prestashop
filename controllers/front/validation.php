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

class SenangpayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'senangpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Senangpay.Shop'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $id_address = (int) $cart->id_address_delivery;
        if (($id_address == 0) && ($customer)) {
            $id_address = Address::getFirstCustomerAddressId($customer->id);
        }

        $address = new Address($id_address);

        $currency = $this->context->currency;
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        $config = Configuration::getMultiple(array('SENANGPAY_IS_STAGING', 'SENANGPAY_SECRET_KEY', 'SENANGPAY_MERCHANT_ID'));

        $parameter = array(
            'merchant_id' => trim($config['SENANGPAY_MERCHANT_ID']),
            'email' => trim($customer->email),
            'phone' => trim((empty($address->phone)) ? $address->phone_mobile : $address->phone),
            'name' => trim($customer->firstname . " " . $customer->lastname),
            'amount' => $total,
        );

        if (empty($parameter['phone']) && empty($parameter['email'])) {
            $parameter['email'] = 'noreply@senangpay.my';
        }

        if (empty($parameter['name'])) {
            $parameter['name'] = 'Payer Name Unavailable';
        }

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('SENANGPAY_OS_WAITING'),
            '0.0',
            $this->module->displayName,
            "",
            array(),
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $order_id = Order::getIdByCartId($cart->id);
        $order = new Order($order_id);
        $order->setInvoice();

        $parameter['order_id'] = $order_id;
        $parameter['detail'] = 'Payment_for_order_' . $order_id;

        $is_staging = $config['SENANGPAY_IS_STAGING'] == 'yes' ? true : false;
        $senangpay = new SenangpayApi(
            $config['SENANGPAY_MERCHANT_ID'],
            $config['SENANGPAY_SECRET_KEY'],
            $is_staging,
            $parameter
        );

        $payment_url = $senangpay->getPaymentUrl();
        Tools::redirect($payment_url);
    }
}
