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

if (!defined('_PS_VERSION_')) {
    exit;
}

class IZerSync extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'iZerSync';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'LightX';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('iZerSync');
        $this->description = $this->l('synchronize prestashop with iZer system. ');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('IZERSYNC_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayHome') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('actionProductAdd') &&
            $this->registerHook('actionProductDelete') &&
            $this->registerHook('actionProductUpdate');
    }

    public function uninstall()
    {
        Configuration::deleteByName('IZERSYNC_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitIZerSyncModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
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
        $helper->submit_action = 'submitIZerSyncModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
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
                        'label' => $this->l('Live mode'),
                        'name' => 'IZERSYNC_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
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
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'IZERSYNC_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'IZERSYNC_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'IZERSYNC_LIVE_MODE' => Configuration::get('IZERSYNC_LIVE_MODE', true),
            'IZERSYNC_ACCOUNT_EMAIL' => Configuration::get('IZERSYNC_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'IZERSYNC_ACCOUNT_PASSWORD' => Configuration::get('IZERSYNC_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $statusId = $params['newOrderStatus']->id;

        $order = new Order((int)$params['id_order']);
        $cart = new Cart((int)$order->id_cart);
        
        $statusName = array_values((new OrderState($statusId))->name)[0];
        
        $deliveryAddress = new Address(intval($cart->id_address_delivery));
        $invoiceAddress = new Address(intval($cart->id_address_invoice));
        
        $deliveryCustomer = new Customer((int)($deliveryAddress->id_customer));
        $invoiceCustomer = new Customer((int)($invoiceAddress->id_customer));

        $deliveryDateTime = Db::getInstance()->getValue("SELECT delivery_date FROM ps_orders WHERE id_order = $params[id_order]");
        $deliveryDate = strtotime(explode(" ", $deliveryDateTime)[0]);
        $deliveryTime = strtotime(explode(" ", $deliveryDateTime)[1]);
        
        $items = $cart->getProducts(true);

        $data = [
            'order' => [
                'created_at' => date("d/m/y") . ' ' . date('G:i:s',  time()),
                'id' => $params['id_order'],
                'order_number' => $params['id_order'],
                'shipping_date' => date("d/m/y", $deliveryDate),
                'shipping_time' => date('G:i', $deliveryTime) . '-' . date('G:i', $deliveryTime + 60*60*4),
                'greeting' => 'שבשגבגבגב',
                'note' => '',
                'status' => $statusName,
                'total' => $order->total_paid_tax_incl,
                'total_shipping' => $order->total_shipping,
                'billing_address' => [
                    'first_name' => $invoiceAddress->firstname,
                    'last_name' => $invoiceAddress->lastname, 
                    'email' => $invoiceCustomer->email,
                    'phone' => $invoiceAddress->phone
                ],
                'shipping_address' => [
                    'recipient_name' => $deliveryAddress->firstname . " " . $deliveryAddress->lastname,
                    'phone' => $deliveryAddress->phone,
                    'phone2' => $deliveryAddress->phone,
                    'city' => $deliveryAddress->city,
                    'address_1' => $deliveryAddress->address1,
                    'address_2' => $deliveryAddress->address2,
                    'street_number' => $deliveryAddress->address1,
                    'entrance' => $deliveryAddress->address1,
                    'appartment' => $deliveryAddress->address1,
                    'floor' => $deliveryAddress->address1
                ],
                'line_items' => $items
            ]
        ];

        $this->sendFromPHP($data, "order");
        die();
    }

    public function hookActionProductAdd($params)
    {
        $product = $params['product'];
        $category = new Category((int)$product->id_category_default, (int)$this->context->language->id);

        $data = [
            'title' => implode($product->name),
            'catalog_number' => $product->reference,
            'short_description' => implode($product->description_short),
            'regular_price' => $product->price,
            'sale_price'  => $product->price,
            'type' => 'simple',
            'id' => $product->id,
            'variations' => [],
            'category' => $category->name
        ];

        $this->sendFromPHP($data, "product");
    }

    public function hookActionProductDelete($params)
    {
        $product = $params['product'];
        $category = new Category((int)$product->id_category_default, (int)$this->context->language->id);

        $data = [
            'title' => implode($product->name),
            'catalog_number' => $product->reference,
            'short_description' => implode($product->description_short),
            'regular_price' => $product->price,
            'sale_price' => $product->price,
            'type' => 'simple',
            'id' => $product->id,
            'variations' => [],
            'category' => $category->name
        ];

        $this->sendFromPHP($data, "product_remove");
    }

    public function hookActionProductUpdate($params)
    {
        $product = $params['product'];
        $category = new Category((int)$product->id_category_default, (int)$this->context->language->id);

        $data = [
            'title' => implode($product->name),
            'catalog_number' => $product->reference,
            'short_description' => implode($product->description_short),
            'regular_price' => $product->price,
            'sale_price' => $product->price,
            'type' => 'simple',
            'id' => $product->id,
            'variations' => [],
            'category' => $category->name
        ];

        $this->sendFromPHP($data, "product");
    }

    public function sendFromPHP($data, $api) {
        $url = "https://izer.co.il/crm/" . $api . "_api.php"; // "presta.lightx.co.il/testIzer.php"; //  
        
        if ($api == "order") {
            $data["order"]["site_url"] = "http://www.sadeflowers.co.il";
            $data["order"]["izer_key"] = "57c781e8f0d2b1ce3a4104c2d3b51676";
            $data["order"]["created_at"] = date('TH:i:sY-m-d');
        } else {
            $data["site_url"] = "http://www.sadeflowers.co.il";
            $data["izer_key"] = "57c781e8f0d2b1ce3a4104c2d3b51676";
            $data["created_at"] = date('TH:i:sY-m-d');
        }

        $this->debugConsole("data", $data);

        $dataJson = json_encode($data);  
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($dataJson))                                                                       
        );  

        $server_output = curl_exec($ch);

        curl_close ($ch);
        ppp($server_output);
        echo "<script> console.log('izer response:', '" . $server_output . "'); </script>";
    }

    public function debugConsole($label, $data) {
        echo "$label :";
        ppp($data);
        echo "<script> console.log('" . $label . "', JSON.parse('" . json_encode($data) . "')); </script>";
    }
}
