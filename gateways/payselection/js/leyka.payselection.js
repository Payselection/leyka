(function($){

    $(document).on('submit.leyka', 'form.leyka-pm-form,form.leyka-revo-form', function(e){

        function addError($errors_block, error_html) {

            $errors_block.html(error_html).show();
            $('html, body').animate({ // 35px is a height of the WP admin bar (just in case)
                scrollTop: $errors_block.offset().top - 35
            }, 250);

        }

        let $form = $(this),
            $errors = $('#leyka-submit-errors');

        // Selected PM don't belong to the Payselection gateway:

        let $pm_field = $form.find('input[name="leyka_payment_method"][value*="payselection-"]'),
            gateway_is_chosen = $pm_field.prop('type') === 'hidden' ?
                $pm_field.val().indexOf('payselection') >= 0 : !!$pm_field.prop('checked');

        if($pm_field.length <= 0 || !gateway_is_chosen) {
            return; /** @todo Add some error to the form! Or at least do some console.logging */
        }

        console.log('$pm_field.length = '+$pm_field.length);
                console.log('gateway_is_chosen = '+gateway_is_chosen);

        let $revo_redirect_step = $form.closest('.leyka-pf').find('.leyka-pf__redirect');
        if($revo_redirect_step.length) {
            $revo_redirect_step.addClass('leyka-pf__redirect--open');
        }

        if($form.data('submit-in-process')) {
            return;
        } else {
            $form.data('submit-in-process', 1);
        }

        // Donation form validation already passed in the main script (public.js)

        let is_recurring = $form.find('.leyka-recurring').prop('checked') ||
                           $form.find('.is-recurring-chosen').val() > 0; // For Revo template;

        const $_form = $form.clone(),
            currency = $('.section__fields.currencies a.active').data('currency');

        $_form.find(`.currency-tab:not(.currency-${currency})`).remove();

        let data_array = $_form.serializeArray(),
            data = {action: 'leyka_ajax_get_gateway_redirect_data'};

        for(let i = 0; i < data_array.length; i++) {
            data[data_array[i].name] = data_array[i].value;
        }

        console.log(data);

        e.preventDefault();

        $.ajax({
            type: 'post',
            url: leyka.ajaxurl,
            data: data,
            beforeSend: function(xhr){
                /** @todo Show some loader */
            }
        }).done(function(response){

            console.log(response);

            $form.data('submit-in-process', 0);

            response = $.parseJSON(response);
            if( !response || typeof response.status === 'undefined' ) {

                addError($errors, leyka.ajax_wrong_server_response);
                return false;

            } else if(response.status !== 0 && typeof response.message !== 'undefined') {

                addError($errors, response.message);
                return false;

            } else if( !response.site_id ) {
                /** @todo Remove this check when more common gateways settings check will be added in leyka-ajax.php:leyka_submit_donation(). */

                addError($errors, leyka.payselection_not_set_up);
                return false;

            }

            if(leyka.gtm_ga_eec_available) {

                window.dataLayer = window.dataLayer || [];

                dataLayer.push({
                    'event': 'eec.add',
                    'ecommerce': {
                        // 'currencyCode': response.currency, // For some reason it doesn't work
                        'add': {
                            'products': [{
                                'name': response.payment_title,
                                'id': response.donation_id,
                                'price': response.amount,
                                'quantity': 1
                            }]
                        }
                    }
                });

            }

            if (response.payselection_method == 'widget') {
                const widget = new pw.PayWidget();

                if(response.additional_fields && !$.isEmptyObject(response.additional_fields)) {
                    $.each(response.additional_fields, function(key, value){
                        data[key] = value;
                    });
                }

                // if(is_recurring) {
                //     data.cloudPayments = {recurrent: {interval: 'Month', period: 1}};
                // }

                widget.pay({
                    serviceId: response.site_id,
                    key: response.widget_key
                }, response.request, {
                    onSuccess: (res) => {
                        console.log("onSuccess from shop", res);
                        //window.location.href = response.success_page;
                        // $errors.html('').hide();
                    },
                    onError: (res) => {
                        console.log("onFail from shop", res);
                        //addError($errors, leyka.payselection_donation_failure_reasons[res] || res)
                        //window.location.href = response.failure_page;
                    },
                    onClose: () => {
                    }
                });
            }

            if (response.payselection_method == 'redirect') {
                if (response.payselection_redirect_url) {
                    window.location.href = response.payselection_redirect_url;
                }
            }

            console.log(response.request);
            
            if($revo_redirect_step.length) {
                $revo_redirect_step.removeClass('leyka-pf__redirect--open');
            }

            if($form.hasClass('leyka-revo-form')) {
                $form.closest('.leyka-pf').leykaForm('close');
            }

        });

    });

}(jQuery));