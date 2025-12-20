import register, { Component } from '../JAX/component';
import { getComputedStyle, getCoordinates, insertBefore } from '../JAX/el';
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
        const style = getComputedStyle(element);
        const maxWidth =
            Number.parseInt(style.width, 10) ||
            Number.parseInt(style.maxWidth, 10);
        const maxHeight =
            Number.parseInt(style.height, 10) ||
            Number.parseInt(style.maxHeight, 10);
        if (maxWidth && naturalWidth > maxWidth) p = maxWidth / naturalHeight;
        if (maxHeight && naturalHeight > maxHeight)
            p2 = maxHeight / naturalHeight;

        p = p && p2 ? Math.min(p, p2) : p2 || p;

        if (p < 1) {
            this.makeResizer(naturalWidth * p, naturalHeight * p);
        }
    }

    makeResizer(imageWidth: number, imageHeight: number) {
        const { element: img } = this;
        img.style.maxWidth = maxDimension;
        img.style.maxHeight = maxDimension;
        img.dataset.madeResized = 'true';
        const link = Object.assign(document.createElement('a'), {
            target: '_blank',
            href: img.src,
        });
        Object.assign(link.style, {
            display: 'block',
            overflow: 'hidden',
            width: `${imageWidth}px`,
            height: `${imageHeight}px`,
        });
        link.dataset.nw = `${img.naturalWidth}`;
        link.dataset.nh = `${img.naturalHeight}`;
        link.addEventListener('mousemove', (event) => {
            const { x, y, w, h } = getCoordinates(link);
            link.scrollLeft =
                ((event.pageX - x) / w) * (+(link.dataset.nw || 0) - w) || 0;
            link.scrollTop =
                ((event.pageY - y) / h) * (+(link.dataset.nh || 0) - h) || 0;
        });
        link.addEventListener('mouseover', () => {
            img.style.width = `${link.dataset.nw}px`;
            img.style.height = `${link.dataset.nh}px`;
        });
        const reset = () => {
            if (link.scrollLeft) {
                link.scrollLeft = 0;
                link.scrollTop = 0;
            }
            img.style.width = `${imageWidth}px`;
            img.style.height = `${imageHeight}px`;
        };
        link.addEventListener('mouseout', reset);
        reset();
        insertBefore(link, img);
        link.appendChild(img);
    }
}
