/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Draws the summary cards: value, change against the previous period with an arrow, and its tone.
 */
define([], function () {
    'use strict';

    var ARROWS = Object.freeze({up: '▲', down: '▼', flat: '▬'});

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
     * @param {Object} card
     * @param {{vsPrevious: string, noData: string}} wording
     * @param {number} periodDays
     * @returns {HTMLElement}
     */
    function cardElement(card, wording, periodDays) {
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
        delta.appendChild(element('strong', '', (ARROWS[card.direction] || '') + ' ' + card.delta));
        delta.appendChild(element('span', 'activity-card__previous', wording.vsPrevious.replace('%1', periodDays)));
        node.appendChild(delta);

        return node;
    }

    /**
     * @param {HTMLElement} container
     * @param {Array<Object>} cards
     * @param {Object} wording
     * @param {number} periodDays
     */
    function render(container, cards, wording, periodDays) {
        container.replaceChildren.apply(container, cards.map(function (card) {
            return cardElement(card, wording, periodDays);
        }));
    }

    return Object.freeze({render: render});
});
