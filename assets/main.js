// Orbisius Live and Admin
jQuery(document).ready(function($) {
    $('#orbisius_messenger_contact_form_recipient').autocomplete({
        source: ajaxurl + '?action=orbisius_messenger_ajax_load_users',
        minLength: 2
    });
});
