<?php
/*
Plugin Name: Balcão Balcão
Plugin URI: https://www.balcaobalcao.com.br/
Description: Shipment via Balcão Balcão
Version: 1.0.5
Author: balcaobalcao
*/

/**
 * Valida se o Woocommerce está ativo.
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

  add_action('woocommerce_shipping_init', 'balcaobalcao_shipping_init');
  function balcaobalcao_shipping_init()
  {
    if (!class_exists('WC_Balcaobalcao_Shipping_Method')) {
      class WC_Balcaobalcao_Shipping_Method extends WC_Shipping_Method
      {
        /**
         * Construtor da classe
         *
         * @access public
         * @return void
         */
        public function __construct($instance_id = 0)
        {
          $this->id                 = 'balcaobalcao_shipping'; // Id do método de entrega.
          $this->instance_id        = absint($instance_id);
          $this->method_title       = __('Balcão Balcão');  // Título mostrado no admin.
          $this->method_description = __('Configure your integration with Balcão Balcão. Access your dashboard at Balcão Balcão to find the integration token and url endpoints.'); // Descrição mostrada no admin.
          $this->supports = array(
            'shipping-zones',
            'settings'
          );

          $this->init();

          /**
           * Carrega as configurações
           */
          $this->config['is_enabled'] = isset($this->settings['is_enabled']) ? $this->settings['is_enabled'] : 'yes';
          $this->config['debug'] = isset($this->settings['debug']) ? $this->settings['debug'] : 'no';
          $this->config['title'] = isset($this->settings['title']) ? $this->settings['title'] : __('Balcão Balcão', 'balcaobalcao_shipping');
          $this->config['token'] = isset($this->settings['token']) ? $this->settings['token'] : null;
          $this->config['endpoint'] = isset($this->settings['endpoint']) ? $this->settings['endpoint'] : null;
          $this->config['return_url'] = site_url('wp-json/balcaobalcao/v1/legacy-callback');
          $this->config['postcode'] = isset($this->settings['postcode']) ? $this->settings['postcode'] : null;
          $this->config['additional_time'] = isset($this->settings['additional_time']) ? $this->settings['additional_time'] : null;
          $this->config['order_status_send'] = isset($this->settings['order_status_send']) ? $this->settings['order_status_send'] : null;
          $this->config['order_status_cancel'] = isset($this->settings['order_status_cancel']) ? $this->settings['order_status_cancel'] : null;
          $this->config['order_status_agent'] = isset($this->settings['order_status_agent']) ? $this->settings['order_status_agent'] : null;
          $this->config['order_status_sent'] = isset($this->settings['order_status_sent']) ? $this->settings['order_status_sent'] : null;
          $this->config['order_status_destiny'] = isset($this->settings['order_status_destiny']) ? $this->settings['order_status_destiny'] : null;
          $this->config['order_status_hub'] = isset($this->settings['order_status_hub']) ? $this->settings['order_status_hub'] : null;
          $this->config['order_status_customer'] = isset($this->settings['order_status_customer']) ? $this->settings['order_status_customer'] : null;
          $this->config['total'] = isset($this->settings['total']) ? $this->settings['total'] : null;
          $this->config['tax'] = isset($this->settings['tax']) ? $this->settings['tax'] : null;

          // Textos fixos
          $this->config['text_to_customer'] = __('Your tracking code is %s, you can track this order through our Android or iOS app. <br/> <br/> Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank">www.balcaobalcao.com.br</a>');
          $this->config['text_to_store'] = __('<b> Attention Shopkeeper: </b> This order tracking code is %s. If you already have registered your credit card, we will soon process your payment and notify you if any problems occur. If you have not yet registered, pay for this tag on our dashboard <a href="https://dashboard.balcaobalcao.com.br/etiquetas/%s" target="_blank">dashboard.balcaobalcao.com.br</a>. You can register your credit card and authorize it to automate the payment process of the tags through our Dashboard, in the My Data area. <br/><br/> Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank"> www.balcaobalcao.com.br </a>');
          $this->config['text_status_agent'] = __('Hello, your order is already with our origin agent and is being prepared for shipping, please use this tracking code %s to track through our website or from Android and iOS app. <br/> <br/> Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank">www.balcaobalcao.com.br</a>');
          $this->config['text_status_sent'] = __('Hi, your order has left the origin city and it\'s on its way to the address you have chosen, please use the tracking code %s to track through our Android or iOS app. <br/><br/> Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank"> www.balcaobalcao.com.br</a>');
          $this->config['text_status_hub'] = __('Hi, your order %s has arrived in our hub agent and soon it will continue it\'s journey!.<br/><br/>Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank"> www.balcaobalcao.com.br</a>');
          $this->config['text_status_destiny'] = __('Hello, your order has arrived at the BB agency you have chosen! Your order is looking forward to meeting you;). Oh, don\'t forget to bring your photo ID! <br/> <br/> Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank">www.balcaobalcao.com.br</a>');
          $this->config['text_status_customer'] = __('Balcão Balcão thanks for your confidence in our service. <br/><br/> Balcão Balcão, the order in your hand! <br/> <a href="https://www.balcaobalcao.com.br/" target="_blank">www.balcaobalcao.com.br</a>');

          require_once('includes/balcaobalcao.php');
          $this->balcaobalcao = new BalcaoBalcao($this->config);

          require_once('includes/balcaobalcao-helpers.php');
          $this->helpers = new Balcaobalcao_Helpers();
        }

        /**
         * Inicializa as configurações
         *
         * @access public
         * @return void
         */
        function init()
        {
          $this->load_scripts();
          $this->init_form_fields();
          $this->init_settings();

          $this->title = $this->get_option('title');

          // Salva as configurações definidas no admin.
          add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Inicia os campos do admin.
         * @return void
         * @author Bruno Marsiliio <bruno@ezoom.com.br>
         */
        function init_form_fields()
        {
          $woocommerce_status = array_merge(
            array('' => __('Do not update')),
            wc_get_order_statuses()
          );

          $this->form_fields = array(
            'is_enabled' => array(
              'title' => __('Enabled', 'balcaobalcao_shipping'),
              'type' => 'checkbox'
            ),
            'debug' => array(
              'title' => __('Logs', 'balcaobalcao_shipping'),
              'label' => __('Enabled', 'balcaobalcao_shipping'),
              'desc_tip' => __('Enables error logs. Logs are saved to wp-content/balcaobalcao-shipping/logs.', 'balcaobalcao_shipping'),
              'type' => 'checkbox'
            ),
            'title' => array(
              'title' => __('Title', 'balcaobalcao_shipping'),
              'type' => 'text',
              'default' => __('Balcão Balcão', 'balcaobalcao_shipping')
            ),
            'token' => array(
              'title' => __('Token *', 'balcaobalcao_shipping'),
              'desc_tip' => __('Access code for communication between Balcão Balcão and your store.', 'balcaobalcao_shipping'),
              'type' => 'text',
              'custom_attributes' => array(
                'required' => 'required',
                'maxlength' => 191
              )
            ),
            'endpoint' => array(
              'title' => __('Endpoint url *', 'balcaobalcao_shipping'),
              'desc_tip' => __('Balcão Balcão API Url. Allows you to define which Balcão Balcão environment you are using. Always put "/" in the end.', 'balcaobalcao_shipping'),
              'type' => 'text',
              'custom_attributes' => array(
                'required' => 'required',
                'maxlength' => 191
              )
            ),
            'postcode' => array(
              'title' => __('Origin ZIP Code *', 'balcaobalcao_shipping'),
              'desc_tip' => __('Zip code from where you will ship the products and consult the Balcão Balcão API.', 'balcaobalcao_shipping'),
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
              'title' => __('Additional Time*', 'balcaobalcao_shipping'),
              'desc_tip' => __('Number of days that will be added to the deadline (usually the number of days you need to prepare and deliver products to our agent). Default: 0.', 'balcaobalcao_shipping'),
              'type' => 'number',
              'default' => 0,
              'custom_attributes' => array(
                'required' => 'required',
                'max' => 60,
                'min' => 0
              )
            ),
            'total' => array(
              'title' => __('Minimum value', 'balcaobalcao_shipping'),
              'desc_tip' => __('Minimum amount of Subtotal required to display Balcão Balcão options.', 'balcaobalcao_shipping'),
              'type' => 'text',
              'class' => 'number-mask',
            ),
            'tax' => array(
              'title' => __('Tax', 'balcaobalcao_shipping'),
              'desc_tip' => __('Embed over the Balcão Balcão rate over the freight amount. If active, the customer receives the tax plus the freight, if inactive the store assumes the tax.', 'balcaobalcao_shipping'),
              'type' => 'select',
              'options' => array(
                1 => __('Enabled'),
                0 => __('Disabled'),
              ),
              'custom_attributes' => array(
                'required' => 'required',
              )
            ),
            array(
              'title' => __('Integrate Orders', 'balcaobalcao_shipping'),
              'type'  => 'title',
              'description'  => __('This options allows your store to automatically integrate orders in our system and receive the tracking code. (Only those that were selected Balcão Balcão as shipping)', 'balcaobalcao_shipping'),
              'id'    => 'send-orders',
            ),
            'order_status_send' => array(
              'title' => __('Shipping Status *', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that registers the order at the Balcão Balcão (may be multiple). Default: Processing.', 'balcaobalcao_shipping'),
              'type' => 'multiselect',
              'css' => 'height: 150px',
              'options' => wc_get_order_statuses(),
              'custom_attributes' => array(
                'required' => 'required',
              )
            ),
            'order_status_cancel' => array(
              'title' => __('Cancellation Status *', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that cancels the order at the Balcão Balcão (may be multiple). Default: Canceled.', 'balcaobalcao_shipping'),
              'type' => 'multiselect',
              'css' => 'height: 150px',
              'options' => wc_get_order_statuses(),
              'custom_attributes' => array(
                'required' => 'required',
              )
            ),
            array(
              'title' => __('Automatic Order Update', 'balcaobalcao_shipping'),
              'type'  => 'title',
              'description'  => __('This options allows your store to automatically update the orders status that have been integrated accordingly to the status in our system.', 'balcaobalcao_shipping'),
              'id'    => 'auto-update',
            ),
            'order_status_agent' => array(
              'title' => __('Origin Agent Status', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that defines when the originating agent confirms the package for shipment. Default: Dispatched.', 'balcaobalcao_shipping'),
              'type' => 'select',
              'options' => $woocommerce_status,
            ),
            'order_status_sent' => array(
              'title' => __('Forwarded to Destination Status', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that defines when the originating agent forwards to the destination agent. Default: Dispatched.', 'balcaobalcao_shipping'),
              'type' => 'select',
              'options' => $woocommerce_status,
            ),
            'order_status_hub' => array(
              'title' => __('Hub Agent Status', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that defines when the package is at some hub. Default: Dispatched.', 'balcaobalcao_shipping'),
              'type' => 'select',
              'options' => $woocommerce_status,
            ),
            'order_status_destiny' => array(
              'title' => __('Destination Agent Status', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that defines when the packages arrives at the destination agent. Default: Full.', 'balcaobalcao_shipping'),
              'type' => 'select',
              'options' => $woocommerce_status,
            ),
            'order_status_customer' => array(
              'title' => __('Delivered Status', 'balcaobalcao_shipping'),
              'desc_tip' => __('Order status that define when the packages has been delivered to the customer.', 'balcaobalcao_shipping'),
              'type' => 'select',
              'options' => $woocommerce_status,
            ),
          );
        }

        /**
         * Calcula o frete.
         *
         * @access public
         * @param mixed $package
         * @return void
         */
        public function calculate_shipping($package = array())
        {
          // Valida o valor mínimo.
          if (($package['contents_cost'] < $this->config['total']) || $this->config['is_enabled'] == 'no') {
            return false;
          }

          // Busca os produtos do carrinho.
          $products = $package['contents'];

          //Adiciona ao tempo qualquer outro valor ex.: Caso exista prazo de fabricação por produto
          $additional_time = (int) $this->config['additional_time'];

          foreach ($products as $key => $product) {

            // Busca a unidade de peso da loja.
            $woocommerce_weight_unit = get_option('woocommerce_weight_unit');

            // Busca a unidade de medida da loja.
            $woocommerce_dimension_unit = get_option('woocommerce_dimension_unit');

            // Converte para metros, medidas são unitárias.
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

          // Valida se retornou alguma cotação da API.
          if ($json->status_code == 422 || $json->status_code == 408)
            return false;


          $rate = array();
          foreach ($json->data as $key => $quote) {
            $rate[] = array(
              'id' => $key + 1,
              'label' => $this->config['title'] . ': ' . $quote->name . ' - (' . $quote->deadline . ') <br/>' . $quote->address,
              'cost' => $quote->price,
              'meta_data' => array(
                __('Deadline') => $quote->deadline,
                'Token' => $quote->token
              ),
              'calc_tax' => 'per_order',
            );
          }

          // Registra a cotação.
          foreach ($rate as $key => $value) {
            $this->add_rate($value);
          }
        }

        /**
         * Callback recebido da API do Balcão Balcão.
         * @return void
         * @author Bruno Marsilio <bruno@ezoom.com.br>
         */
        public function callback()
        {
          $order_id = sanitize_text_field($_POST['order_id']);
          $status_id = sanitize_text_field($_POST['status_id']);
          $tracking_code = sanitize_text_field($_POST['tracking_code']);

          if (!$order_id || !$status_id || !$tracking_code) {
            // Cria a resposta de erro
            return new WP_REST_Response(array(
              'status_code' => 401,
              'message' => __('Wrong parameters')
            ), 401);
          }

          // Busca o pedido.
          $order = null;
          $wcOrder = wc_get_order($order_id);
          if ($wcOrder->has_shipping_method($this->id)) {
            $shipping_info = $wcOrder->get_items('shipping');
            foreach ($shipping_info as $key => $shipping) {
              $tracking = $shipping->get_meta(__('Tracking Code', 'balcaobalcao_shipping'));
              if ($tracking == $tracking_code) {
                $order = $wcOrder;
                break;
              }
            }

            if ($order) {
              $balcaobalcao_shipping = new WC_Balcaobalcao_Shipping_Method();
              $woocommerce_status = array(
                3 => $balcaobalcao_shipping->config['order_status_agent'],
                4 => $balcaobalcao_shipping->config['order_status_sent'],
                5 => $balcaobalcao_shipping->config['order_status_destiny'],
                6 => $balcaobalcao_shipping->config['order_status_customer'],
                7 => $balcaobalcao_shipping->config['order_status_hub'],
              );

              $woocommerce_text_status = array(
                3 => $balcaobalcao_shipping->config['text_status_agent'],
                4 => $balcaobalcao_shipping->config['text_status_sent'],
                5 => $balcaobalcao_shipping->config['text_status_destiny'],
                6 => $balcaobalcao_shipping->config['text_status_customer'],
                7 => $balcaobalcao_shipping->config['text_status_hub'],
              );

              $order_new_status = NULL;
              $comment = NULL;
              if (isset($woocommerce_status[$status_id]) && !empty($woocommerce_status[$status_id])) {
                $order_new_status = $woocommerce_status[$status_id];
                $comment = isset($woocommerce_text_status[$status_id]) ? sprintf($woocommerce_text_status[$status_id], $tracking_code) : ' - ';
              }

              if ($order_new_status && $comment) {
                $order->set_status($order_new_status);
                $order->add_order_note($comment, 1);
                $order->save();

                return new WP_REST_Response(array(
                  'status_code' => 200,
                  'message' => __('Order successfully updated')
                ));
              }
            }
          }

          return new WP_REST_Response(array(
            'status_code' => 200,
            'message' => __('No action required')
          ));
        }

        private function load_scripts()
        {
          $plugin_dir = plugin_dir_url(__FILE__);

          // Carrega jQueryMask
          wp_register_script('balcaobalcao-shipping-masks', $plugin_dir . 'public/js/jquery-mask/jquery.mask.js', array('wc-clipboard'), WC_VERSION);
          wp_enqueue_script('balcaobalcao-shipping-masks');

          // Carrega custom js
          wp_register_script('balcaobalcao-shipping-custom', $plugin_dir . 'public/js/custom.js', array('wc-clipboard'), WC_VERSION);
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

  add_action('woocommerce_thankyou', 'balcaobalcao_send_quote');
  function balcaobalcao_send_quote($order_id)
  {
    if (!$order_id)
      return;

    $order = wc_get_order($order_id);
    if ($order->has_shipping_method('balcaobalcao_shipping')) {

      $balcaobalcao_shipping = new WC_Balcaobalcao_Shipping_Method();
      $list_order_status_send = $balcaobalcao_shipping->config['order_status_send'];

      $current_order_status = 'wc-' . $order->get_status();

      // Valida para enviar pro Balcão Balcão apenas uma vez
      $shipping_info = $order->get_items('shipping');
      foreach ($shipping_info as $key => $shipping) {
        if ($shipping->get_meta(__('Tracking Code', 'balcaobalcao_shipping')))
          return;
      }

      foreach ($list_order_status_send as $key => $order_status_send) {
        // Se o status atual do pedido for igual ao status configurado em "Status Envio"
        if ($order_status_send == $current_order_status)
          $balcaobalcao_shipping->balcaobalcao->sendOrder($balcaobalcao_shipping->config, $order, $order_status_send);
      }
    }
  }

  add_action('woocommerce_order_status_changed', 'balcaobalcao_change_order_status', 10, 3);
  function balcaobalcao_change_order_status($order_id, $status_old, $status_new)
  {
    if (!$order_id)
      return;

    $order = null;
    $tracking_code = null;
    $wcOrder = wc_get_order($order_id);
    if ($wcOrder->has_shipping_method('balcaobalcao_shipping')) {
      $shipping_info = $wcOrder->get_items('shipping');
      foreach ($shipping_info as $key => $shipping) {
        $tracking_code = $shipping->get_meta(__('Tracking Code', 'balcaobalcao_shipping'));
        $order = $wcOrder;
        break;
      }

      if ($order && $tracking_code) {

        $balcaobalcao_shipping = new WC_Balcaobalcao_Shipping_Method();
        $list_order_status_cancel = $balcaobalcao_shipping->config['order_status_cancel'];

        foreach ($list_order_status_cancel as $key => $order_status_send) {
          if ($order_status_send == 'wc-' . $status_new) {
            $balcaobalcao_shipping->balcaobalcao->updateOrder($balcaobalcao_shipping->config, $tracking_code, 2/*cancelado*/);
          }
        }
      }
    }
  }

  function legacy_callback(WP_REST_Request $request)
  {
    do_action('woocommerce_shipping_init');

    $wcBalcaoBalcao = new WC_Balcaobalcao_Shipping_Method();
    return $wcBalcaoBalcao->callback();
  }

  add_action('rest_api_init', function () {
    register_rest_route('balcaobalcao/v1', 'legacy-callback', array(
      'methods' => 'POST',
      'callback' => 'legacy_callback'
    ));
  });
}
