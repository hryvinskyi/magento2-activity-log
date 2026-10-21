/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Draws the summary cards: value, change against the previous period with a trend icon, and its tone. The icons
 * come from the page, one per direction ("up", "down", "flat").
 */
define([], function () {
    'use strict';

    /**
     * @param {string} tag
     * @param {string} className
     * @param {string} text
     * @returns {HTMLElement}
     */
    function element(tag, className, text) {
        var node = document.createElement(tag);

        node.className = className;
        node.textContent = text;

        return node;
    }

    /**
     * @param {string} direction
     * @param {string} text
     * @param {Object<string, Node>} trendIcons
     * @returns {HTMLElement}
     */
    function change(direction, text, trendIcons) {
        var node = element('strong', 'activity-card__change', ''),
            icon = Object.prototype.hasOwnProperty.call(trendIcons, direction) ? trendIcons[direction] : null;

        if (icon) {
            node.appendChild(icon.cloneNode(true));
        }
        node.appendChild(document.createTextNode(text));

        return node;
    }

    /**
     * @param {Object} card
     * @param {{vsPrevious: string, noData: string}} wording
     * @param {number} periodDays
     * @param {Object<string, Node>} trendIcons
     * @returns {HTMLElement}
     */
    function cardElement(card, wording, periodDays, trendIcons) {
        var node = element('div', 'activity-card', ''),
            value,
            delta;

        node.appendChild(element('div', 'activity-card__label', card.label));

        if (card.empty) {
            node.appendChild(element('div', 'activity-card__value activity-card__value--empty', '—'));
            node.appendChild(element('div', 'activity-card__delta', wording.noData));

            return node;
        }

        value = element('div', 'activity-card__value', card.value);
        value.appendChild(element('span', 'activity-card__unit', card.unit));
        node.appendChild(value);

        delta = element('div', 'activity-card__delta activity-card__delta--' + card.tone, '');
        delta.appendChild(change(card.direction, card.delta, trendIcons));
        delta.appendChild(element('span', 'activity-card__previous', wording.vsPrevious.replace('%1', periodDays)));
        node.appendChild(delta);

        return node;
    }

    /**
     * @param {HTMLElement} container
     * @param {Array<Object>} cards
     * @param {Object} wording
     * @param {number} periodDays
     * @param {Object<string, Node>} trendIcons
     */
    function render(container, cards, wording, periodDays, trendIcons) {
        container.replaceChildren.apply(container, cards.map(function (card) {
            return cardElement(card, wording, periodDays, trendIcons || {});
        }));
    }

    return Object.freeze({render: render});
});
