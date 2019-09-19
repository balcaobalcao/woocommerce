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

                    $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Balcão Balcão', 'balcaobalcao_shipping');
                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                    $this->token = isset($this->settings['token']) ? $this->settings['token'] : null;
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
                        'title' => array(
                            'title' => __('Título', 'balcaobalcao_shipping'),
                            'type' => 'text',
                            'default' => __('Balcão Balcão', 'balcaobalcao_shipping')
                        ),
                        'enabled' => array(
                            'title' => __('Habilitado', 'balcaobalcao_shipping'),
                            'type' => 'checkbox'
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
                            'class' => 'postcode',
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
                            'title' => __('Status Envio *'),
                            'desc_tip' => __('Situação do pedido que cadastra o pedido no Balcão Balcão (podem ser múltiplas). Padrão: Processando.', 'balcaobalcao_shipping'),
                            'type' => 'multiselect',
                            'css' => 'height: 150px',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_cancel' => array(
                            'title' => __('Status Cancelamento *'),
                            'desc_tip' => __('Situação do pedido que cancela o pedido no Balcão Balcão (podem ser múltiplas). Padrão: Cancelado.', 'balcaobalcao_shipping'),
                            'type' => 'multiselect',
                            'css' => 'height: 150px',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_agent' => array(
                            'title' => __('Status Agente Origem *'),
                            'desc_tip' => __('Situação do pedido que é definida quando o agente de origem confirma o recebimento dos produtos para envio. Padrão: Despachado.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_sent' => array(
                            'title' => __('Status Encaminhado ao Destino'),
                            'desc_tip' => __('Situação do pedido que é definida quando o agente de origem encaminha ao agente de destino. Padrão: Despachado.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                        ),
                        'order_status_destiny' => array(
                            'title' => __('Status Agente Destino *'),
                            'desc_tip' => __('Situação do pedido que é definida quando o pedido chega no agente de destino. Padrão: Completo.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                            'custom_attributes' => array(
                                'required' => 'required',
                            )
                        ),
                        'order_status_customer' => array(
                            'title' => __('Status Retirado'),
                            'desc_tip' => __('Situação do pedido que é definida quando é o pedido foi retirado pelo cliente.', 'balcaobalcao_shipping'),
                            'type' => 'select',
                            'options' => wc_get_order_statuses(),
                        ),
                        'total' => array(
                            'title' => __('Valor Mínimo', 'balcaobalcao_shipping'),
                            'desc_tip' => __('Valor mínimo do Sub-Total necessário para exibir as opções do Balcão Balcão.', 'balcaobalcao_shipping'),
                            'type' => 'text',
                        ),
                        'tax' => array(
                            'title' => __('Taxa'),
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
                public function calculate_shipping($package)
                {
                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => '10.98',
                        'calc_tax' => 'per_item'
                    );
                    // Register the rate
                    $this->add_rate($rate);
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
}