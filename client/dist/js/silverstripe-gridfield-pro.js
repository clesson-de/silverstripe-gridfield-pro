/* global $, window, document, jQuery */

(function ($) {
    $.entwine("ss", function ($) {
        $("div.gridfield-tiles>.gridfield-tiles--item").entwine({
            onmatch: function () {
                this._super();
                const url = this.data('href');
                if (url) {
                    this.addClass('active');
                }
            },
            onclick: function () {
                const url = this.data('href');
                if (url) {
                    document.location.href = url;
                }
            },
        });

        $("div.gridfield-calendar").entwine({
            calendar: null,
            onmatch: function () {
                this._super();
                this.loadCalendarOptions(this.get(0), this.data('options-url'));
            },
            loadCalendarOptions: function(element, url){
                const self = this;
                $.getJSON(url, function (response) {
                    self.initCalendar(element, response.options);
                }).done(function (response) {
                    // console.log('response.data', response.data);
                }).fail(function (response) {
                    // console.log("error");
                }).always(function (response) {
                    // console.log("complete");
                });
            },
            initCalendar: function(element, options){
                const self = this;
                /*
                options.eventClick = function(info) {
                    self.eventClick(info);
                };*/
                options.customButtons.reloadButton.click = function(){
                    self.reload();
                };
                options.select = function(info) {
                    alert('selected ' + info.startStr + ' to ' + info.endStr);
                };
                calendar = new FullCalendar.Calendar(element, options);
                calendar.render();
            },
            reload: function(){
                const url = this.data('eventlist-url');
                $.getJSON(url, function (response) {
                    if (response.initialDate) {
                        calendar.gotoDate(response.initialDate);
                    }
                    response.items.forEach(function(item){
                        calendar.addEvent(item);
                    });
                }).done(function (response) {
                    // console.log('response.data', response.data);
                }).fail(function (response) {
                    // console.log("error");
                }).always(function (response) {
                    // console.log("complete");
                });
            },
            eventClick: function(info){
                const url = info.event.url;
                document.location.href = url;
            }
        });

    });
})(jQuery);
