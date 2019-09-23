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
        $this->write_log('Quote requested: ' . $url);
        $api_data = $this->getAPIData($url);
        $json = json_decode($api_data);

        if(isset($json->status_code) && $json->status_code == 422 && $this->config['debug'] == 'yes') {
            $this->write_log(json_encode($json));
        }

        return $json;
    }

    public function post($uri = 'order', $data, $method = 'POST')
    {
        $query = http_build_query($data);
        $url = $this->api_endpoint . $uri;

        $this->write_log('Post data: ' . $url . '?' . $query);

        $api_data = $this->postAPIData($url, $query, $method);
        $json = json_decode($api_data);

        if(isset($json->status_code) && $json->status_code == 422 && $this->config['debug'] == 'yes') {
            $this->write_log(json_encode($json));
        }

        return $json;
    }

    private function _force_error($message)
    {
        $json = json_encode([
            'status_code' => 408,
            'message' => $message,
        ]);

        $this->write_log($json);

        return $json;
    }

    public function getAPIData($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $server_output = curl_exec($ch);

        if (!$server_output) {
            $server_output = $this->_force_error(__('Não foi possível conectar na API.'));
        }

        curl_close($ch);

        return $server_output;
    }

    public function postAPIData($url, $data, $method)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $server_output = curl_exec($ch);

        if (!$server_output) {

            $server_output = curl_exec($ch);

            $lang_error_reconexao = $this->language->get('error_reconexao');
            $server_output = $this->_force_error($lang_error_reconexao);
        }

        curl_close($ch);

        return $server_output;
    }

    /**
     * Verifica se contém a palavra balcabalcao no shipping_code do pedido.
     *
     * @param array $order_info
     * @return bool
     */
    public function checkIfIsABBOrder($order_info)
    {
        echo '<pre>';die(var_dump('ORDER_INFO', $order_info));
        return strpos($order_info['shipping_code'], 'balcaobalcao') !== FALSE;
    }

    /**
     * Verifica se deve enviar um pedido através do status configurado.
     * Certificando também que não exista o código de rastreio
     *
     * @param int $order_id
     * @param int $status_id
     * @return bool
     */
    public function checkIfMustSendOrder($order_id, $status_id, $situation = 'store')
    {
        $res = false;
        $send_status = $this->config->get('balcaobalcao_order_status_send');
        //Valida se o status está dentre os definidos
        if ($send_status && in_array($status_id, $send_status)) {

            //Valida se já foi integrado
            $query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "order_balcaobalcao WHERE tracking_code IS NOT NULL AND order_id = '" . (int) $order_id . "'");
            $res = ($query->row['total'] == 0);

            if ($situation == 'activate' && $query->row['total'] == 1)
                $res = true;
        }

        return $res;
    }

    /**
     * Verifica se deve cancelar um pedido através do status configurado.
     *
     * @param int $status_id
     * @return bool
     */
    public function checkIfMustCancelOrder($status_id)
    {
        $cancel_status = $this->config->get('balcaobalcao_order_status_cancel');
        return $cancel_status && in_array($status_id, $cancel_status);
    }

    /**
     * Ajusta os dados do shipping_method e totals antes de salvar no db
     * @param array $data
     * @return array
     * @author Fábio Neis <fabio@ezoom.com.br>
     */
    public function fixOrderData($data)
    {
        $data['shipping_extras'] = $this->session->data['shipping_method']['extras'];
        $extra = json_decode($data['shipping_extras'], true);
        if (isset($extra['name'])) {
            $data['shipping_method'] = trim($extra['name']);
            if (isset($data['totals'])) {
                foreach ($data['totals'] as $key => $total) {
                    if ($total['code'] == 'shipping') {
                        $data['totals'][$key]['title'] = trim($extra['name']);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Seta para notificado o histórico do pedido quando vem o retorno da api
     * Serve apenas para exibir o texto do histórico para o cliente mas não enviar o e-mail da loja
     *
     * @param int $order
     * @param int $order_status_id
     * @param string $comment
     * @return boolean
     * @author Fábio Neis <fabio@ezoom.com.br>
     */
    public function fixOrderHistory($order_id, $order_status_id, $comment = '')
    {
        $query = $this->db->query("
            UPDATE " . DB_PREFIX . "order_history SET notify = 1
            WHERE order_id = '" . (int) $order_id . "'
            AND order_status_id = '" . (int) $order_status_id . "'
            AND notify = 0
            AND comment = '" . $this->db->escape($comment) . "'
            ORDER BY date_added DESC
            LIMIT 1
        ");

        return $query;
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

            // Get store weight unit
            $woocommerce_weight_unit = get_option('woocommerce_weight_unit');

            // Get store dimension unit
            $woocommerce_dimension_unit = get_option('woocommerce_dimension_unit');

            // Converte para metros, medidas são unitárias
            $product_info['width']  = $balcaobalcao_shipping->helpers->getSizeInMeters($woocommerce_dimension_unit, $product_detail->get_width());
            $product_info['height'] = $balcaobalcao_shipping->helpers->getSizeInMeters($woocommerce_dimension_unit, $product_detail->get_height());
            $product_info['length'] = $balcaobalcao_shipping->helpers->getSizeInMeters($woocommerce_dimension_unit, $product_detail->get_length());

            // O peso do produto não é unitário como a dimensão, é multiplicado pela quantidade.
            $product_info['weight'] = $balcaobalcao_shipping->helpers->getWeightInKg($woocommerce_weight_unit, $product_detail->get_weight()) / $product->get_quantity();

            $products_data[$key] = [
                'name' => $product->get_name(),
                'quantity' => $product->get_quantity(),
                'price' => (float) $product->get_total(),
                'weight' => $product_info['weight'],
                'length' => $product_info['length'],
                'width' => $product_info['width'],
                'height' => $product_info['height'],
            ];
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

        // Prepara o nome do usuário
        if (trim($order_info['shipping_firstname'])) {
            $customer_name = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
        } else {
            $customer_name = $order_info['firstname'] . ' ' . $order_info['lastname'];
        }

        // Define o endereço
        $address = trim($order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2']);
        $address_number = $order->get_meta('_billing_number');
        $address_complement = trim($order_info['shipping_address_2']);
        $document = $order->get_meta('_billing_cpf');

        // Prepare Post Data
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

        // Post Data
        $post_data = $this->post('order/store', $data);

        // If success
        if ($post_data && isset($post_data->tracking_code)) {

            // Update tracking code with the returned code
            end($shipping_info)->update_meta_data(__('Código de Rastreio'), $post_data->tracking_code);

            // if tag is "already paid"
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

        // Return Post Data
        return $post_data;
    }

    /**
     * Atualiza o status de um pedido no Balcão Balcão.
     *
     * @param int $order_id
     * @return object
     */
    public function updateOrder($order_id, $status)
    {
        // Get BB Token
        $token = $this->config->get('balcaobalcao_token');

        // Prepare Data
        $data = array(
            'token' => $token,
            'order_id' => $order_id,
            'status_id' => $status,
        );

        // Post Data
        $post_data = $this->balcaobalcao->post('order/update-status', $data, 'PATCH');

        // Store Post Data At Session
        $this->session->data['balcaobalcao'] = $post_data;

        // Return Post Data
        return $post_data;
    }

    /**
     * Salva no banco de dados a forma de transporte escolhida
     * @param [int] $order_id
     * @param [json] $shipping_extras
     * @return void
     * @author Fábio Neis <fabio@ezoom.com.br>
     */
    public function addOrder($order_id, $shipping_extras)
    {
        $shipping_extras = json_decode($shipping_extras, true);
        $params = array_merge(
            array(
                'order_id'      => $order_id,
                'tracking_code' => 'NULL',
                'name'          => 'NULL',
                'address'       => 'NULL',
                'price'         => 0.00,
                'deadline'      => 'NULL',
                'token'         => 'NULL',
            ),
            $shipping_extras
        );

        //Organiza o array caso venha valores inválidos
        $params = $this->fixBeforeAddOrder($params);

        $this->model_shipping_balcaobalcao->addOrder($params);
    }

    /**
     * Altera valores vazios por nulos
     *
     * @param Array $params
     * @return Array
     * @author Fábio Neis <fabio@ezoom.com.br>
     */
    public function fixBeforeAddOrder($params)
    {
        $params = array_map(function ($item) {
            if (is_array($item))
                return $this->fixBeforeAddOrder($item);
            else
                return (trim($item) == '' || $item === NULL) ? 'NULL' : $item;
        }, $params);

        return $params;
    }

    /**
     * Salva logs
     * @param string $message
     * @return void
     * @author Bruno Marsilio <bruno@ezoom.com.br>
     */
    private function write_log($message)
    {
        if($this->config['debug'] == 'yes') {
            $filename = getcwd(). '/wp-content/plugins/balcaobalcao-shipping/logs/balcaobalcao.log';

            $text = date('Y-m-d H:i:s').' ---- '.$message.PHP_EOL.PHP_EOL;

            if (fopen($filename, "ab+") && is_writable($filename)) {
                if (!$handle = fopen($filename, 'a')) {
                    echo __("Não foi possível abrir o arquivo ($filename)");
                    exit;
                }

                if (fwrite($handle, $text) === FALSE) {
                    echo "Não foi possível escrever no arquivo ($filename)";
                    exit;
                }

                fclose($handle);
            } else {
                echo __("O arquivo $filename não pode ser alterado");
            }
        }
    }
}
