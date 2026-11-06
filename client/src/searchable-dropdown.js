const $ = window.jQuery;

$.entwine('ss', function ($) {
    $('.grid-field select.gridfield-searchable-dropdown').entwine({
        onmatch() {
            this._super();
            if (this.data('chosen')) {
                return;
            }
            this.chosen({
                disable_search_threshold: 0,
                search_contains: true,
                width: '100%',
            });
            this.data('chosen').search_field.addClass('no-change-track')
                .attr('aria-label', this.attr('aria-label')).on('keydown.gridfieldSearchable', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                });
        },
        onunmatch() {
            this.data('chosen')?.search_field.off('.gridfieldSearchable');
            this.chosen('destroy');
            this._super();
        },
    });
});
