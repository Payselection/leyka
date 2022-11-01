<?php

namespace Payselection\Donation;

use Payselection\Donation\Api;

class Webhook extends Api
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
            empty($headers['X-WEBHOOK-SIGNATURE'])
        ) {
            return new \WP_Error('payselection_donation_webhook_error', __('A call to your Payselection callbacks URL was made with a missing required parameter.', 'leyka'));
        }

        if (leyka_options()->opt('payselection_site_id') != $headers['X-SITE-ID'] ) {
            return new \WP_Error(
                'payselection_donation_webhook_error',
                sprintf(__('a call to your Payselection callback was called with wrong site id. Site id from request: %s, Site id from options: %s', 'leyka'), $headers['X-SITE-ID'], leyka_options()->opt('payselection_site_id'))
            );
        }
        
        // Check signature
        $signBody = $_SERVER['REQUEST_METHOD'] . PHP_EOL . home_url('/leyka/service/payselection/process') . PHP_EOL . leyka_options()->opt('payselection_site_id') . PHP_EOL . $request;
        $signCalculated = Api::getSignature($signBody, leyka_options()->opt('payselection_key'));

        if ($headers['X-WEBHOOK-SIGNATURE'] !== $signCalculated) {
            return new \WP_Error(
                'payselection_donation_webhook_error',
                sprintf(__('A call to your Payselection callback was called with wrong digital signature. It may mean that someone is trying to hack your payment website. Signature from request: %s, Signature calculated: %s', 'leyka'), $headers['X-WEBHOOK-SIGNATURE'], $signCalculated)
            );
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
