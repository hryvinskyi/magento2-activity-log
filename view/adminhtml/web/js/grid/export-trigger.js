/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A page button that runs the grid's own export, so the file follows the grid's filters, search and selection.
 */
define([
    'uiRegistry'
], function (registry) {
    'use strict';

    /**
     * @param {{exportButton: string, option: string}} config
     * @param {HTMLElement} button
     */
    return function (config, button) {
        button.addEventListener('click', function () {
            registry.get(config.exportButton, function (exporter) {
                exporter.checked(config.option);
                exporter.applyOption();
            });
        });
    };
});
