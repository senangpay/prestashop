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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Senangpay extends \PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    protected $api_key;
    protected $collection_id;
    protected $x_signature;

    public function __construct()
    {
        $this->name = 'senangpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7.4.4', 'max' => '1.7');
        $this->author = 'Simplepay Gateway Sdn. Bhd.';
        $this->controllers = array('return', 'validation');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'radio';

        $config = Configuration::getMultiple(array('SENANGPAY_IS_STAGING', 'SENANGPAY_SECRET_KEY', 'SENANGPAY_MERCHANT_ID'));

        if (!empty($config['SENANGPAY_IS_STAGING'])) {
            $this->is_staging = $config['SENANGPAY_IS_STAGING'];
        }
        
        if (!empty($config['SENANGPAY_SECRET_KEY'])) {
            $this->secret_key = $config['SENANGPAY_SECRET_KEY'];
        }

        if (!empty($config['SENANGPAY_MERCHANT_ID'])) {
            $this->merchant_id = $config['SENANGPAY_MERCHANT_ID'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('senangPay', array(), 'Modules.Senangpay.Admin');
        $this->description = $this->trans('The easiest way to accept online payments', array(), 'Modules.Senangpay.Admin');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.Senangpay.Admin');

        if (!isset($this->is_staging) || !isset($this->secret_key) || !isset($this->merchant_id) || !isset($this->return_url)) {
            $this->warning = $this->trans('Merchant ID, Secret Key and Return URL must be configured before using this module.', array(), 'Modules.Senangpay.Admin');
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.Senangpay.Admin');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions') || !$this->installOrderState()) {
            return false;
        }
        return true;
    }

    public function installOrderState()
    {
        if (!Configuration::get('SENANGPAY_OS_WAITING') || !Validate::isLoadedObject(new OrderState(Configuration::get('SENANGPAY_OS_WAITING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting for senangPay payment';
            }

            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_ . 'senangpay/logo.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int) $order_state->id . '.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('SENANGPAY_OS_WAITING', (int) $order_state->id);
        }
        return true;
    }

    public function uninstall()
    {
        // Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'senangpay`;');
        
        if (!Configuration::deleteByName('SENANGPAY_IS_STAGING')
            || !Configuration::deleteByName('SENANGPAY_SECRET_KEY')
            || !Configuration::deleteByName('SENANGPAY_MERCHANT_ID')
            || !Configuration::deleteByName('SENANGPAY_OS_WAITING')
            || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('SENANGPAY_IS_STAGING')) {
                $this->_postErrors[] = $this->trans('senangPay environment mode is required.', array(), 'Modules.Senangpay.Admin');
            } elseif (!Tools::getValue('SENANGPAY_SECRET_KEY')) {
                $this->_postErrors[] = $this->trans('Secret Key is required.', array(), 'Modules.Senangpay.Admin');
            } elseif (!Tools::getValue('SENANGPAY_MERCHANT_ID')) {
                $this->_postErrors[] = $this->trans('Merchant ID is required.', array(), "Modules.Senangpay.Admin");
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('SENANGPAY_IS_STAGING', Tools::getValue('SENANGPAY_IS_STAGING'));
            Configuration::updateValue('SENANGPAY_SECRET_KEY', Tools::getValue('SENANGPAY_SECRET_KEY'));
            Configuration::updateValue('SENANGPAY_MERCHANT_ID', Tools::getValue('SENANGPAY_MERCHANT_ID'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->renderForm();
        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Pay via senangPay', array(), 'Modules.Senangpay.Shop'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
    }

    public function checkCurrency($cart)
    {
        if (!$this->currencies) {
            return false;
        }

        $currencies_module = Currency::getPaymentCurrencies($this->id);
        $currency_order = new Currency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('senangPay account details', array(), 'Modules.Senangpay.Admin'),
                    'icon' => 'icon-gear',
                ),
                'input' => array(
                    array(
                        'type' => 'radio',
                        'label' => $this->trans('Environment', array(), 'Modules.Senangpay.Admin'),
                        'name' => 'SENANGPAY_IS_STAGING',
                        'values' => array(
                            array(
                                'id' => 'production',
                                'label' => $this->l('Production'),
                                'value' => 'no'
                            ),
                            array(
                              'id' => 'sandbox',
                              'label' => $this->l('Sandbox'),
                              'value' => 'yes'
                            )
                        ),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Merchant ID', array(), 'Modules.Senangpay.Admin'),
                        'name' => 'SENANGPAY_MERCHANT_ID',
                        'desc' => $this->trans('senangPay Merchant ID.', array(), 'Modules.Senangpay.Admin'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Secret Key', array(), 'Modules.Senangpay.Admin'),
                        'name' => 'SENANGPAY_SECRET_KEY',
                        'desc' => $this->trans('senangPay Secret Key.', array(), 'Modules.Senangpay.Admin'),
                        'required' => true,
                    )
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
        . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'SENANGPAY_IS_STAGING' => Tools::getValue('SENANGPAY_IS_STAGING', Configuration::get('SENANGPAY_IS_STAGING')),
            'SENANGPAY_SECRET_KEY' => Tools::getValue('SENANGPAY_SECRET_KEY', Configuration::get('SENANGPAY_SECRET_KEY')),
            'SENANGPAY_MERCHANT_ID' => Tools::getValue('SENANGPAY_MERCHANT_ID', Configuration::get('SENANGPAY_MERCHANT_ID')),
        );
    }
}
