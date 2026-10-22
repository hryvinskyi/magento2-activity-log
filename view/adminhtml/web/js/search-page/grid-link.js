/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search page's hold on the activity grid: it hands the grid the search to show, gives the grid's requests the
 * page's deadline, clears the grid's selection for a new search and runs the grid's export of a search. The grid
 * never searches on its own — the search travels as one parameter of its provider and of its export, the parameter
 * the grid's data provider reads.
 *
 * Showing a search starts the grid on its first page; the provider reloads once for both changes. The components
 * are looked up in the UI registry passed in, and used once they exist.
 */
define([], function () {
    'use strict';

    /**
     * @param {{get: function(string, function(Object))}} registry The UI component registry
     * @param {{provider: string, paging: string, selections: string, exportButton: string, exportOption: string,
     *     param: string}} names
     * @param {number} timeoutMs How long one of the grid's requests may take
     * @returns {{show: function(Object), clearSelection: function(), exportCsv: function(Object)}}
     */
    function create(registry, names, timeoutMs) {
        registry.get(names.provider, function (provider) {
            provider.set('requestConfig', {timeout: timeoutMs});
        });

        /**
         * The grid shows a search
         *
         * @param {Object} param The search as the grid parameter (search-state.gridParam)
         */
        function show(param) {
            registry.get(names.paging, function (paging) {
                paging.goFirst();
            });
            registry.get(names.provider, function (provider) {
                provider.set('params.' + names.param, param);
            });
        }

        /** No row stays chosen: the rows chosen were chosen for another search. */
        function clearSelection() {
            registry.get(names.selections, function (selections) {
                selections.deselectAll();
            });
        }

        /**
         * The grid's export runs for a search
         *
         * @param {Object} param The search as the grid parameter (search-state.gridParam)
         */
        function exportCsv(param) {
            registry.get(names.exportButton, function (exporter) {
                var additional = {};

                additional[names.param] = param;
                exporter.set('additionalParams', additional);
                exporter.checked(names.exportOption);
                exporter.applyOption();
            });
        }

        return Object.freeze({show: show, clearSelection: clearSelection, exportCsv: exportCsv});
    }

    return Object.freeze({create: create});
});
