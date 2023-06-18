let is_status_received = false;

function check_status(ajax_url) {
    function status_loop() {

        if (is_status_received) {
            return;
        }

        if (typeof(ajax_url) !== "undefined") {
            jQuery.getJSON(ajax_url, function (data) {
                jQuery.ajaxSetup({ cache: false });
                if (data.status == 'wait') {
                    setTimeout(status_loop, 2000);
                } else if (data.status == 'error') {
                    jQuery('.ca_loader,.payment_pending,.payment_details').hide(200);
                    jQuery('.payment_error').show();
                    is_status_received = true;
                } else if (data.status == 'pending') {
                    jQuery('.ca_loader,.payment_pending,.payment_details').hide(200);
                    jQuery('.payment_status_pending').show();
                    is_status_received = true;
                } else if (data.status == 'failed') {
                    jQuery('.ca_loader,.payment_pending,.payment_details').hide(200);
                    jQuery('.payment_status_failed').show();
                    is_status_received = true;
                } else if (data.status == 'completed') {
                    jQuery('.ca_loader,.payment_pending,.payment_details').hide(200);
                    jQuery('.payment_status_complete').show();
                    is_status_received = true;
                }
            });
        }
    }

    status_loop();
}
