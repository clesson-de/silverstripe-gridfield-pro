/* global $, window, document, jQuery */

(function ($) {
    $.entwine("ss", function ($) {
        $("div.gridfield-tiles>.gridfield-tiles--item").entwine({
            onmatch: function () {
                this._super();
            },
            onclick: function () {
                var url = this.data('href');
                console.log('url', url);
                document.location.href = url;
            },
        });
    });
})(jQuery);
