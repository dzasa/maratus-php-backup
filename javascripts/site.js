(function($) {
    $(document).ready(function() {
        //include navigation
        $("#navigation").load("include/nav.html", function() {
            $(".sb-right").load("include/right-side.html", function() {
                $(".sb-left").load("include/left-side.html", function() {
                    // Initiate Slidebars
                    $.slidebars();
                });
            });
        });
    });
})(jQuery);
