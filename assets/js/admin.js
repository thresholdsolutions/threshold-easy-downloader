/* Threshold Easy Downloader: live filter on the Tools page. */
(function($){
    // Live filter across both tables.
    var $rows  = $('#threshold_ed_plugins tbody tr, #threshold_ed_themes tbody tr');
    var $count = $('#threshold_ed_count');
    var total  = $rows.length;

    function updateCount(visible){
        $count.text( visible === total ? '' : ( window.thresholdEdAdmin && thresholdEdAdmin.shown ? thresholdEdAdmin.shown.replace( '%1$d', visible ).replace( '%2$d', total ) : visible + ' / ' + total ) );
    }
    updateCount(total);

    $('#threshold_ed_filter').on('input', function(){
        var q = $(this).val().toLowerCase().trim();
        var visible = 0;
        $rows.each(function(){
            var hit = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
            $(this).toggle(hit);
            if (hit) visible++;
        });
        updateCount(visible);
    });
}(jQuery));
