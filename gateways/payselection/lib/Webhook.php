<?php

namespace Payselection\Donation;

class Webhook
{
      /**
     * handle Webhook handler
     *
     * @return void
     */
    public static function verify_header_signature($request)
    {
        $headers = getallheaders();

        if (
            empty($request) ||
            empty($headers['X-SITE-ID']) ||
            leyka_options()->opt('payselection_site_id') != $headers['X-SITE-ID'] ||
            empty($headers['X-WEBHOOK-SIGNATURE'])
        ) {
            return new \WP_Error('leyka_webhook_error', __('Missing required parameter', 'leyka'));
        }
        
        // Check signature
        $signBody = $_SERVER['REQUEST_METHOD'] . PHP_EOL . home_url('/leyka/service/payselection/process') . PHP_EOL . leyka_options()->opt('payselection_site_id') . PHP_EOL . $request;

        if ($headers['X-WEBHOOK-SIGNATURE'] !== self::getSignature($signBody, leyka_options()->opt('payselection_key'))) {
            return new \WP_Error('leyka_webhook_error', __('Signature error', 'leyka'));
        }

        return true;
    }
    
    /**
     * handle Webhook handler
     *
     * @return void
     */
    public function handle()
    {
        $request = file_get_contents('php://input');
        $headers = getallheaders();

        if (
            empty($request) ||
            empty($headers['X-SITE-ID']) ||
            leyka_options()->opt('payselection_site_id') != $headers['X-SITE-ID'] ||
            empty($headers['X-WEBHOOK-SIGNATURE'])
        )
            wp_die('Not found', 'payselection', array('response' => 404));
        
        // Check signature
        $signBody = $_SERVER['REQUEST_METHOD'] . PHP_EOL . home_url('/leyka/service/payselection') . PHP_EOL . leyka_options()->opt('payselection_site_id') . PHP_EOL . $request;

        if ($headers['X-WEBHOOK-SIGNATURE'] !== self::getSignature($signBody, leyka_options()->opt('payselection_key')))
            wp_die('Signature error', 'payselection', array('response' => 403));

        $request = json_decode($request, true);

        if (!$request)
            wp_die('Can\'t decode JSON', 'payselection', array('response' => 403));
        
        $requestDonation = explode('-', $request['OrderId']);

        if (count($requestDonation) !== 3)
            wp_die('Donation id error', 'payselection', array('response' => 404));

        $donation_id = (int) $requestDonation[0];
        $donation = Leyka_Donations::get_instance()->get_donation($donation_id);

        //if (empty($donation)) {
            wp_die('Donation not found', 'payselection', array('response' => 404));
        //}

        // if ($request['Event'] === 'Fail' || $request['Event'] === 'Payment') {
        //     $donation->add_Donation_note(sprintf("Payselection Webhook:\nEvent: %s\nOrderId: %s\nTransaction: %s", $request['Event'], esc_sql($request['OrderId']), esc_sql($request['TransactionId'])));
        // }

        switch ($request['Event'])
        {
            case 'Payment':
                $donation->add_Donation_note(sprintf('Payment approved (ID: %s)', esc_sql($request['TransactionId'])));
                $donation->update_meta_data('TransactionId', esc_sql($request['TransactionId']));
                self::payment($donation, 'completed');
                break;

            case 'Fail':
                self::payment($donation, 'fail');
                break;

            case 'Block':
                $donation->update_meta_data('BlockTransactionId', esc_sql($request['TransactionId']));
                self::payment($donation, 'hold');
                break;

            case 'Refund':
                self::payment($donation, 'refund');
                break;

            case 'Cancel':
                self::payment($donation, 'cancel');
                break;

            default:
                wp_die('There is no handler for this event', 'payselection', array('response' => 404));
                break;
        }
    }
    
    /**
     * payment Set Donation status
     *
     * @param  mixed $donation
     * @param  mixed $status
     * @return void
     */
    private static function payment($donation, $status = 'completed')
    {
        if ('completed' == $donation->get_status() && $status !== 'refund') {
            wp_die('Ok', 'payselection', array('response' => 200));
        }

        switch ($status)
        {
            case 'completed':
                $donation->payment_complete();
                break;

            case 'hold':
                $donation->update_status('on-hold');
                break;

            case 'cancel':
            case 'refund':
                $donation->update_status('cancelled');
                break;

            default:
                $donation->update_status('pending');
                break;
        }        
        
        wp_die('Ok', 'payselection', array('response' => 200));
    }
}
