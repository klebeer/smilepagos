<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use smile\payment\model\Amount;
use smile\payment\model\Constants;
use smile\payment\model\CustomerInfo;
use smile\payment\model\DataFastRequest;
use smile\payment\model\ProductInfo;
use smile\payment\PaymentService;
use smile\payment\smile\payment\Config;
use smile\payment\smile\payment\model\CardBrands;
use smile\payment\smile\payment\model\Payment;

;

include_once "src/classes/smile/payment/model/Constants.php";
include_once "src/classes/smile/payment/model/Environment.php";
include_once "src/classes/smile/payment/model/Amount.php";
include_once "src/classes/smile/payment/model/CardBrands.php";
include_once "src/classes/smile/payment/model/Payment.php";
include_once "src/classes/smile/payment/model/ProductInfo.php";
include_once "src/classes/smile/payment/model/CustomerInfo.php";
include_once "src/classes/smile/payment/model/DataFastRequest.php";
include_once "src/classes/smile/payment/PaymentService.php";
include_once "src/classes/smile/payment/SmileDB.php";
include_once "src/classes/smile/payment/Config.php";
include_once(_PS_MODULE_DIR_ . 'smilepagos/vendor/autoload.php');

if (!defined('_PS_VERSION_')) {
    exit;
}


class smilepagos extends PaymentModule
{

    private $entityId;
    private $bearerToken;
    private $mid;
    private $tid;
    private $risk;


    public function __construct()
    {
        $this->name = 'smilepagos';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Kleber Ayala';
        $this->need_instance = 0;
        $this->is_configurable = 1;

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);


        parent::__construct();

        $this->displayName = $this->l('Smile Pagos con Datafast');
        $this->description = $this->l('Módulo de pagos de Smile para DataFast');
        $this->confirmUninstall = $this->l('Está seguro que desea desinstalar el modulo de pagos de de Smile para DataFast?');


        $this->bootstrap = true;
        $this->checkIfConfigurationIsProvided();
        $this->checkForCurrency();
        $this->checkForLogsFolder();

    }

    public function checkIfConfigurationIsProvided(): void
    {
        if (!isset($this->entityId)
            || !isset($this->bearerToken)
            || !isset($this->mid)
            || !isset($this->tid)
            || !isset($this->risk)
            || empty($this->entityId)
            || empty($this->bearerToken)
            || empty($this->mid)
            || empty($this->tid)
            || empty($this->risk)
        ) {
            $this->warning = 'Toda la información debe ser configurada antes de utilizar el módulo.';
            $this->status_module = false;
        }
    }

    public function checkForCurrency(): void
    {
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function checkForLogsFolder(): void
    {
        $logFolder = Constants::LOGGER_FOLDER;
        if (!file_exists($logFolder)) {
            mkdir($logFolder, 0777, true);
        }
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('DATA_FAST_LIVE_MODE', false);
        Configuration::updateValue('DATA_FAST_DEV', true);

        PrestaShopLogger::addLog('Instalación de módulo de pagos Smile DataFast Payment', 2);


        if (!$this->createDatabase()) {
            $this->_errors[] = $this->l('No se pudo crear la base de datos de smile pagos');
            return false;
        }

        if (parent::install()
            && $this->registerHook('payment')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayHeader')) {

            return true;
        } else {
            $this->_errors[] = $this->l('Could not register payments hooks on Smile DataFast Payment ');
            return false;
        }


        return true;
    }

    public function createDatabase()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'smilepagos (
            `id` INTEGER(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `cart_id` INTEGER(11) DEFAULT NULL,
            `customer_id` INTEGER(11) DEFAULT NULL,
            `total` FLOAT(11) DEFAULT NULL,
            `message` VARCHAR (100) ,
            `status` VARCHAR (100),
            `authorization` VARCHAR (6) DEFAULT NULL,
            `transaction_id` VARCHAR (32) DEFAULT NULL,
            `batch` VARCHAR (6) DEFAULT NULL,
            `reference_number` VARCHAR (6) DEFAULT NULL,
            `response_code` VARCHAR (2) DEFAULT NULL,
            `acquirer_code` VARCHAR (4) DEFAULT NULL,
            `customer_params` VARCHAR (85) DEFAULT NULL,
            `updated_at` DATETIME DEFAULT NULL)
            ENGINE = ' . _MYSQL_ENGINE_ . ' '
        );
    }

    public function uninstall()
    {
        Configuration::deleteByName('DATA_FAST_LIVE_MODE');
        Configuration::deleteByName('DATA_FAST_DEV');
        Configuration::deleteByName('DATA_FAST_ENTITY_ID');
        Configuration::deleteByName('DATA_FAST_BEARER_TOKEN');
        Configuration::deleteByName('DATA_FAST_MID');
        Configuration::deleteByName('DATA_FAST_TID');
        Configuration::deleteByName('DATA_FAST_RISK');


        PrestaShopLogger::addLog('Uninstalling Smile DataFast Payment Module', 2);

        return parent::uninstall();
    }

    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitDataFastPaymentModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('web_url', $this->context->link->getModuleLink($this->name, 'status', array(), true));
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {

        $logger = $this->getLogger();

        $logger->info('Smile DataFast Configuration changed!');

        PrestaShopLogger::addLog('Configuration parameters in Smile DataFast payments changed', 2);
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * @return Logger
     */
    private function getLogger(): Logger
    {
        $logger = new Logger('Configuration');
        $logger->pushHandler(new StreamHandler(Constants::LOGGER_FILE, Logger::DEBUG));
        return $logger;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DATA_FAST_LIVE_MODE' => Configuration::get('DATA_FAST_LIVE_MODE', true),
            'DATA_FAST_DEV' => Configuration::get('DATA_FAST_DEV', true),
            'DATA_FAST_ENTITY_ID' => Configuration::get('DATA_FAST_ENTITY_ID', null),
            'DATA_FAST_BEARER_TOKEN' => Configuration::get('DATA_FAST_BEARER_TOKEN', null),
            'DATA_FAST_MID' => Configuration::get('DATA_FAST_MID', null),
            'DATA_FAST_TID' => Configuration::get('DATA_FAST_TID', null),
            'DATA_FAST_RISK' => Configuration::get('DATA_FAST_RISK', null),

        );
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDataFastPaymentModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        if (Currency::getDefaultCurrency()->iso_code == 'USD') {
            return $helper->generateForm(array($this->getConfigForm()));
        } else {
            exit;
        }

    }

    /**
     * Create the structure of your form USD.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Ambiente de Pruebas'),
                        'name' => 'DATA_FAST_DEV',
                        'is_bool' => true,
                        'desc' => $this->l('Usar el módulo en ambiente de pruebas'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Ingrese el valor de Entity ID'),
                        'name' => 'DATA_FAST_ENTITY_ID',
                        'label' => $this->l('Entity ID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Ingrese el valor de Authorization Bearer'),
                        'name' => 'DATA_FAST_BEARER_TOKEN',
                        'label' => $this->l('Authorization'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Ingrese el valor de MID'),
                        'name' => 'DATA_FAST_MID',
                        'label' => $this->l('MID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Ingrese el valor de TID'),
                        'name' => 'DATA_FAST_TID',
                        'label' => $this->l('TID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Ingrese el valor de RISK'),
                        'name' => 'DATA_FAST_RISK',
                        'label' => $this->l('RISK'),
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }

        $order = $params['order'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {


            $dataFastBrand = Context::getContext()->cookie->dataFastBrand;
            $dataFastAmount = Context::getContext()->cookie->dataFastAmount;
            $dataFastAuth = Context::getContext()->cookie->dataFastAuth;
            $dataFastCardHolder = Context::getContext()->cookie->dataFastCardHolder;

            $status_map = array(
                $this->getConfig('PS_OS_PAYMENT') => 'ok',
                $this->getConfig('PS_OS_OUTOFSTOCK') => 'ok',
                $this->getConfig('PS_OS_OUTOFSTOCK_PAID') => 'ok',
                $this->getConfig('PS_OS_CANCELED') => 'cancel',
            );

            $status = isset($status_map[$order->getCurrentState()]) ? $status_map[$order->getCurrentState()] : 'error';


            $this->context->smarty->assign(array(
                'this_path' => $this->getPath(),
                'status' => $status,
                'shop_name' => $this->context->shop->name,
                'dataFastBrand' => CardBrands::getBrand($dataFastBrand),
                'dataFastAmount' => $dataFastAmount,
                'dataFastAuth' => $dataFastAuth,
                'dataFastCardHolder' => $dataFastCardHolder
            ));

        }

        return $this->fetch('module:smilepagos/views/templates/hook/confirmation.tpl');
    }

    private function getConfig($key)
    {
        return Configuration::get($key);
    }

    private function getPath()
    {
        return 'modules/smilepagos';
    }

    public function hookPaymentOptions($params)
    {
        if ($this->context->customer->isLogged()) {

            if (!$this->active) {
                return;
            }

            $payment = new Payment();

            $request = $this->getDataFastRequest();
            $productInfo[] = $this->getProductInfo();
            $customerInfo = $this->getCustomerInfo();
            $amount = $this->getAmount();


            $payment->setProductInfo($productInfo);
            $payment->setCustomerInfo($customerInfo);
            $payment->setAmount($amount);
            $payment->setRequest($request);


            $paymentService = new PaymentService();

            $checkOutId = $paymentService->requestCheckoutId($payment);

            $checkScript = $request->getCheckoutScript() . $checkOutId;

            $action = $this->context->link->getModuleLink($this->name, 'result', array('smileId' => $checkOutId), true);

            $this->smarty->assign('action', $action);
            $this->smarty->assign('checkScript', $checkScript);
            $this->smarty->assign('checkOutId', $checkOutId);


            $setAdditionalInformation = $this->fetch('module:smilepagos/views/templates/hook/smilePayment.tpl');

            $newOption = new PaymentOption();
            $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans('Pago con Datafast', array(), 'Pago con Datafast'))
                ->setAction($action)
                ->setAdditionalInformation($setAdditionalInformation);
            return [$newOption];

        } else {
            return;
        }

    }

    /**
     * @return DataFastRequest
     */
    protected function getDataFastRequest(): DataFastRequest
    {
        $config = new Config();
        return $config->getDataFastRequest();
    }

    private function getProductInfo(): array
    {
        $products = $this->context->cart->getProducts();

        $productList = [];
        foreach ($products as $key => $product) {
            $productInfo = new ProductInfo($product['name'], $product['description_short'], $product['price_wt'], $product['quantity']);
            array_push($productList, $productInfo);
        }
        return $productList;
    }

    /**
     * @return CustomerInfo
     */
    private function getCustomerInfo(): CustomerInfo
    {
        $cart = $this->context->cart;
        $transactionId = $cart->id;
        $customerId = $this->context->customer->id;
        $firstName = $this->context->customer->firstname;
        $lastName = $this->context->customer->lastname;
        $email = $this->context->customer->email;

        $address = new Address((int)$cart->id_address_delivery);

        $dni = $address->vat_number;
        $mobile = $address->phone_mobile;
        $customerIp = $_SERVER['REMOTE_ADDR'];
        $shippingAddress = $address->address1;

        return new CustomerInfo($firstName, "", $lastName, $customerIp, $customerId, $transactionId, $email, $dni, $mobile, $shippingAddress, "EC");
    }

    /**
     * @return Amount
     */
    protected function getAmount(): Amount
    {
        $cart = $this->context->cart;
        $amount = new Amount();
        $totalOrder = $cart->getOrderTotal(true, Cart::BOTH);
        $amount->setTotal($totalOrder);

        $subtotalIVA = 0.0;
        $subtotalIVA0 = 0.0;


        foreach ($cart->getProducts() as $product) {
            if ($product['rate'] > 0) {
                $subtotalIVA += $product['total'];
                $amount->setIvaRate($product['rate']);
            } else {
                $subtotalIVA0 += $product['total'];
            }
        }
        $amount->setSubtotalIVA($subtotalIVA);
        $amount->setSubtotalIVA0($subtotalIVA0);

        return $amount;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookDisplayHeader($params)
    {

    }

    public function buildConfigInfo(): void
    {
        $config = Configuration::getMultiple(array('DATA_FAST_ENTITY_ID', 'DATA_FAST_BEARER_TOKEN', 'DATA_FAST_MID', 'DATA_FAST_TID', 'DATA_FAST_RISK'));

        if (isset($config['DATA_FAST_ENTITY_ID'])) {
            $this->entityId = $config['DATA_FAST_ENTITY_ID'];
        }

        if (isset($config['DATA_FAST_BEARER_TOKEN'])) {
            $this->bearerToken = $config['DATA_FAST_BEARER_TOKEN'];
        }

        if (isset($config['DATA_FAST_MID'])) {
            $this->mid = $config['DATA_FAST_MID'];
        }

        if (isset($config['DATA_FAST_TID'])) {
            $this->tid = $config['DATA_FAST_TID'];
        }


        if (isset($config['DATA_FAST_RISK'])) {
            $this->risk = $config['DATA_FAST_RISK'];
        }
    }
}