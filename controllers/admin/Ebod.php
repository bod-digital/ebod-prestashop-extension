<?php
/**
 * <ModuleClassName> => MyStock
 * <FileName> => stock.php
 * Format expected: <ModuleClassName><FileName>ModuleFrontController
 */
class EbodController extends ModuleAdminController
{
    public $auth = false;

    public function initContent()
    {
        parent::initContent();
        $template_file = _PS_MODULE_DIR_ . '/ebod/views/frame.tpl';
        $content = $this->context->smarty->fetch($template_file);
        $this->context->smarty->assign([
            'content' => $content
        ]);
    }
}
