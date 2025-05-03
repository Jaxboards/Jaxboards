import { getHighestZIndex } from './el';
import { date, smalldate } from './date';

// This file is just a dumping ground until I can find better homes for these
export function assign(a, b) {
    return Object.assign(a, b);
}

/**
 * Tries to call a function, if it exists.
 * @param  {Function} method
 * @param  {...any} args
 * @return {any}
 */
export function tryInvoke(method, ...args) {
    if (method && typeof method === 'function') {
        return method(...args);
    }
    return null;
}

export function onImagesLoaded(imgs, timeout = 2000) {
    return new Promise((resolve) => {
        const images = new Set();
        const imagesToWaitOn = Array.from(imgs).filter((img) => !img.complete);

        if (!imagesToWaitOn.length) {
            resolve();
            return;
        }

        function markImageLoaded() {
            images.delete(this.src);
            if (images.size === 0) {
                resolve();
            }
        }

        imagesToWaitOn.forEach((img) => {
            if (!images.has(img.src)) {
                images.add(img.src);
                img.addEventListener('error', markImageLoaded);
                img.addEventListener('load', markImageLoaded);
            }
        });

        if (timeout) {
            setTimeout(resolve, timeout);
        }
    });
}

export function updateDates() {
    const dates = document.querySelectorAll('.autodate');
    const dateTitles = Array.from(
        document.querySelectorAll('[data-timestamp]'),
    );
    if (!dates) {
        return;
    }
    dates.forEach((el) => {
        const timestamp = parseInt(el.title, 10);
        const parsed = el.classList.contains('smalldate')
            ? smalldate(timestamp)
            : date(timestamp);
        if (parsed !== el.innerHTML) {
            el.innerHTML = parsed;
        }
    });
    dateTitles.forEach((el) => {
        if (!el.title) {
            el.title = smalldate(el.dataset.timestamp);
        }
    });
}

export function toggleOverlay(show) {
    const dE = document.documentElement;
    let ol = document.getElementById('overlay');
    if (ol) {
        assign(ol.style, {
            zIndex: getHighestZIndex(),
            top: 0,
            height: `${dE.clientHeight}px`,
            width: `${dE.clientWidth}px`,
            display: show ? '' : 'none',
        });
    } else {
        if (!show) return;
        ol = document.createElement('div');
        ol.id = 'overlay';
        assign(ol.style, {
            height: `${dE.clientHeight}0px`,
            width: `${dE.clientWidth}0px`,
        });
        dE.appendChild(ol);
    }
}

/**
 * Run a callback function either when the DOM is loaded and ready,
 * or immediately if the document is already loaded.
 * @param {Function} callback
 */
export function onDOMReady(callback) {
    if (document.readyState === 'complete') {
        callback();
    } else {
        document.addEventListener('DOMContentLoaded', callback);
    }
}
