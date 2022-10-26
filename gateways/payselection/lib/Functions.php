<?php

namespace Payselection;

class Functions
{

    public static function get_method() {
        $method = leyka_options()->opt('payselection_method');
        return empty($method) ||  $method !== 'redirect' ? 'widget' : 'redirect';
    }

    /**
     * getRequestData Create payment data for Payselection
     *
     * @return void
     */
    public function getRequestData($options)
    {
        // Get plugin options
        $options = self::get_options();

        $successUrl = $this->get_checkout_order_received_url();
        $cancelUrl = is_user_logged_in() ? $this->get_checkout_order_received_url() : $this->get_cancel_order_url();

        // Redirect links
        $extraData = [
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook'),
            "SuccessUrl"    => $successUrl,
            "CancelUrl"     => $cancelUrl,
            "DeclineUrl"    => $cancelUrl,
            "FailUrl"       => $cancelUrl,
        ];

        $data = [
            "MetaData" => [
                "PaymentType" => !empty($options->type) ? $options->type : "Pay",
            ],
            "PaymentRequest" => [
                "OrderId" => implode("-",[$this->get_id(), $options->site_id, time()]),
                "Amount" => number_format($this->get_total(), 2, ".", ""),
                "Currency" => $this->get_currency(),
                "Description" => "Order payment #" . $this->get_id(),
                "PaymentMethod" => "Card",
                "RebillFlag" => !empty($options->rebill) ? !!$options->rebill : false,
                "ExtraData" => $extraData,
            ],
            "CustomerInfo" => [
                "Email" => $this->get_billing_email(),
                "Phone" => $this->get_billing_phone(),
                "Language" => !empty($options->language) ? $options->language : "en",
                "Address" => $this->get_billing_address_1(),
                "Town" => $this->get_billing_city(),
                // "Country" => $this->get_billing_country(),
                "ZIP" => $this->get_billing_postcode(),
                "FirstName" => $this->get_billing_first_name(),
                "LastName" => $this->get_billing_last_name(),
                "IP" => \WC_Geolocation::get_ip_address(),
            ],
        ];

        if ($options->receipt === 'yes') {
            $data['ReceiptData'] = $this->getReceiptData($options);
        }

        return $data;
    }
    
    /**
     * getReceiptData Create receipt data
     *
     * @param  mixed $options
     * @return void
     */
    public function getReceiptData(object $options)
    {
        $items = [];
        $cart = $this->get_items();

        foreach ($cart as $item_data) {
            $product = $item_data->get_product();
            $items[] = [
                'name'      => mb_substr($product->get_name(), 0, 120),
                'sum'       => number_format(floatval($item_data->get_total()), 2, '.', ''),
                'price'     => number_format($product->get_price(), 2, '.', ''),
                'quantity'  => $item_data->get_quantity(),
                'vat'       => [
                    'type'      => $options->company_vat,
                ]
            ];
        }
        
        if ($this->get_total_shipping()) {
			$items[] = [
                'name'      => __('Shipping', 'payselection'),
                'sum'       => number_format($this->get_total_shipping(), 2, '.', ''),
                'price'     => number_format($this->get_total_shipping(), 2, '.', ''),
                'quantity'  => 1,
                'vat'       => [
                    'type'      => $options->company_vat,
                ]
            ];
        }

        return [
            'timestamp' => date('d.m.Y H:i:s'),
            'external_id' => (string) $this->get_id(),
            'receipt' => [
                'client' => [
                    'email' => $this->get_billing_email(),
                ],
                'company' => [
                    'email' => $options->company_email,
                    'inn' => $options->company_inn,
                    'sno' => $options->company_tax_system,
                    'payment_address' => $options->company_address,
                ],
                'items' => $items,
            ],
            'payments' => [
                'type' => 1,
                'sum' => number_format($this->get_total(), 2, ".", ""),
            ],
            'total' => number_format($this->get_total(), 2, ".", ""),
        ];
    }
    
    /**
     * getChargeCancelData Create data for Charge or Cancel
     *
     * @return void
     */
    public function getChargeCancelData()
    {
        return [
            "TransactionId" => $this->get_meta('BlockTransactionId'),
            "Amount"        => number_format($this->get_total(), 2, ".", ""),
            "Currency"      => $this->get_currency(),
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook')
        ];
    }
}