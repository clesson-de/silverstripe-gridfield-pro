import TomSelect from 'tom-select';
import { fetchOptions } from './remote-options.js';

const $ = window.jQuery;

$.entwine('ss', function ($) {
    $('select.gridfield-add-existing-select__input').entwine({
        onmatch: function () {
            this._super();
            const input = this[0];
            const container = input.closest('.gridfield-add-existing-select');
            const error = container.querySelector('[role="alert"]');
            let controller;
            const picker = new TomSelect(input, {
                valueField: 'id',
                labelField: 'label',
                searchField: [],
                maxItems: 1,
                create: false,
                preload: false,
                shouldLoad: query => Array.from(query.trim()).length >= 2,
                loadThrottle: 300,
                dropdownParent: 'body',
                dropdownClass: 'ts-dropdown gridfield-add-existing-select__dropdown',
                load(query, callback) {
                    controller?.abort();
                    controller = new AbortController();
                    error.hidden = true;
                    fetchOptions(input.dataset.searchUrl, query, controller.signal)
                        .then(options => {
                            if (query !== this.inputValue()) {
                                callback();
                                delete this.loadedSearches[query];
                                return;
                            }
                            this.clearOptions();
                            callback(options);
                        })
                        .catch(failure => {
                            callback();
                            delete this.loadedSearches[query];
                            if (failure.name !== 'AbortError' && query === this.inputValue()) {
                                error.textContent = container.dataset.error;
                                error.hidden = false;
                                console.error('[GridFieldAddExistingSelect]', failure);
                            }
                        });
                },
                onType() {
                    controller?.abort();
                    error.hidden = true;
                    this.clearOptions();
                    this.refreshOptions(false);
                },
                render: {
                    not_loading: (_, escape) => `<div class="no-results">${escape(container.dataset.minLength)}</div>`,
                    no_results: (_, escape) => `<div class="no-results">${escape(container.dataset.noResults)}</div>`,
                    loading: (_, escape) => `<div class="spinner">${escape(container.dataset.loading)}</div>`,
                },
                onChange(id) {
                    if (!id) {
                        return;
                    }
                    controller?.abort();
                    this.close();
                    this.lock();
                    error.hidden = true;
                    const grid = $(input).closest('.grid-field');
                    const rows = grid.find('tbody').children().clone(true);
                    const action = container.querySelector('.gridfield-add-existing-select__action');
                    grid.reload({
                        data: [
                            { name: action.name, value: action.value },
                            { name: input.name, value: id },
                        ],
                        error: xhr => {
                            grid.find('tbody').empty().append(rows);
                            error.textContent = container.dataset.error;
                            error.hidden = false;
                            this.clear(true);
                            this.unlock();
                            console.error('[GridFieldAddExistingSelect] Linking failed:', xhr.status);
                        },
                    });
                },
            });
            picker.control_input.classList.add('no-change-track');
            picker.on('destroy', () => controller?.abort());
        },
        onunmatch: function () {
            this[0].tomselect?.destroy();
            this._super();
        },
    });
});
