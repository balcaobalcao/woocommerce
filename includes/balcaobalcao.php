<?php

class BalcaoBalcao
{
  private $api_endpoint;
  private $config;

  public function __construct($config)
  {
    $this->config = $config;
    $this->api_endpoint = $this->config['endpoint'];

    if (!$this->config['endpoint']) {
      $this->api_endpoint = 'https://prod.balcaobalcao.com.br/api/';
    }
  }

  public function getData($uri = 'shipping/find', $data)
  {
    $url = $this->api_endpoint . $uri . '?' . http_build_query($data);

    // Log
    $this->write_log('Quote requested: ' . $url);

    $api_data = $this->getAPIData($url);
    $json = json_decode($api_data);

    if (isset($json->status_code) && $json->status_code == 422) {
      // Log
      $this->write_log(json_encode($json));
    }

    return $json;
  }

  public function post($uri = 'order', $data, $method = 'POST', $headers = array())
  {
    $query = http_build_query($data);
    $url = $this->api_endpoint . $uri;

    // Log
    $this->write_log('Post data: ' . $url . '?' . $query);

    $api_data = $this->postAPIData($url, $data, $method, $headers);
    $json = json_decode($api_data);

    if (isset($json->status_code) && $json->status_code == 422) {
      // Log
      $this->write_log(json_encode($json));
    }

    return $json;
  }

  public function getAPIData($url)
  {
    $args = array(
      'timeout' => '30'
    );

    $response = wp_remote_get($url, $args);

    $server_output = wp_remote_retrieve_body($response);

    if (!$server_output) {
      $server_output = $this->_force_error(__('Could not connect to the API.'));
    }

    return $server_output;
  }

  public function postAPIData($url, array $data, $method, $headers)
  {
    $body = $data;
    $args = array(
      'body' => $body,
      'timeout' => '30',
      'redirection' => '5',
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => $headers,
      'cookies' => array()
    );

    if ($method == 'POST') {
      $response = wp_remote_post($url, $args);
    } else {
      $args['method'] = $method;

      $response = wp_remote_request($url, $args);
    }

    $server_output = wp_remote_retrieve_body($response);

    if (!$server_output) {
      $this->_force_error(__('Could not connect to the API.'));
    }

    return $server_output;
  }

  /**
   * Envia um pedido pro Balcão Balcão.
   *
   * @param array $config
   * @param array $order_info
   * @param int $order_status_id
   * @return object
   */
  public function sendOrder($config, WC_Order $order, $order_new_status)
  {
    $balcaobalcao_shipping = new WC_Balcaobalcao_Shipping_Method();

    // Busca os dados do pedido
    $order_info['order_id'] = $order->get_id();
    $order_info['shipping_firstname'] = $order->get_shipping_first_name();
    $order_info['shipping_lastname'] = $order->get_shipping_last_name();
    $order_info['firstname'] = $order->get_billing_first_name();
    $order_info['lastname'] = $order->get_billing_last_name();
    $order_info['shipping_address_1'] = $order->get_shipping_address_1();
    $order_info['shipping_address_2'] = $order->get_shipping_address_2();
    $order_info['customer_id'] = $order->get_customer_id();

    $products = $order->get_items('line_item');
    $products_data = array();
    foreach ($products as $key => $product) {

      $product_detail = $product->get_product();

      // Busca a unidade de peso da loja.
      $woocommerce_weight_unit = get_option('woocommerce_weight_unit');

      // Busca a unidade de medida da loja.
      $woocommerce_dimension_unit = get_option('woocommerce_dimension_unit');

      // Converte para metros, medidas são unitárias.
      $product_info['width']  = $balcaobalcao_shipping->helpers->getSizeInMeters($woocommerce_dimension_unit, $product_detail->get_width());
      $product_info['height'] = $balcaobalcao_shipping->helpers->getSizeInMeters($woocommerce_dimension_unit, $product_detail->get_height());
      $product_info['length'] = $balcaobalcao_shipping->helpers->getSizeInMeters($woocommerce_dimension_unit, $product_detail->get_length());

      // O peso do produto não é unitário como a dimensão, é multiplicado pela quantidade.
      $product_info['weight'] = $balcaobalcao_shipping->helpers->getWeightInKg($woocommerce_weight_unit, $product_detail->get_weight()) / $product->get_quantity();

      $products_data[$key] = array(
        'name' => $product->get_name(),
        'quantity' => $product->get_quantity(),
        'price' => (float) $product->get_total(),
        'weight' => $product_info['weight'],
        'length' => $product_info['length'],
        'width' => $product_info['width'],
        'height' => $product_info['height'],
      );
    }

    $order_info['products'] = $products_data;
    $order_info['email'] = $order->get_billing_email();
    $order_info['telephone'] = $order->get_billing_phone();
    $order_info['total'] = $order->get_total();
    $order_info['date_added'] = $order->order_date;

    $shipping_info = $order->get_items('shipping');
    foreach ($shipping_info as $key => $shipping) {
      $shipping_token = $shipping->get_meta('Token');
    }

    $order_info['shipping_token'] = $shipping_token;

    // Prepara o nome do usuário.
    if (trim($order_info['shipping_firstname'])) {
      $customer_name = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
    } else {
      $customer_name = $order_info['firstname'] . ' ' . $order_info['lastname'];
    }

    // Define o endereço.
    $address = trim($order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2']);
    $address_number = $order->get_meta('_billing_number');
    $address_complement = trim($order_info['shipping_address_2']);
    $document = $order->get_meta('_billing_cpf');

    // Prepara as informações para enviar para a API.
    $data = array(
      'token'      => $config['token'],
      'return_url' => $config['return_url'],
      'customer'   => array(
        'name'               => $customer_name,
        'document'           => $document,
        'email'              => $order_info['email'],
        'phone'              => $order_info['telephone'],
        'address'            => $address,
        'address_number'     => $address_number,
        'address_complement' => $address_complement
      ),
      'order' => array(
        'id'       => $order_info['order_id'],
        'value'    => $order_info['total'],
        'date'     => $order_info['date_added'],
        'token'    => $order_info['shipping_token'],
        'products' => $order_info['products'],
      ),
    );

    $post_data = $this->post('order/store', $data);

    if ($post_data && isset($post_data->tracking_code)) {

      // Atualiza o tracking code com o código recebido da API.
      end($shipping_info)->update_meta_data(__('Tracking Code', 'balcaobalcao_shipping'), $post_data->tracking_code);

      // Se o status da tag for "already paid"
      if ($post_data->status == 1) {
        $notify = 1;
        $comment = sprintf($config['text_to_customer'], $post_data->tracking_code);
      } else {
        $notify = 0;
        $comment = sprintf($config['text_to_store'], $post_data->tracking_code, $post_data->tracking_code);
      }

      $order->set_status($order_new_status);
      $order->add_order_note($comment, $notify);
      $order->save();
    }

    // Log
    $this->write_log('sendOrder return: ' . json_encode($post_data));

    return $post_data;
  }

  /**
   * Atualiza o status de um pedido no Balcão Balcão.
   *
   * @param int $order_id
   * @return object
   */
  public function updateOrder($config, $tracking_code, $order_new_status)
  {
    // Prepara as informações para enviar para a API.
    $data = array(
      'status' => $order_new_status,
    );

    $post_data = $this->post(
      'order/status/' . $tracking_code,
      $data,
      'PATCH',
      array(
        'X-BB-ApiToken' => $config['token']
      )
    );

    // Log
    $this->write_log('updateOrder return: ' . json_encode($post_data));

    return $post_data;
  }

  /**
   * Força erro 408
   * @param string $message
   * @return void
   * @author Bruno Marsilio <bruno@ezoom.com.br>
   */
  private function _force_error($message)
  {
    $json = json_encode([
      'status_code' => 408,
      'message'     => $message,
    ]);

    // Log
    $this->write_log($json);

    return $json;
  }

  /**
   * Salva logs
   * @param string $message
   * @return void
   * @author Bruno Marsilio <bruno@ezoom.com.br>
   */
  private function write_log($message)
  {
    if ($this->config['debug'] == 'yes') {
      $filename = plugin_dir_path(__DIR__) . 'logs/balcaobalcao.log';

      $text = date('Y-m-d H:i:s') . ' ---- ' . $message . PHP_EOL . PHP_EOL;

      if ($handle = fopen($filename, 'a+')) {
        fwrite($handle, $text);
        fclose($handle);
      }
    }
  }
}
