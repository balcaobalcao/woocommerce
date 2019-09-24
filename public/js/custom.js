jQuery(document).ready(function() {

    // Apply masks
    jQuery('.postcode-mask').mask('99999-999');
    jQuery('.number-mask').mask('#.99', {
        reverse: true
    });
});
