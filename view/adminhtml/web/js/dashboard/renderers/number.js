/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Draws number data: {cards: [{label, value, unit}]}.
 */
define([], function () {
    'use strict';

    /**
     * @param {HTMLElement} body
     * @param {{cards: Array<Object>}} data
     * @returns {{destroy: Function}}
     */
    function render(body, data) {
        var list = document.createElement('div');

        list.className = 'activity-widget-numbers';
        data.cards.forEach(function (card) {
            var item = document.createElement('div'),
                label = document.createElement('div'),
                value = document.createElement('div'),
                unit = document.createElement('span');

            item.className = 'activity-widget-numbers__item';
            label.className = 'activity-card__label';
            label.textContent = String(card.label);
            value.className = 'activity-card__value';
            value.textContent = String(card.value);
            unit.className = 'activity-card__unit';
            unit.textContent = card.unit ? String(card.unit) : '';
            value.appendChild(unit);
            item.appendChild(label);
            item.appendChild(value);
            list.appendChild(item);
        });

        body.appendChild(list);

        return {
            /** Removes the cards. */
            destroy: function () {
                list.remove();
            }
        };
    }

    /**
     * @param {Object} data
     * @returns {boolean}
     */
    function isEmpty(data) {
        return !Array.isArray(data.cards) || data.cards.length === 0;
    }

    return Object.freeze({render: render, isEmpty: isEmpty});
});
