<?php  if( !defined('WPINC') ) die;

class Payselection_Donation_Helper {

    public function __construct() {

        require_once LEYKA_PLUGIN_DIR.'gateways/payselection/lib/Payselection_Merchant_Api.php';

        $gateway = leyka_get_gateway_by_id('payselection');
        if($gateway && $gateway->get_activation_status() === 'active') {
            add_action('leyka_donation_status_funded_to_refunded', [$this, 'status_watcher'], 10);
        }

    }

    public function status_watcher(Leyka_Donation_Base $donation) {
        $this->create_refund($donation);
    }

    public function create_refund(Leyka_Donation_Base $donation) {

        if(!$donation->payselection_transaction_id) {
            return;
        }

        $api = new \Payselection_Merchant_Api(
            leyka_options()->opt('payselection_site_id'),
            leyka_options()->opt('payselection_key'),
            leyka_options()->opt('payselection_host'),
            leyka_options()->opt('payselection_create_host')
        );

        $data = [
            'TransactionId' => $donation->payselection_transaction_id,
            'Amount' => number_format(floatval($donation->amount), 2, '.', ''),
            'Currency' => strtoupper($donation->currency),
            'WebhookUrl' => home_url('/leyka/service/payselection/process'),   
        ];

        // $data = [
        //     'OrderId' => implode('-',[$donation->id, leyka_options()->opt('payselection_site_id'), time()]),
        //     'RebillId' => $donation->payselection_recurring_id,
        //     'Amount' => number_format(floatval($donation->amount), 2, '.', ''),
        //     'Currency' => strtoupper($donation->currency),
        //     'PayOnlyFlag' => true,
        //     'WebhookUrl' => home_url('/leyka/service/payselection/process'),   
        // ];

        // if (leyka_options()->opt('payselection_receipt')) {
        //     $data['ReceiptData'] = [
        //         'timestamp' => date('d.m.Y H:i:s'),
        //         'external_id' => (string) $donation->id,
        //         'receipt' => [
        //             'client' => [
        //                 'name' => $donation->donor_name,
        //                 'email' => $donation->donor_email,
        //             ],
        //             'company' => [
        //                 'inn' => '',
        //                 'payment_address' => '',
        //             ],
        //             'items' => [
        //                 'name' => 'donation refund',
        //                 'price' => 0,
        //                 'quantity' => 1,
        //                 'sum' => 0,
        //                 'vat' => 'none'
        //             ],
        //             'payments' => [
        //                 'type' => 0,
        //                 'sum' => 0,
        //             ],
        //             'total' => 0,
        //         ],
        //     ];
        // }

        if (leyka_options()->opt('payselection_receipt')) {
            $data['ReceiptData'] = [
                'timestamp' => date('d.m.Y H:i:s'),
                // 'external_id' => (string) $donation->id,
                'external_id' => implode('-',[$donation->id, time()]),
                'receipt' => [
                    'client' => [
                        'name' => $donation->donor_name,
                        'email' => $donation->donor_email,
                    ],
                    'company' => [
                        'inn' => leyka_options()->opt('leyka_org_inn'),
                        'payment_address' => leyka_options()->opt('leyka_org_address'),
                    ],
                    'items' => [
                        [
                            'name' => __('Donation refund', 'leyka'),
                            'price' => number_format(floatval($donation->amount), 2, '.', ''),
                            'quantity' => 1,
                            'sum' => number_format(floatval($donation->amount), 2, '.', ''),
                            'payment_method' => 'full_prepayment',
                            'payment_object'=> 'commodity',
                            'vat' => [
                                'type' => 'none',
                            ]
                        ]
                    ],
                    'payments' => [
                        [
                            'type' => 1,
                            'sum' => number_format(floatval($donation->amount), 2, '.', ''),
                        ]
                    ],
                    'total' => number_format(floatval($donation->amount), 2, '.', ''),
                ],
            ];
        }

        $response = $api->refund($data);
        //$response = $api->rebill($data);

        $file = get_template_directory() . '/payselection-errors2.txt'; 
        $current = file_get_contents($file);
        if (is_wp_error($response)) {
            $current .= $response->get_error_message() 
            ."\n".$donation->payselection_transaction_id."\n"
            ."\n".number_format(floatval($donation->amount), 2, '.', '')."\n"
            ."\n".strtoupper($donation->currency)."\n"
            ."\n".json_encode($data['ReceiptData'])."\n";
        } else {
            $current .= $response ."\n";
        }
        
        $open = file_put_contents($file, $current);

    }

}

function payselection_gateway_helper_init() {
    new Payselection_Donation_Helper();
}
add_action('admin_init', 'payselection_gateway_helper_init');