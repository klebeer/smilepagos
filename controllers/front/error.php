<?php


class SmilepagosErrorModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $error_message = Context::getContext()->cookie->errorMessage;
        Context::getContext()->smarty->assign(array(
            'error_msg' => $error_message,
            'redirect' => $this->context->link->getPageLink('order')
        ));

        $this->setTemplate('module:smilepagos/views/templates/front/error.tpl');
    }
}
