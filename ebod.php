<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ebod extends Module
{
    protected $filterable = 1;
    protected static $products = array();

    public function __construct()
    {
        $this->name = 'ebod';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'bod.digital';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ebod');
        $this->description = $this->l('Dynamic and innovative digital ecosystem that connects merchants and customers through a seamless token-based loyalty system, using bod.digital platform.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall EBOD Tracking? You will lose all the data related to this module.');
    }

    public function install()
    {
        if (version_compare(_PS_VERSION_, '1.6', '>=') && Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!parent::install()
            || !$this->installTab()
            || !$this->registerHook('header')
            || !$this->registerHook('productFooter')
            || !$this->registerHook('orderConfirmation')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!$this->uninstallTab() || !parent::uninstall()) {
            return false;
        }
    }

    public function installTab()
    {
        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            return true;
        }

        $tab_id = Tab::getIdFromClassName('Ebod');
        $languages = Language::getLanguages(false);

        if ($tab_id == false) {
            $tab = new Tab();
            $tab->class_name = 'Ebod';
            $tab->position = 99;
            // This value isn't listed in the developer documenation but I found it in the database.
            $tab->id_parent = (int) Tab::getIdFromClassName('DEFAULT');
            $tab->module = 'ebod';
            foreach ($languages as $language) {
                $tab->name[$language['id_lang']] = "ebod";
            }
            $tab->add();
        }
    }

    public function uninstallTab()
    {
        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            return true;
        }

        $id_tab = (int)Tab::getIdFromClassName('AdminEbod');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }

        return true;
    }

    /**
     * Builds the configuration form
     * @return string HTML code
     */
    public function displayForm()
    {
        // Init Fields form array
        $form = [
            'form' => [
                'legend' => array(
                    'title' => $this->l('Settings'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('EBOD Website Token'),
                        'name' => 'EBOD_STORE_CODE',
                        'size' => 20,
                        'required' => true,
                        'hint' => $this->l('This information is available in your https://app.bod.digital account')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // Default language
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        // Load current value into the form
        $helper->fields_value['EBOD_STORE_CODE'] = Configuration::get('EBOD_STORE_CODE');

        return $helper->generateForm([$form]);
    }

    /**
     * back office module configuration page content
     */
    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $ga_account_id = Tools::getValue('EBOD_STORE_CODE');
            if (!empty($ga_account_id)) {
                Configuration::updateValue('EBOD_STORE_CODE', $ga_account_id);
                Configuration::updateValue('EBOD_CONFIGURATION_OK', true);
                $output .= $this->displayConfirmation($this->l('Store Code updated successfully'));
            }
        }

        $output .= $this->displayForm();

        return $output;
    }

    protected function _getEbodTag()
    {
        return '<script type="text/javascript">
        (function(e,b,o,d){a=b.getElementsByTagName("head")[0];r=b.createElement("script");r.async=1;r.src=o+d;r.onload=function(){Ebod.init(\'' . Tools::safeOutput(Configuration::get('EBOD_STORE_CODE')) . '\');};a.appendChild(r);})(window,document,"https"+":"+"//e.bod.digital/init",".js");
        </script>';
    }

    public function hookHeader()
    {
        if (Configuration::get('EBOD_STORE_CODE')) {
            $tag = $this->_getEbodTag();
            return $tag;
        }
    }

    public function hookOrderConfirmation($params)
    {
        if (Configuration::get('EBOD_STORE_CODE')) {
            $script = "";
            
            $order = $params['order'];
            $items = [];
            $cart = new Cart($order->id_cart);
            $currency = new Currency($this->context->currency->id);
            foreach ($cart->getProducts() as $order_product) {
                $p = $this->wrapProduct($order_product, [], 0, true);
                $items[] = [
                    "productCode" => $p['reference'],
                    "unitPrice" => $p['price'],
                    "quantity" => $p['quantity']
                ];
            }
            $jsonProducts = json_encode($items);
            $currSymbol = $currency->iso_code;
            $script .= "<script type=\"text/javascript\">
            typeof(Ebod) !== 'undefined' && Ebod.purchase({ 
                event: 'ebod_purchase',
                email: '" . $order->email . "',
                externalId: '" . $order->id . "',
                currency: '" . $currSymbol . "',
                totalPrice: '" . $order->total_paid . "',
                languageCode: '" . $this->context->language->iso_code . "',
                products: " . $jsonProducts . "
            });";
            $script .= "</script>";
            return $script;
        }
    }

    public function hookProductFooter($params)
    {
        $productInfo = $params['product']['reference'];
        return "<!-- EBOD track product -->";

    }

    public function wrapProduct($product, $extras, $index = 0, $full = false)
    {
        $product_qty = 1;
        if (isset($extras['qty'])) {
            $product_qty = $extras['qty'];
        } elseif (isset($product['cart_quantity'])) {
            $product_qty = $product['cart_quantity'];
        }

        $product_id = 0;
        if (!empty($product['id_product'])) {
            $product_id = $product['id_product'];
        } else if (!empty($product['id'])) {
            $product_id = $product['id'];
        }

        if (!empty($product['id_product_attribute'])) {
            $product_id .= '-' . $product['id_product_attribute'];
        }

        if ($full) {
            $productInfo = [
                'id' => $product_id,
                'name' => Tools::jsonEncode($product['name']),
                'quantity' => $product_qty,
                'price' => $product['price'],
                'reference' => $product['reference']
            ];
        } else {
            $productInfo = [
                'id' => $product_id,
                'name' => Tools::jsonEncode($product['name'])
            ];
        }

        return $productInfo;
    }
}
