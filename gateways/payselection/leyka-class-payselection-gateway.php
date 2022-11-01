<?php if( !defined('WPINC') ) die;
/**
 * Leyka_Payselection_Gateway class
 */

class Leyka_Payselection_Gateway extends Leyka_Gateway {

    protected static $_instance;

    protected $_method;

    protected function __construct() {

        parent::__construct();

        require_once LEYKA_PLUGIN_DIR.'gateways/payselection/lib/init.php';

        $this->_method = \Payselection\Donation\Functions::get_method();

    }

    protected function _set_attributes() {

        $this->_id = 'payselection';
        $this->_title = __('Payselection', 'leyka');

        $this->_description = apply_filters(
            'leyka_gateway_description',
            __('<a href="https://payselection.com/">Payselection</a> is a Designer IT-solutions for the e-commerce market.', 'leyka'),
            $this->_id
        );

        $this->_docs_link = '//leyka.te-st.ru/docs/podklyuchenie-payselection/';
        $this->_registration_link = 'https://merchant.payselection.com/login/';
        $this->_has_wizard = false;

        $this->_min_commission = 2.8;
        $this->_receiver_types = ['legal',];
        $this->_may_support_recurring = true;
    }

    protected function _set_options_defaults() {

        if($this->_options) {
            return;
        }

        $this->_options = [
            'payselection_method' => [
                'type' => 'select',
                'title' => __('Widget or Redirect', 'leyka'),
                'default' => 'widget',
                'list_entries' => [
                    'widget' => __('Widget', 'leyka'),
                    'redirect' => __('Redirect', 'leyka'),
                ],
            ],
            'payselection_type' => [
                'type' => 'select',
                'title' => __('Payment type', 'leyka'),
                'default' => 'Pay',
                'list_entries' => [
                    'Pay' => __('Pay', 'leyka'),
                    'Block' => __('Block', 'leyka'),
                ],
            ],
            'payselection_webhook' => [
                'type' => 'static_text',
                'title' => __('Webhook URL', 'leyka'),
                'value' => home_url('/leyka/service/payselection/process'),
            ],
            'payselection_host' => [
                'type' => 'text',
                'title' => __('API host', 'leyka'),
                'comment' => __('API hostname.', 'leyka'),
                'required' => true,
                'default' => 'https://gw.payselection.com',
            ],
            'payselection_create_host' => [
                'type' => 'text',
                'title' => __('Create Payment host', 'leyka'),
                'comment' => __('Leave blank if you dont know what you do.', 'leyka'),
                //'required' => true,
                'default' => 'https://webform.payselection.com',
            ],
            'payselection_site_id' => [
                'type' => 'text',
                'title' => __('Site ID', 'leyka'),
                'comment' => __('Your site ID on Payselection.', 'leyka'),
                'required' => true,
                'default' => '',
            ],
            'payselection_key' => [
                'type' => 'text',
                'title' => __('Secret Key', 'leyka'),
                'comment' => __('Your Key on Payselection.', 'leyka'),
                'required' => true,
                'default' => '',
            ],
            'payselection_widget_url' => [
                'type' => 'text',
                'title' => __('Widget URL', 'leyka'),
                'default' => 'https://widget.payselection.com/lib/pay-widget.js',
                'required' => true,
            ],
            'payselection_widget_key' => [
                'type' => 'text',
                'title' => __('Widget Key', 'leyka'),
                'comment' => __('Your Widget Key on Payselection.', 'leyka'),
                'required' => true,
                'default' => '',
            ],
            'payselection_widget_language' => [
                'type' => 'select',
                'title' => __('Widget language', 'leyka'),
                'default' => 'en',
                'list_entries' => [
                    'ru' => __('Russian', 'leyka'),
                    'en' => __('English', 'leyka'),
                ],
            ],
            'payselection_receipt' => [
                'type' => 'checkbox',
                'default' => false,
                'title' => __('Fiscalization', 'leyka'),
                'comment' => __('If this option is enabled order receipts will be created and sent to your customer and to the revenue service via Payselection.', 'leyka'),
                'short_format' => true,
            ],
        ];
    }

    public function is_setup_complete($pm_id = false) {
        return leyka_options()->opt('payselection_host')
            && leyka_options()->opt('payselection_site_id')
            && leyka_options()->opt('payselection_key');
    }

    protected function _initialize_pm_list() {
        if(empty($this->_payment_methods['card'])) {
            $this->_payment_methods['card'] = Leyka_Payselection_Card::get_instance();
        }
    }

    public function localize_js_strings(array $js_data) {
        return array_merge($js_data, [
            'ajax_wrong_server_response' => __('Error in server response. Please report to the website tech support.', 'leyka'),
            'payselection_not_set_up' => __('Error in Payselection settings. Please report to the website tech support.', 'leyka'),
            'payselection_donation_failure_reasons' => [
                'User has cancelled' => __('You cancelled the payment', 'leyka'),
            ],
        ]);
    }

    public function enqueue_gateway_scripts() {

        if(Leyka_Payselection_Card::get_instance()->active) {

            $leyka_main_js_handle = wp_script_is('leyka-public') ? 'leyka-public' : 'leyka-new-templates-public';
            $leyka_widget_js_handle = 'widget' === $this->_method ? $leyka_main_js_handle.' leyka-payselection-widget' : $leyka_main_js_handle;

            if ('widget' === $this->_method) {
                wp_enqueue_script('leyka-payselection-widget', leyka_options()->opt('payselection_widget_url'), [], false, true);
            }
            wp_enqueue_script(
                'leyka-payselection',
                LEYKA_PLUGIN_BASE_URL.'gateways/'.Leyka_Payselection_Gateway::get_instance()->id.'/js/leyka.payselection.js',
                ['jquery', $leyka_main_js_handle],
                LEYKA_VERSION.'.001',
                true
            );

        }

        add_filter('leyka_js_localized_strings', [$this, 'localize_js_strings']);

    }

    public function process_form($gateway_id, $pm_id, $donation_id, $form_data) {

        $donation = Leyka_Donations::get_instance()->get_donation($donation_id);

        if( !empty($form_data['leyka_recurring']) ) {
            $donation->payment_type = 'rebill';
        }
    }

    public function submission_redirect_url($current_url, $pm_id) {
        return ''; // Payselection use custom redirection url on payment via ajax
    }

    public function submission_form_data($form_data, $pm_id, $donation_id) {

        // $file = LEYKA_PLUGIN_DIR . 'lib/payselection-errors.txt'; 
        // $current = file_get_contents($file);
        // $current .= json_encode($form_data)."\n";
        // $open = file_put_contents($file, $current);

		if( !array_key_exists($pm_id, $this->_payment_methods) ) {
			return $form_data; // It's not our PM
        }

        if(is_wp_error($donation_id)) { /** @var WP_Error $donation_id */
            return ['status' => 1, 'message' => $donation_id->get_error_message()];
        } else if( !$donation_id ) {
            return ['status' => 1, 'message' => __('The donation was not created due to error.', 'leyka')];
        } else if( !leyka_options()->opt('payselection_site_id') ) {
            return [
                'status' => 1,
                'message' => __('Error in Payselection settings. Please report to the website tech support.', 'leyka')
            ];
        } 

        $campaign = new Leyka_Campaign($form_data['leyka_campaign_id']);
        $donation = Leyka_Donations::get_instance()->get_donation($donation_id);

        // if( !empty($form_data['leyka_recurring']) ) {
        //     $donation->payment_type = 'rebill';
        // }

        $currency = !empty($_POST['leyka_donation_currency']) ?
            strtoupper($_POST['leyka_donation_currency']) : strtoupper($this->get_supported_currencies()[0]);

        $response = [
            'payselection_method' => $this->_method,
            'site_id' => trim(leyka_options()->opt('payselection_site_id')),
            'widget_key' => trim(leyka_options()->opt('payselection_widget_key')),
            'donation_id' => $donation_id,
            'amount' => number_format(floatval($donation->amount), 2, '.', ''),
            'currency' => $currency,
            'payment_title' => $donation->payment_title,
            'success_page' => leyka_get_success_page_url(),
            'failure_page' => leyka_get_failure_page_url(),
        ];

        $extraData = [
            'WebhookUrl'    => home_url('/leyka/service/payselection/process'),
            'SuccessUrl'    => leyka_get_success_page_url(),
            'CancelUrl'     => $campaign->url,
            'DeclineUrl'    => $campaign->url,
            'FailUrl'       => leyka_get_failure_page_url(),
        ];

        $response['request'] = [
            'MetaData' => [
                'PaymentType' => !empty(leyka_options()->opt('payselection_type')) ? trim(leyka_options()->opt('payselection_type')) : 'Pay',
                //"Initiator" => "Widget"
            ],
            'PaymentRequest' => [
                'OrderId' => implode('-',[$donation_id, leyka_options()->opt('payselection_site_id'), time()]),
                'Amount' => number_format(floatval($donation->amount), 2, '.', ''),
                'Currency' => $currency,
                'Description' => leyka_get_donation_gateway_description($donation, 250),
                'PaymentMethod' => 'Card',
                'RebillFlag' => !empty($form_data['leyka_recurring']) ? true : false,
                'ExtraData' => $extraData,
            ],
            'CustomerInfo' => [
                'Email' => $donation->donor_email,
                'Language' => !empty(leyka_options()->opt('payselection_language')) ? leyka_options()->opt('payselection_language') : 'en',
                'Name' => $donation->donor_name,
            ],
        ];

        //if (leyka_options()->opt('payselection_receipt') === 'yes') {
            $response['request']['ReceiptData'] = [
                'timestamp' => date('d.m.Y H:i:s'),
                'external_id' => (string) $donation_id,
                'receipt' => [
                    'client' => [
                        'name' => $donation->donor_name,
                        'email' => $donation->donor_email,
                    ],
                    'company' => [
                        'inn' => '123',
                        'payment_address' => 'address',
                    ],
                    'items' => [
                        'name' => 'donation',
                        'price' => number_format(floatval($donation->amount), 2, '.', ''),
                        'quantity' => '1',
                        'sum' => number_format(floatval($donation->amount), 2, '.', ''),
                        'vat' => '2'
                    ],
                    'payments' => [
                        'type' => 1,
                        'sum' => number_format(floatval($donation->amount), 2, '.', ''),
                    ],
                    'total' => number_format(floatval($donation->amount), 2, '.', ''),
                ],
            ];
        //}

        if($donation->additional_fields) {
            $response['additional_fields'] = $donation->additional_fields;
        }

        $api = new \Payselection\Donation\Api();
        $response['payselection_redirect_url'] = $api->getPaymentLink($response['request']);

        if ('widget' === $this->_method) {
            $response['request']['MetaData']['Initiator']  = 'Widget';
        }

        return $response;

    }

    public function _handle_service_calls($call_type = '') {
        // Callback URLs are: some-website.org/leyka/service/payselection/process/
        // Request content should contain "Event" field.
        // Possible field values: Payment, Block, Fail, Refund, Cancel

        $data = file_get_contents('php://input');

        $file = LEYKA_PLUGIN_DIR . 'lib/payselection-errors.txt'; 
        $current = file_get_contents($file);
        $current .= $data."\n";
        $open = file_put_contents($file, $current);

        $check = \Payselection\Donation\Webhook::verify_header_signature($data);

        // $file = LEYKA_PLUGIN_DIR . 'lib/payselection-errors.txt'; 
        // $current = file_get_contents($file);
        // foreach (getallheaders() as $name => $value) {
        //     $current .= $name.' = '.$value.'  '.$check."\n";
        // }
        // $open = file_put_contents($file, $current);

        $data = json_decode($data, true);
        
        if(is_wp_error($check)) {

            if(leyka_options()->opt('notify_tech_support_on_failed_donations')) {

                $message = sprintf(__('This message has been sent because %s The details of the call are below:', 'leyka'), $check->get_error_message)."\n\r\n\r"
                .esc_html($check->get_error_message())."\n\r\n\r"
                ."POST:\n\r".print_r($_POST, true)."\n\r\n\r"
                ."GET:\n\r".print_r($_GET, true)."\n\r\n\r"
                ."SERVER:\n\r".print_r(apply_filters('leyka_notification_server_data', $_SERVER), true)."\n\r\n\r";

                wp_mail(
                    leyka_get_website_tech_support_email(),
                    __('Payselection callback error.', 'leyka'),
                    $message
                );

            }

            $donation->add_gateway_response($_REQUEST);
            $donation->status = 'failed';

            die();

        } elseif(empty($data['Event']) || !is_string($data['Event'])) {
            wp_die(__('Webhook error: Event field is not found or have incorrect value', 'leyka'));
        }

        $_POST['Currency'] = mb_strtolower($_POST['Currency']);
        if( !in_array($_POST['currency'], ['rub', 'usd', 'eur', 'kgs']) ) {

            if(leyka_options()->opt('notify_tech_support_on_failed_donations')) {

                $message = __("This message has been sent because a call to your Payselection callbacks URL was made with a currency parameter (POST['currency']) that Leyka is unknown of. The details of the call are below.", 'leyka')."\n\r\n\r";

                $message .= "POST:\n\r".print_r($_POST, true)."\n\r\n\r";
                $message .= "GET:\n\r".print_r($_GET, true)."\n\r\n\r";
                $message .= "SERVER:\n\r".print_r(apply_filters('leyka_notification_server_data', $_SERVER), true)."\n\r\n\r";

                wp_mail(
                    leyka_get_website_tech_support_email(),
                    __('Payselection gives unknown currency parameter!', 'leyka'),
                    $message
                );

            }

            die();

        }

        $donation_string = explode('-', $data['OrderId']);

        $donation = Leyka_Donations::get_instance()->get_donation((int)$donation_string[0]);

        if( !$donation ) {

            if(leyka_options()->opt('notify_tech_support_on_failed_donations')) {

                $message = __("This message has been sent because a call to your Payselection callbacks URL was made with a donation ID parameter that Leyka is unknown of. The details of the call are below.", 'leyka')."\n\r\n\r";

                $message .= "POST:\n\r".print_r($_POST, true)."\n\r\n\r";
                $message .= "GET:\n\r".print_r($_GET, true)."\n\r\n\r";
                $message .= "SERVER:\n\r".print_r(apply_filters('leyka_notification_server_data', $_SERVER), true)."\n\r\n\r";
                $message .= "Donation ID: ".$_POST['cs2']."\n\r\n\r";

                wp_mail(
                    leyka_get_website_tech_support_email(),
                    __('Payselection gives unknown donation ID parameter!', 'leyka'),
                    $message
                );

            }

            die();

        }

        switch ($data['Event'])
        {
            case 'Payment':
                $donation->status = 'refunded';
                break;

            case 'Fail':
                $donation->status = 'refunded';
                break;

            case 'Block':
                //$donation->update_meta_data('BlockTransactionId', esc_sql($data['TransactionId']));
                $donation->status = 'refunded';
                break;

            case 'Refund':
                $donation->status = 'refunded';
                break;

            case 'Cancel':
                $donation->status = 'refunded';
                break;

            default:
                wp_die('There is no handler for this event', 'payselection', array('response' => 404));
                break;
        }

    }

    public function cancel_recurring_subscription(Leyka_Donation_Base $donation) {

        if( !$donation->recurring_is_active ) {
            return true;
        }

        if($donation->type !== 'rebill') {
            return new WP_Error(
                'wrong_recurring_donation_to_cancel',
                __('Wrong donation given to cancel a recurring subscription.', 'leyka')
            );
        }

        if( !$donation->payselection_recurring_id ) {
            return new WP_Error('payselection_no_subscription_id', sprintf(__('<strong>Error:</strong> unknown Subscription ID for donation #%d. We cannot cancel the recurring subscription automatically.<br><br>Please, email abount this to the <a href="%s" target="_blank">website tech. support</a>.<br>We are very sorry for inconvenience.', 'leyka'), $donation->id, leyka_get_website_tech_support_email()));
        }

        $api = new \Payselection\Donation\Api();
        $response = $api->unsubscribe(['RebillId' => $donation->payselection_recurring_id]);
        if (is_wp_error($response)) {
            return new WP_Error(
                'payselection_error_subscription',
                sprintf(__('The recurring subsciption cancelling request returned unexpected result. We cannot cancel the recurring subscription automatically. Error: %s', 'leyka'), $response->get_error_message())
            );
        }

        $response = json_decode($response);
        if ($response['TransactionState'] === 'false') {
            return new WP_Error(
                'payselection_error_false_subscription',
                sprintf(__('The recurring subsciption cancelling request returned unexpected result. We cannot cancel the recurring subscription automatically. Error: %s', 'leyka'), $response['Error']['Description']())
            );
        }
        $donation->recurring_is_active = false;

        return true;

    }

    /**
     * It is possible for CP to call a callback several times for one donation.
     * This donation must be created only once and then updated. It can be identified with CP transaction id.
     *
     * @param $payselection_transaction_id integer
     * @return Leyka_Donation_Base
     */
    // protected function _get_donation_by_transaction_id($payselection_transaction_id) {

    //     $donation = Leyka_Donations::get_instance()->get([
    //         'meta' => [['key' => 'payselection_transaction_id', 'value' => $payselection_transaction_id,],],
    //         'get_single' => true,
    //     ]);

    //     if( !$donation ) {
    //         $donation = Leyka_Donations::get_instance()->add([
    //             'status' => 'submitted',
    //             'gateway_id' => 'cp',
    //             'payment_method_id' => 'card',
    //             'payselection_transaction_id' => $payselection_transaction_id,
    //             'force_insert' => true, // Turn off donation fields validation checks
    //         ], true);
    //     }

    //     return $donation;

    // }

    // public function get_init_recurring_donation($recurring) {

    //     if(is_a($recurring, 'Leyka_Donation_Base')) {
    //         $recurring = $recurring->payselection_recurring_id;
    //     }
    //     if( !$recurring ) {
    //         return false;
    //     }

    //     return Leyka_Donations::get_instance()->get([
    //         'recurring_only_init' => true,
    //         'get_single' => true,
    //         'meta' => [['key' => 'payselection_recurring_id', 'value' => $recurring,]],
    //         'orderby' => 'id',
    //         'order' => 'asc',
    //     ]);

    // }

    protected function _get_value_if_any($arr, $key, $val = false) {
        return empty($arr[$key]) ? '' : ($val ? : $arr[$key]);
    }

    public function get_gateway_response_formatted(Leyka_Donation_Base $donation) {

        if( !$donation->gateway_response ) { 
            return [];
        }

        $vars = maybe_unserialize($donation->gateway_response);
        if( !$vars || !is_array($vars) ) {
            return [];
        }

        $vars_final = [
            __('Transaction ID:', 'leyka') => $this->_get_value_if_any($vars, 'TransactionId'),
            __('Sum:', 'leyka') => $this->_get_value_if_any($vars, 'Amount'),
            __('Currency:', 'leyka') => $this->_get_value_if_any($vars, 'Currency'),
            __('Donor email:', 'leyka') => $this->_get_value_if_any($vars, 'Email'),
            __('Callback time:', 'leyka') => $this->_get_value_if_any($vars, 'DateTime'),
            __('Donation description:', 'leyka') => $this->_get_value_if_any($vars, 'Description'),
            __('Is test donation:', 'leyka') => $this->_get_value_if_any($vars, 'IsTest'),
            __('Invoice status:', 'leyka') => $this->_get_value_if_any($vars, 'Event'),
        ];

        if( !empty($vars['RemainingAmount']) ) {
            $vars_final[__('Remaining amount:', 'leyka')] = $vars['RemainingAmount'];
        }

        if( !empty($vars['ErrorMessage']) ) {
            $vars_final[__('Donation failure reason:', 'leyka')] = $vars['ErrorMessage'];
        }
        if( !empty($vars['RebillId']) ) {
            $vars_final[__('Recurrent subscription ID:', 'leyka')] = $this->_get_value_if_any($vars, 'RebillId');
        }

        return $vars_final;

    }

    public function display_donation_specific_data_fields($donation = false) {

        if($donation) { // Edit donation page displayed

            $donation = Leyka_Donations::get_instance()->get_donation($donation);?>

            <div class="leyka-ddata-string">

                <label><?php _e('Payselection transaction ID', 'leyka');?>:</label>

                <div class="leyka-ddata-field">

                    <?php if($donation->type === 'correction') {?>
                        <input type="text" id="payselection-transaction-id" name="payselection-transaction-id" placeholder="<?php _e('Enter Payselection transaction ID', 'leyka');?>" value="<?php echo $donation->payselection_transaction_id;?>">
                    <?php } else {?>
                        <span class="fake-input"><?php echo $donation->payselection_transaction_id;?></span>
                    <?php }?>
                </div>

            </div>


            <?php if($donation->type !== 'rebill') {
                return;
            }?>

            <div class="leyka-ddata-string">

                <label><?php _e('Payselection subscription ID', 'leyka');?>:</label>

                <div class="leyka-ddata-field">

                    <?php if($donation->type === 'correction') {?>
                        <input type="text" id="payselection-recurring-id" name="payselection-recurring-id" placeholder="<?php _e('Enter Payselection subscription ID', 'leyka');?>" value="<?php echo $donation->payselection_recurring_id;?>">
                    <?php } else {?>
                        <span class="fake-input"><?php echo $donation->payselection_recurring_id;?></span>
                    <?php }?>
                </div>

            </div>

            <?php $init_recurring_donation = $donation->init_recurring_donation;?>

            <div class="recurring-is-active-field">

                <label><?php _e('Recurring subscription is active', 'leyka');?>:</label>
                <div class="leyka-ddata-field">
                    <?php echo $init_recurring_donation->recurring_is_active ? __('yes', 'leyka') : __('no', 'leyka');

                    if( !$init_recurring_donation->recurring_is_active && $init_recurring_donation->recurring_cancel_date ) {
                    echo ' ('.sprintf(__('canceled on %s', 'leyka'), date(get_option('date_format').', '.get_option('time_format'), $init_recurring_donation->recurring_cancel_date)).')';
                    }?>
                </div>

            </div>

        <?php } else { // New donation page displayed ?>

            <label for="payselection-transaction-id"><?php _e('Payselection transaction ID', 'leyka');?>:</label>
            <div class="leyka-ddata-field">
                <input type="text" id="payselection-transaction-id" name="payselection-transaction-id" placeholder="<?php _e('Enter Payselection transaction ID', 'leyka');?>" value="">
            </div>

            <label for="payselection-recurring-id"><?php _e('Payselection subscription ID', 'leyka');?>:</label>
            <div class="leyka-ddata-field">
                <input type="text" id="payselection-recurring-id" name="payselection-recurring-id" placeholder="<?php _e('Enter Payselection subscription ID', 'leyka');?>" value="">
            </div>

        <?php }

    }

    public function get_specific_data_value($value, $field_name, Leyka_Donation_Base $donation) {

        switch($field_name) {
            case 'payselection_recurring_id':
                return $donation->get_meta('payselection_recurring_id');
            case 'payselection_transaction_id':
                return $donation->get_meta('payselection_transaction_id');
            default:
                return false;
        }

    }

    public function set_specific_data_value($field_name, $value, Leyka_Donation_Base $donation) {

        switch($field_name) {
            case 'payselection_recurring_id':
                return $donation->set_meta('payselection_recurring_id', $value);
            case 'payselection_transaction_id':
                return $donation->set_meta('payselection_transaction_id', $value);
            default:
                return false;
        }

    }

    public function save_donation_specific_data(Leyka_Donation_Base $donation) {

        if(isset($_POST['payselection-recurring-id']) && $donation->recurring_id != $_POST['payselection-recurring-id']) {
            $donation->payselection_recurring_id = $_POST['payselection-recurring-id'];
        }

        if(isset($_POST['payselection-transaction-id']) && $donation->transaction_id != $_POST['payselection-transaction-id']) {
            $donation->payselection_transaction_id = $_POST['payselection-transaction-id'];
        }

    }

    public function add_donation_specific_data($donation_id, array $params) {

        if( !empty($params['payselection_recurring_id']) ) {
            Leyka_Donations::get_instance()->set_donation_meta($donation_id, 'payselection_recurring_id', $params['payselection_recurring_id']);
        }

        if( !empty($params['payselection_transaction_id']) ) {
            Leyka_Donations::get_instance()->set_donation_meta($donation_id, 'payselection_transaction_id', $params['payselection_transaction_id']);
        }

    }

}

class Leyka_Payselection_Card extends Leyka_Payment_Method {

    protected static $_instance = null;

    public function _set_attributes() {

        $this->_id = 'card';
        $this->_gateway_id = 'payselection';
        $this->_category = 'bank_cards';

        $this->_description = apply_filters(
            'leyka_pm_description',
            __('<a href="//Payselection.ru/">Payselection</a> is a Designer IT-solutions for the e-commerce market. Every partner receives the most comprehensive set of key technical options allowing to create a customer-centric payment system on site or in mobile application. Partners are allowed to receive payments in roubles and in other world currencies.', 'leyka'),
            $this->_id,
            $this->_gateway_id,
            $this->_category
        );

        $this->_label_backend = __('Bank card', 'leyka');
        $this->_label = __('Bank card', 'leyka');

        $this->_icons = apply_filters('leyka_icons_'.$this->_gateway_id.'_'.$this->_id, [
            LEYKA_PLUGIN_BASE_URL.'img/pm-icons/card-visa.svg',
            LEYKA_PLUGIN_BASE_URL.'img/pm-icons/card-mastercard.svg',
            LEYKA_PLUGIN_BASE_URL.'img/pm-icons/card-maestro.svg',
            LEYKA_PLUGIN_BASE_URL.'img/pm-icons/card-mir.svg',
        ]);

        $this->_supported_currencies = ['rub', 'eur', 'usd', 'kgs'];
        $this->_default_currency = 'rub';

        $this->_processing_type = 'custom-process-submit-event';
        //$this->_ajax_without_form_submission = true;

    }

    public function has_recurring_support() {
        return 'passive';
    }

}

function leyka_add_gateway_payselection() { // Use named function to leave a possibility to remove/replace it on the hook
    leyka_add_gateway(Leyka_Payselection_Gateway::get_instance());
}
add_action('leyka_init_actions', 'leyka_add_gateway_payselection');
