jQuery(document).ready(function($) {
    $('#share-cart-btn').on('click', function(e) {
        e.preventDefault();
        $.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", { action: 'generate_share_link' }, function(response) {
            if (response.success) {
                var shareUrl = response.data.url;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(shareUrl).then(function() {
                        $('#share-cart-url').html('<p><?php echo esc_html__('Link copied to clipboard:', 'share-cart-for-woocommerce'); ?> ' + shareUrl + '</p>');
                        $('#share-cart-btn').hide();
                    });
                } else {
                    var tempInput = $('<input>');
                    $('body').append(tempInput);
                    tempInput.val(shareUrl).select();
                    document.execCommand("copy");
                    tempInput.remove();
                    $('#share-cart-url').html('<span><?php echo esc_html__('Link copied to clipboard - ', 'share-cart-for-woocommerce'); ?> ' + shareUrl + '</span>');
                    $('#share-cart-btn').hide();
                    $('#share-cart-url').css({
                        'margin-bottom': '10px',
                        'background': 'rgb(241 241 241)',
                        'padding': '0.6em 1em'
                    });
                }
            }
        });
    });
    // Reset the share link and show the button when the cart updates.
    $(document.body).on('updated_wc_div', function() {
        $('#share-cart-url').empty();
        $('#share-cart-btn').show();
    });
});