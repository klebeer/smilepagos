<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use smile\payment\model\Constants;
use smile\payment\PaymentService;
use smile\payment\smile\payment\Config;
use smile\payment\smile\payment\model\Message;
use smile\payment\smile\payment\model\PaymentResponse;

include_once(_PS_MODULE_DIR_ . 'smilepagos/vendor/autoload.php');

class SmilepagosResultModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {
        if ($this->context->customer->isLogged()) {


            if (!$this->isPaymentMethodValid()) {
                die($this->module->l('This payment method is not available.', 'smilepagos'));
            }

            $cart = $this->context->cart;
            if (!$cart || !$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice) {
                $this->redirectTo('order', array('step' => 1));
            }

            $customer = new Customer($cart->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                $this->redirectTo('order', array('step' => 1));
            }

            $secureKey = $customer->secure_key;

            $paymentService = new PaymentService();

            $config = new Config();
            $request = $config->getDataFastRequest();

            $checkOutId = Tools::getValue('id');
            $resourcePathUri = str_replace("{id}", $checkOutId, $request->getResourcePathUri());

            //$this->getLogger()->debug("resourcePathUri--->" . $resourcePathUri);
            $request->setResourcePathUri($resourcePathUri);
            $paymentResponse = $paymentService->processPayment($request);
            $resultCode = $paymentResponse->getResult()->getCode();
            $accepted = $this->validateTransaction($resultCode);


            $paymentResponse->setCustomerId($customer->id);
            $paymentResponse->setCartId($cart->id);
            $paymentResponse->setAccepted($accepted);

            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $module_name = $this->module->displayName;

            $this->addTransaction($paymentResponse);


            if ($accepted) {
                $payment_status = Configuration::get('PS_OS_PAYMENT');
                $message = $this->l('El pago se registró correctamente');
                $this->module->validateOrder((int)$cart->id, $payment_status, $total, $module_name, $message, array(), (int)$cart->id_currency, false, $secureKey);

                $authCode = $paymentResponse->getResultDetails()->getAuthCode();
                $card = $paymentResponse->getCard();
                $dataFastCardHolder = $card->getHolder();

                Context::getContext()->cookie->dataFastBrand = $paymentResponse->getPaymentBrand();
                Context::getContext()->cookie->dataFastAmount = $paymentResponse->getAmount();
                Context::getContext()->cookie->dataFastAuth = $authCode;
                Context::getContext()->cookie->dataFastCardHolder = $dataFastCardHolder;

                $this->redirectTo('order-confirmation', array(
                    'id_cart' => (int)$cart->id,
                    'id_module' => (int)$this->module->id,
                    'id_order' => (int)$this->module->currentOrder,
                    'key' => $customer->secure_key
                ));
            } else {
                $errorMessage = $this->getDetailErrorMessage($resultCode);
                $logInfo = "Error en el pago  de:  " . $total . ", customer id: " . $customer->id . " cart id: " . $cart->id . " order id" . $this->module->currentOrder . " Detalle: " . $errorMessage;
                $this->getLogger()->info($logInfo);

                Context::getContext()->cookie->errorMessage = $errorMessage;

                Tools::redirect(Context::getContext()->link->getModuleLink('smilepagos', 'error', array()));
            }

        }
    }

    private function isPaymentMethodValid()
    {
        if (!$this->module->active) {
            return false;
        }

        if (method_exists('Module', 'getPaymentModules')) {
            foreach (Module::getPaymentModules() as $module) {
                if (isset($module['name']) && $module['name'] === $this->module->name) {
                    return true;
                }
            }
        } else {
            return true;
        }

        return false;
    }

    private function redirectTo($controller, array $params = array())
    {
        $query_string = !empty($params) ? http_build_query($params) : '';

        Tools::redirect('index.php?controller=' . $controller . '&' . $query_string);

    }

    /**
     * @return Logger
     */
    private function getLogger(): Logger
    {
        $logger = new Logger('PaymentFrontController');
        $logger->pushHandler(new StreamHandler(Constants::LOGGER_FILE, Logger::DEBUG));
        return $logger;
    }

    private function validateTransaction(string $resultCode): bool
    {
        $testMode = Config::getDataFastRequest()->isTestMode();
        $validTransaction = false;

        if (in_array($resultCode, Constants::TRANSACTION_APPROVED_TEST) && $testMode) {
            $validTransaction = true;
        }
        if ($resultCode == Constants::TRANSACTION_APPROVED_PROD && !$testMode) {
            $validTransaction = true;
        }
        return $validTransaction;
    }

    private function addTransaction(PaymentResponse $paymentResponse)
    {

        $cartId = $paymentResponse->getCartId();
        $customerId = $paymentResponse->getCustomerId();
        $total = $paymentResponse->getAmount();
        $resultCode = $paymentResponse->getResult()->getCode();
        $message = Message::getMessageDescription($resultCode);

        $transaction_id = $paymentResponse->getId();
        $authorization = null;
        $batch = null;
        $reference_number = null;
        $response_code = null;
        $acquirer_code = null;
        $customer_params = null;
        $updated_at = null;

        if ($paymentResponse->isAccepted()) {
            $resultDetails = $paymentResponse->getResultDetails();

            $referenceNbr = $resultDetails->getReferenceNbr();
            $acquirerResponse = $resultDetails->getAcquirerResponse();

            $authorization = $paymentResponse->getResultDetails()->getAuthCode();


            $batch = explode("_", $referenceNbr)[0];
            $reference_number = explode("_", $referenceNbr)[1];
            $response_code = explode("_", $acquirerResponse)[0];
            $acquirer_code = explode("_", $acquirerResponse)[1];
            $customer_params = $paymentResponse->getCustomParameters();

        }

        $query = 'INSERT INTO ' . _DB_PREFIX_ . 'smilepagos
                             (`cart_id`, 
                             `customer_id`, 
                             `total`, 
                             `message`, 
                             `status`, 
                             `authorization`, 
                             `transaction_id`, 
                             `batch`,
                             `reference_number`,
                             `response_code`,
                             `acquirer_code`, 
                             `customer_params`, 
                             `updated_at`)
                                 VALUES ( 
                                        ' . $cartId . ',
                                      \'' . $customerId . '\',
                                        ' . $total . ',
                                      \'' . $message . '\',
                                    \'' . $paymentResponse->isAccepted() . '\',
                                    \'' . $authorization . '\',
                                    \'' . $transaction_id . '\',
                                     \'' . $batch . '\',
                                      \'' . $reference_number . '\',
                                     \'' . $response_code . '\',
                                     \'' . $acquirer_code . '\',
                                     \'' . $customer_params . '\',
                                    \'' . date('Y-m-d H:i:s') . '\'
                         )';

        Db::getInstance()->execute($query);
    }

    private function getDetailErrorMessage(string $resultCode): string
    {
        return Message::getClientMessageDescription($resultCode);
    }
}

