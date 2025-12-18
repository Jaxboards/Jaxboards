import register, { Component } from '../JAX/component';
import { getComputedStyle, getCoordinates, insertBefore } from '../JAX/el';
import Event from '../JAX/event';
import { onImagesLoaded } from '../JAX/util';

const maxDimension = '999999px';

export default class ImageResizer extends Component<HTMLImageElement> {
    static hydrate(container: HTMLElement): void {
        const bbcodeImages =
            container.querySelectorAll<HTMLImageElement>('.bbcodeimg');

        onImagesLoaded(Array.from(bbcodeImages)).then(() =>
            register('ImageResizer', bbcodeImages, this),
        );
    }

    constructor(element: HTMLImageElement) {
        super(element);

        let p = 1;
        let p2 = 1;

        const { naturalWidth, naturalHeight } = element;
        let imageWidth = naturalWidth;
        let imageHeight = naturalHeight;
        const style = getComputedStyle(element);
        const maxWidth =
            Number.parseInt(style.width, 10) ||
            Number.parseInt(style.maxWidth, 10);
        const maxHeight =
            Number.parseInt(style.height, 10) ||
            Number.parseInt(style.maxHeight, 10);
        if (maxWidth && imageWidth > maxWidth) p = maxWidth / imageWidth;
        if (maxHeight && imageHeight > maxHeight) p2 = maxHeight / imageHeight;

        p = p && p2 ? Math.min(p, p2) : p2 || p;

        if (p < 1) {
            imageWidth *= p;
            imageHeight *= p;
            this.makeResizer(imageWidth, imageHeight);
        }
    }

    makeResizer(imageWidth: number, imageHeight: number) {
        const { element: img } = this;
        img.style.maxWidth = maxDimension;
        img.style.maxHeight = maxDimension;
        img.dataset.madeResized = 'true';
        const link = document.createElement('a');
        link.target = '_BLANK';
        link.href = img.src;
        link.style.display = 'block';
        link.style.overflow = 'hidden';
        link.style.width = `${imageWidth}px`;
        link.style.height = `${imageHeight}px`;
        link.dataset.nw = `${img.naturalWidth}`;
        link.dataset.nh = `${img.naturalHeight}`;
        link.onmousemove = (event) => {
            const o = getCoordinates(link);
            const e = Event(event);
            link.scrollLeft =
                ((e.pageX - o.x) / o.w) * (+(link.dataset.nw || 0) - o.w) || 0;
            link.scrollTop =
                ((e.pageY - o.y) / o.h) * (+(link.dataset.nh || 0) - o.h) || 0;
        };
        link.onmouseover = () => {
            img.style.width = `${link.dataset.nw}px`;
            img.style.height = `${link.dataset.nh}px`;
        };
        const reset = () => {
            if (link.scrollLeft) {
                link.scrollLeft = 0;
                link.scrollTop = 0;
            }
            img.style.width = `${imageWidth}px`;
            img.style.height = `${imageHeight}px`;
        };
        link.onmouseout = reset;
        reset();
        insertBefore(link, img);
        link.appendChild(img);
    }
}
