/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

/**
 * The few DOM features the search page's adapters use, enough to drive them from Node: elements with attributes,
 * dataset, children, text, hidden, value, inline style and focus; selectors made of a tag, .class and [attribute]
 * or [attribute="value"] parts; events that bubble, with AbortSignal-bound listeners; <template> content. Nothing here
 * is a browser; a test proves what the adapter asks of the page, the walkthrough proves the page.
 */

const SELECTOR = /^([a-z]*)((?:\.[a-z0-9_-]+)*)((?:\[[a-z-]+(?:="[^"]*")?\])*)$/i;
const ATTRIBUTE = /\[([a-z-]+)(?:="([^"]*)")?\]/gi;

const camel = (name) => name.replace(/-([a-z])/g, (all, letter) => letter.toUpperCase());

class FakeEvent {
    constructor(type, init = {}) {
        Object.assign(this, init);
        this.type = type;
        this.defaultPrevented = false;
        this.bubbles = init.bubbles !== false;
    }

    preventDefault() {
        this.defaultPrevented = true;
    }
}

class FakeElement {
    constructor(doc, tag) {
        this.ownerDocument = doc;
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.parentNode = null;
        this.attrs = {};
        this.dataset = {};
        this.listeners = [];
        this.hidden = false;
        this.disabled = false;
        this.value = '';
        this.checked = false;
        this.placeholder = '';
        this.type = '';
        this.max = '';
        this.text = '';
        this.className = '';
        this.id = '';
        this.title = '';
        this.style = {};
        const element = this;
        this.classList = {
            add: (name) => element.classList.toggle(name, true),
            remove: (name) => element.classList.toggle(name, false),
            contains: (name) => element.className.split(/\s+/).includes(name),
            toggle: (name, force) => {
                const names = element.className.split(/\s+/).filter(Boolean).filter((n) => n !== name);
                if (force) {
                    names.push(name);
                }
                element.className = names.join(' ');
            }
        };
        if (this.tagName === 'TEMPLATE') {
            this.content = new FakeElement(doc, 'fragment');
        }
    }

    get options() {
        return this.children.filter((child) => child.tagName === 'OPTION');
    }

    get textContent() {
        return this.text + this.children.map((child) => child.textContent).join('');
    }

    set textContent(value) {
        this.children.forEach((child) => {
            child.parentNode = null;
        });
        this.children = [];
        this.text = String(value);
    }

    setAttribute(name, value) {
        if (name.startsWith('data-')) {
            this.dataset[camel(name.slice(5))] = String(value);
        } else if (name === 'id') {
            this.id = String(value);
        } else {
            this.attrs[name] = String(value);
        }
    }

    getAttribute(name) {
        if (name.startsWith('data-')) {
            const value = this.dataset[camel(name.slice(5))];

            return value === undefined ? null : value;
        }
        if (name === 'id') {
            return this.id === '' ? null : this.id;
        }
        if (name === 'role' || name.startsWith('aria-') || name in this.attrs) {
            return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
        }

        return null;
    }

    removeAttribute(name) {
        delete this.attrs[name];
    }

    appendChild(child) {
        if (child.tagName === 'FRAGMENT') {
            child.children.slice().forEach((node) => this.appendChild(node));

            return child;
        }
        if (child.parentNode) {
            child.remove();
        }
        child.parentNode = this;
        this.children.push(child);

        return child;
    }

    replaceChildren(...nodes) {
        this.textContent = '';
        nodes.forEach((node) => this.appendChild(node));
    }

    remove() {
        if (this.parentNode) {
            this.parentNode.children = this.parentNode.children.filter((child) => child !== this);
            this.parentNode = null;
        }
    }

    cloneNode(deep) {
        const copy = new FakeElement(this.ownerDocument, this.tagName);

        Object.assign(copy.attrs, this.attrs);
        Object.assign(copy.dataset, this.dataset);
        copy.className = this.className;
        copy.text = this.text;
        copy.hidden = this.hidden;
        copy.type = this.type;
        if (deep) {
            this.children.forEach((child) => copy.appendChild(child.cloneNode(true)));
        }

        return copy;
    }

    matches(selector) {
        const parsed = SELECTOR.exec(selector);
        let match;

        if (parsed === null) {
            throw new Error('The DOM fake does not understand the selector ' + selector);
        }
        if (parsed[1] !== '' && parsed[1].toUpperCase() !== this.tagName) {
            return false;
        }
        if (parsed[2].split('.').filter(Boolean).some((name) => !this.classList.contains(name))) {
            return false;
        }
        ATTRIBUTE.lastIndex = 0;
        while ((match = ATTRIBUTE.exec(parsed[3])) !== null) {
            const actual = this.getAttribute(match[1]);

            if (actual === null || (match[2] !== undefined && actual !== match[2])) {
                return false;
            }
        }

        return true;
    }

    descendants() {
        return this.children.flatMap((child) => [child, ...child.descendants()]);
    }

    querySelectorAll(selector) {
        const all = this.descendants().filter((node) => node.matches(selector));

        return Object.assign(all, {forEach: Array.prototype.forEach});
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }

    closest(selector) {
        let node = this;

        while (node && node.tagName !== 'FRAGMENT') {
            if (node.matches(selector)) {
                return node;
            }
            node = node.parentNode;
        }

        return null;
    }

    contains(node) {
        let current = node;

        while (current) {
            if (current === this) {
                return true;
            }
            current = current.parentNode;
        }

        return false;
    }

    addEventListener(type, handler, options = {}) {
        const entry = {type, handler};

        if (options.signal) {
            if (options.signal.aborted) {
                return;
            }
            options.signal.addEventListener('abort', () => {
                this.listeners = this.listeners.filter((listener) => listener !== entry);
            });
        }
        this.listeners.push(entry);
    }

    dispatchEvent(event) {
        let node = this;

        event.target = event.target || this;
        while (node) {
            event.currentTarget = node;
            node.listeners.filter((listener) => listener.type === event.type)
                .forEach((listener) => listener.handler(event));
            node = event.bubbles ? node.parentNode : null;
        }
        if (event.bubbles && this.ownerDocument.body.contains(this)) {
            event.currentTarget = this.ownerDocument;
            this.ownerDocument.listeners.filter((listener) => listener.type === event.type)
                .forEach((listener) => listener.handler(event));
        }

        return !event.defaultPrevented;
    }

    focus() {
        const previous = this.ownerDocument.activeElement;

        if (previous === this) {
            return;
        }
        this.ownerDocument.activeElement = this;
        if (previous) {
            previous.dispatchEvent(new FakeEvent('focusout', {relatedTarget: this}));
        }
        this.dispatchEvent(new FakeEvent('focus', {bubbles: false}));
    }

    scrollIntoView() {
    }
}

class FakeDocument {
    constructor() {
        this.body = new FakeElement(this, 'body');
        this.focused = null;
        this.listeners = [];
    }

    /**
     * The element with the focus; as in a browser, the focus falls back to the body once that element leaves the page
     */
    get activeElement() {
        if (this.focused !== null && !this.body.contains(this.focused)) {
            return this.body;
        }

        return this.focused;
    }

    set activeElement(element) {
        this.focused = element;
    }

    createElement(tag) {
        return new FakeElement(this, tag);
    }

    getElementById(id) {
        return this.body.descendants().find((node) => node.id === id) || null;
    }

    querySelector(selector) {
        return this.body.matches(selector) ? this.body : this.body.querySelector(selector);
    }

    querySelectorAll(selector) {
        return this.body.querySelectorAll(selector);
    }

    addEventListener(type, handler, options) {
        FakeElement.prototype.addEventListener.call(this, type, handler, options);
    }
}

/**
 * An element tree from a compact description: el('div', {'data-role': 'bar'}, [el('input', {...})])
 *
 * @param {FakeDocument} doc
 * @returns {function(string, Object=, Array=): FakeElement}
 */
function builder(doc) {
    return (tag, attrs = {}, children = []) => {
        const element = doc.createElement(tag);

        Object.keys(attrs).forEach((name) => {
            const value = attrs[name];

            if (['value', 'type', 'hidden', 'className', 'text'].includes(name)) {
                element[name] = value;
            } else {
                element.setAttribute(name, value);
            }
        });
        children.forEach((child) => (element.tagName === 'TEMPLATE' ? element.content : element).appendChild(child));

        return element;
    };
}

/**
 * Dispatches a user's event on an element
 *
 * @param {FakeElement} target
 * @param {string} type
 * @param {Object=} init
 * @returns {FakeEvent}
 */
function fire(target, type, init = {}) {
    const event = new FakeEvent(type, init);

    target.dispatchEvent(event);

    return event;
}

module.exports = {FakeDocument, FakeEvent, builder, fire};
