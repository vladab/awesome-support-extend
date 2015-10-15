jQuery(function() {
    jQuery('#quick_search_ticket_input')
        .typeahead({
            name: 'search',
            remote: wp_typeahead.ajaxurl + '?action=wpdt_wpas_before_tickets_list&searchString=%QUERY',
            template: [
                '<p><a class="qr_voucher_url" rel="{{slug}}" href="{{url}}">{{value}}</a></p>',
            ].join(''),
            engine: Hogan
        }).bind('typeahead:selected', function (obj, ticket) {
            window.location.href = ticket.url;
        });;;
});