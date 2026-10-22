/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity grid's data provider: it follows only the latest search. A reload drops the request before it, and an
 * answer to an earlier reload is never shown, so a slow answer to an older search cannot replace the rows of the
 * search shown now. The deadline of each request is the request config the page gives (its "timeout").
 */
define([
    'Magento_Ui/js/grid/provider',
    'Hryvinskyi_ActivityLog/js/model/latest-request'
], function (Provider, latestRequest) {
    'use strict';

    return Provider.extend({
        /**
         * @returns {Object} Chainable.
         */
        initialize: function () {
            this.requests = latestRequest.create();
            this.inFlight = null;

            return this._super();
        },

        /**
         * Reloads the rows for the current parameters; only the latest reload's answer is shown
         *
         * @param {Object=} options
         * @returns {Promise}
         */
        reload: function (options) {
            var isLatest = this.requests.start(),
                provider = this,
                request;

            if (this.inFlight !== null && typeof this.inFlight.abort === 'function') {
                this.inFlight.abort();
            }
            request = this.storage().getData(this.params, options);
            this.inFlight = request;
            this.trigger('reload');

            request
                .done(function (data) {
                    if (isLatest()) {
                        provider.inFlight = null;
                        provider.onReload(data);
                    }
                })
                .fail(function (xhr) {
                    if (isLatest()) {
                        provider.inFlight = null;
                        provider.onError(xhr);
                    }
                });

            return request;
        }
    });
});
