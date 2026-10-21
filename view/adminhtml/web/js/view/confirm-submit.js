/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Asks before a form is submitted; the wording comes from the template.
 */
define([
    'Magento_Ui/js/modal/confirm'
], function (confirm) {
    'use strict';

    /**
     * @param {{title: string, content: string}} config
     * @param {HTMLFormElement} form
     */
    return function (config, form) {
        var confirmed = false;

        form.addEventListener('submit', function (event) {
            if (confirmed) {
                return;
            }

            event.preventDefault();
            confirm({
                title: config.title,
                content: config.content,
                actions: {
                    /** Submits once the admin user agrees. */
                    confirm: function () {
                        confirmed = true;
                        form.submit();
                    }
                }
            });
        });
    };
});
