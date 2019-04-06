$(document).ready(function(){

    /* Init */
    var identifier = 0;
    var thisState;
    $('.tree-folder-title>.dropdown-toggle').each(function () {

        // Set element identifier
        $(this).data('uid', identifier);
        $(this).attr('data-uid', identifier);

        // Check for stored toggle state
        thisState = localStorage.getItem('freshrss-toggle-state-' + identifier);
        if (thisState) {
            if ($('.tree-folder-title>.dropdown-toggle[data-uid="' + identifier + '"]').length) {
                $(this).parent().next(".tree-folder-items").show(0, function () {
                    $(document.body).trigger("sticky_kit:recalc");
                });

                // Set new state icon
                $(this).children().each(function () {
                    
                    if (this.src.includes('down.svg')) {
                        this.src = this.src.replace('/icons/down.', '/icons/up.');
                        this.alt = '△';
                    }
                    
                });

            }
        }

        identifier++;

    });


    /* Listener */
    $('#aside_feed').on('click', '.tree-folder>.tree-folder-title>a.dropdown-toggle', function () {

        $(this).children().each(function () {

            // Get element identifier
            identifier = $(this).parent().data('uid');

            if (this.src.includes('up.svg')) {

                // Check for stored toggle state
                var thisState = localStorage.getItem('freshrss-toggle-state-' + identifier);
                if (thisState) {

                    localStorage.removeItem('freshrss-toggle-state-' + identifier);

                }

                // console.log('Close');

            } else {

                localStorage.setItem('freshrss-toggle-state-' + identifier, "open");

                // console.log('Open');
            }
        });

    });

});