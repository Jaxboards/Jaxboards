import { date, smalldate } from './date';

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

export function onImagesLoaded(imgs: Array<HTMLImageElement>, timeout = 2000) {
    return new Promise<void>((resolve) => {
        const images = new Set();
        const imagesToWaitOn = Array.from(imgs).filter((img) => !img.complete);

        if (!imagesToWaitOn.length) {
            resolve();
            return;
        }

        function markImageLoaded(this: HTMLImageElement) {
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
    const dateTitles: HTMLElement[] = Array.from(
        document.querySelectorAll('[data-timestamp]'),
    );
    if (!dates) {
        return;
    }
    dates.forEach((el) => {
        const timestamp = parseInt(el.getAttribute('title') ?? '', 10);
        const parsed = el.classList.contains('smalldate')
            ? smalldate(timestamp)
            : date(timestamp);
        if (parsed !== el.innerHTML) {
            el.innerHTML = parsed;
        }
    });
    dateTitles.forEach((el) => {
        if (!el.title) {
            el.title = smalldate(parseInt(el.dataset.timestamp ?? ''));
        }
    });
}

/**
 * Run a callback function either when the DOM is loaded and ready,
 * or immediately if the document is already loaded.
 * @param {Function} callback
 */
export function onDOMReady(callback: () => void) {
    if (document.readyState === 'complete') {
        callback();
    } else {
        document.addEventListener('DOMContentLoaded', callback);
    }
}

/**
 * Check if client supports emoji
 *
 * @return {boolean}
 */
export function supportsEmoji(): boolean {
    // validate if a two-code point emoji width matches a single byte emoji
    // width
    const widths = [
        String.fromCodePoint(0x1f1fa, 0x1f1f8),
        String.fromCodePoint(0x1f354),
    ].map((character: string): number => {
        const element = document.body.appendChild(
            document.createElement('span'),
        );
        element.appendChild(document.createTextNode(character));
        const width = element.offsetWidth;
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }

        return width;
    });

    return widths[0] === widths[1];
}
