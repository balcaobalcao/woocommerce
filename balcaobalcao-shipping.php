<?php
/*
Plugin Name: Balcão Balcão
Plugin URI: https://woocommerce.com/
Description: Your shipping method plugin
Version: 1.0.0
Author: WooThemes
Author URI: https://woocommerce.com/
*/

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    add_action('woocommerce_shipping_init', 'balcaobalcao_shipping_init');
    function balcaobalcao_shipping_init()
    {
        if (!class_exists('WC_Balcaobalcao_Shipping_Method')) {
            class WC_Balcaobalcao_Shipping_Method extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct($instance_id = 0)
                {
                    $this->id                 = 'balcaobalcao_shipping'; // Id for your shipping method. Should be uunique.
                    $this->instance_id        = absint($instance_id);
                    $this->method_title       = __('Balcão Balcão');  // Title shown in admin
                    $this->method_description = __('Balcão Balcão API'); // Description shown in admin
                    $this->supports = array(
                        'shipping-zones',
                        'settings'
                    );

                    $this->init();

                    /**
                     * Load settings
                     */
                    $this->config['is_enabled'] = isset($this->settings['is_enabled']) ? $this->settings['is_enabled'] : 'yes';
                    $this->config['debug'] = isset($this->settings['debug']) ? $this->settings['debug'] : 'no';
                    $this->config['title'] = isset($this->settings['title']) ? $this->settings['title'] : __('Balcão Balcão', 'balcaobalcao_shipping');
                    $this->config['token'] = isset($this->settings['token']) ? $this->settings['token'] : null;
                    $this->config['endpoint'] = isset($this->settings['endpoint']) ? $this->settings['endpoint'] : null;
                    $this->config['return_url'] = isset($this->settings['return_url']) ? $this->settings['return_url'] : null;
                    $this->config['postcode'] = isset($this->settings['postcode']) ? $this->settings['postcode'] : null;
                    $this->config['additional_time'] = isset($this->settings['additional_time']) ? $this->settings['additional_time'] : null;
                    $this->config['order_status_send'] = isset($this->settings['order_status_send']) ? $this->settings['order_status_send'] : null;
                    $this->config['order_status_cancel'] = isset($this->settings['order_status_cancel']) ? $this->settings['order_status_cancel'] : null;
                    $this->config['order_status_agent'] = isset($this->settings['order_status_agent']) ? $this->settings['order_status_agent'] : null;
                    $this->config['order_status_sent'] = isset($this->settings['order_status_sent']) ? $this->settings['order_status_sent'] : null;
                    $this->config['order_status_destiny'] = isset($this->settings['order_status_destiny']) ? $this->settings['order_status_destiny'] : null;
                    $this->config['order_status_customer'] = isset($this->settings['order_status_customer']) ? $this->settings['order_status_customer'] : null;
                    $this->config['total'] = isset($this->settings['total']) ? $this->settings['total'] : null;
                    $this->config['tax'] = isset($this->settings['tax']) ? $this->settings['tax'] : null;
                    $this->config['text_to_customer'] = __('Seu código de rastreio Balcão Balcão é %s, você pode acompanhar este pedido pelo nosso aplicativo Android ou iOS.<br/><br/>Balcão Balcão, a encomenda na sua mão!<br/><a href=\'https://www.balcaobalcao.com.br/\' target=\'_blank\'>www.balcaobalcao.com.br</a>');
                    $this->config['text_to_store'] = __('O código de rastreio Balcão Balcão para este pedido é %s. Entretanto não foi possível gerar o pedido automaticamente, efetue o pagamento da sua etiqueta em nosso dashboard <a href=\'https://dashboard.balcaobalcao.com.br/etiquetas/%s\' target=\'_blank\'>dashboard.balcaobalcao.com.br</a>. Você pode cadastrar seu cartão de crédito para automatizar este processo ou se você ainda não é correntista BB entre em contato conosco e solicite maiores informações.<br/><br/>Balcão Balcão, a encomenda na sua mão!<br/><a href=\'https://www.balcaobalcao.com.br/\' target=\'_blank\'>www.balcaobalcao.com.br</a>');

                    require_once('includes/balcaobalcao.php');
                    $this->balcaobalcao = new BalcaoBalcao($this->config);

                    require_once('includes/helpers.php');
                    $this->helpers = new Helpers();
                }
                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->load_scripts();
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                /**
                 * Init admin form fields
                 * @return void
                 * @author Bruno Marsiliio <bruno@ezoom.com.br>
                 */
                function init_form_fields()
                {
                    $this->form_fields = array(
                        'is_enabled' => array(
                            'title' => __('Habilitado', 'balcaobalcao_shipping'),
                            'type' => 'checkbox'
                        ),
                        'debug' => array(
                            'title' => __('Logs', 'balcaobalcao_shipping'),
                            'label' => __('Habilitado', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Habilita os logs de erros. Os logs são salvos em wp-content/balcaobalcao-shipping/logs.', 'balcaobalcao_shipping'),
                            'type' => 'checkbox'
                        ),
                        'title' => array(
                            'title' => __('Título', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'default' => __('Balcão Balcão', 'balcaobalcao_shipping')
                        ),
                        'token' => array(
                            'title' => __('Token *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Código de acesso para comunicação entre o Balcão Balcão e sua loja.', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'custom_attributes' => array(
                                'required' => 'required',
                                'maxlength' => 191
                            )
                        ),
                        'endpoint' => array(
                            'title' => __('Url do Endpoint *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Url da API do Balcão Balcão. Permite definir em qual ambiente do Balcão Balcão deve enviar as solicitações.', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'custom_attributes' => array(
                                'required' => 'required',
                                'maxlength' => 191
                            )
                        ),
                        'return_url' => array(
                            'title' => __('Url de Retorno *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Url de retorno para recebimento do callback nas trocas de status no Balcão Balcão. Permite atualizar automaticamente a troca de status na loja.', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'custom_attributes' => array(
                                'required' => 'required',
                                'maxlength' => 191
                            )
                        ),
                        'postcode' => array(
                            'title' => __('CEP Origem *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('CEP de onde você vai enviar os produtos e consultar a API do Balcão Balcão.', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'class' => 'postcode-mask',
                            'custom_attributes' => array(
                                'required' => 'required',
                                'maxlength' => 9,
                                'minlength' => 9,
                                'autocomplete' => 'off'
                            )
                        ),
                        'additional_time' => array(
                            'title' => __('Prazo Adicional *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Número de dias que serão adicionados ao prazo (geralmente a quantidade de dias que você precisa para preparar e entregar os produtos pro Balcão Balcão). Padrão: 0.', 'balcaobalcao_shipping'),
                            'type' => 'number',
                            'default' => 0,
                            'custom_attributes' => array(
                                'required' => 'required',
                                'max' => 60,
                                'min' => 0
                            )
                        ),
                        'order_status_send' => array(
                            'title' => __('Status Envio *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Situação do pedido que cadastra o pedido no Balcão Balcão (podem ser múltiplas). Padrão: Processando.', 'balcaobalcao_shipping'),
                            'type' => 'multiselect',
                            'css' => 'height: 150px',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_cancel' => array(
                            'title' => __('Status Cancelamento *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Situação do pedido que cancela o pedido no Balcão Balcão (podem ser múltiplas). Padrão: Cancelado.', 'balcaobalcao_shipping'),
                            'type' => 'multiselect',
                            'css' => 'height: 150px',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_agent' => array(
                            'title' => __('Status Agente Origem *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Situação do pedido que é definida quando o agente de origem confirma o recebimento dos produtos para envio. Padrão: Despachado.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_sent' => array(
                            'title' => __('Status Encaminhado ao Destino', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Situação do pedido que é definida quando o agente de origem encaminha ao agente de destino. Padrão: Despachado.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                        ),
                        'order_status_destiny' => array(
                            'title' => __('Status Agente Destino *', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Situação do pedido que é definida quando o pedido chega no agente de destino. Padrão: Completo.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_customer' => array(
                            'title' => __('Status Retirado', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Situação do pedido que é definida quando é o pedido foi retirado pelo cliente.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                        ),
                        'total' => array(
                            'title' => __('Valor Mínimo', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Valor mínimo do Sub-Total necessário para exibir as opções do Balcão Balcão.', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'class' => 'number-mask',
                        ),
                        'tax' => array(
                            'title' => __('Taxa', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Embutir taxa do Balcão Balcão sobre o valor do frete. Se ativo o cliente recebe a taxa acrescida no valor do frete, se inativo a loja assume a taxa.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => array(
                                1 => __('Habilitado'),
                                0 => __('Desabilitado'),
                            ),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),

                    );
                }

                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package = array())
                {

                    // Check the minimum value
                    if( ($package['contents_cost'] < $this->config['total']) || $this->config['is_enabled'] == 'no') {
                        return false;
                    }

                    // Get cart products
                    $products = $package['contents'];

                    //Adicionar ao tempo qualquer outro valor ex.: Caso exista prazo de fabricação por produto
                    $additional_time = (int) $this->config['additional_time'];

                    foreach ($products as $key => $product) {

                        // Get store weight unit
                        $woocommerce_weight_unit = get_option('woocommerce_weight_unit');

                        // Get store dimension unit
                        $woocommerce_dimension_unit = get_option('woocommerce_dimension_unit');

                        // Converte para metros, medidas são unitárias
                        $product['width']  = $this->helpers->getSizeInMeters($woocommerce_dimension_unit, $product['data']->get_width());
                        $product['height'] = $this->helpers->getSizeInMeters($woocommerce_dimension_unit, $product['data']->get_height());
                        $product['length'] = $this->helpers->getSizeInMeters($woocommerce_dimension_unit, $product['data']->get_length());

                        // O peso do produto não é unitário como a dimensão, é multiplicado pela quantidade.
                        $product['weight'] = $this->helpers->getWeightInKg($woocommerce_weight_unit, $product['data']->get_weight()) / $product['quantity'];

                        $products_data[$key] = [
                            'quantity' => $product['quantity'],
                            'weight'   => $product['weight'],
                            'length'   => $product['length'],
                            'width'    => $product['width'],
                            'height'   => $product['height'],
                        ];
                    }

                    $api_data = [
                        'from'            => $this->config['postcode'],
                        'to'              => $package['destination']['postcode'],
                        'value'           => $package['cart_subtotal'],
                        'products'        => $products_data,
                        'token'           => $this->config['token'],
                        'additional_time' => $additional_time,
                        'tax'             => $this->config['tax'],
                    ];

                    $json = $this->balcaobalcao->getData('shipping/find', $api_data);

                    // Valida se retornou alguma cotação da API
                    if($json->status_code == 422) {
                        return false;
                    }

                    $rate = array();
                    foreach ($json->data as $key => $quote) {
                        $rate[] = array(
                            'id' => $key+1,
                            'label' => $this->config['title'].': '.$quote->name . ' - ('. $quote->deadline . ') <br/>' . $quote->address,
                            'cost' => $quote->price,
                            'meta_data' => array(
                                __('Prazo de Entrega') => $quote->deadline,
                                'Token' => $quote->token
                            ),
                            'calc_tax' => 'per_order',
                        );
                    }

                    // Register the rate
                    foreach ($rate as $key => $value) {
                        $this->add_rate($value);
                    }
                }

                private function load_scripts()
                {
                    // Load jQueryMask
                    wp_register_script('balcaobalcao-shipping-masks', WC()->plugin_url() . '/../balcaobalcao-shipping/assets/js/jquery-mask/jquery.mask.js', array('wc-clipboard'), WC_VERSION);
                    wp_enqueue_script('balcaobalcao-shipping-masks');

                    // Load custom js
                    wp_register_script('balcaobalcao-shipping-custom', WC()->plugin_url() . '/../balcaobalcao-shipping/assets/js/custom.js', array('wc-clipboard'), WC_VERSION);
                    wp_enqueue_script('balcaobalcao-shipping-custom');
                }
            }
        }
    }

    add_filter('woocommerce_shipping_methods', 'add_balcaobalcao_shipping');
    function add_balcaobalcao_shipping($methods)
    {
        $methods['balcaobalcao_shipping'] = 'WC_Balcaobalcao_Shipping_Method';
        return $methods;
    }

    add_action('woocommerce_thankyou', 'send_balcaobalcao_quote');
    function send_balcaobalcao_quote($order_id)
    {
        if (!$order_id) {
            return;
        }

        $balcaobalcao_shipping = new WC_Balcaobalcao_Shipping_Method();
        $order_status_send = end($balcaobalcao_shipping->config['order_status_send']);

        $order = wc_get_order($order_id);
        $current_order_status = 'wc-'.$order->get_status();

        // Se o status atual do pedido for igual ao status configurado em "Status Envio"
        if($order_status_send == $current_order_status) {
            $balcaobalcao_shipping->balcaobalcao->sendOrder($balcaobalcao_shipping->config, $order, $order_status_send);
        }
    }
}
