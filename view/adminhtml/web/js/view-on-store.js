define([
    'uiRegistry',
    'jquery'
], function (registry, $) {
    'use strict';

    var target = {
        /**
         * Open the given product URL in a new browser tab.
         *
         * @param {String} url
         */
        viewOnStore: function (url) {
            if (!url) {
                return;
            }

            var popup = window.open(url, '_blank');

            if (popup) {
                popup.focus();
            }
        },
        viewOnStoreBtn: function () {
            setTimeout(function () {
                $('.view-on-store-parent-btn.action-toggle').click()
            }, 1)
        }
    };

    registry.set('viewOnStore.target', target);
    registry.set('viewOnStore.main', target);

    return target;
});
