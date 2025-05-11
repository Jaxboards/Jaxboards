import { getComputedStyle, getCoordinates, insertBefore } from './el';
import Event from './event';

const maxDimension = '999999px';

export function makeResizer(
    iw: number,
    nw: number,
    ih: number,
    nh: number,
    img: HTMLImageElement,
) {
    img.style.maxWidth = maxDimension;
    img.style.maxHeight = maxDimension;
    img.dataset.madeResized = 'true';
    const link = document.createElement('a');
    link.target = 'newwin';
    link.href = img.src;
    link.style.display = 'block';
    link.style.overflow = 'hidden';
    link.style.width = `${iw}px`;
    link.style.height = `${ih}px`;
    link.dataset.nw = `${nw}`;
    link.dataset.nh = `${nh}`;
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
        img.style.width = `${iw}px`;
        img.style.height = `${ih}px`;
    };
    link.onmouseout = reset;
    reset();
    insertBefore(link, img);
    link.appendChild(img);
}

export function imageResizer(imgs: NodeListOf<HTMLImageElement>) {
    if (!imgs || !imgs.length) {
        return;
    }
    Array.from(imgs)
        .filter((img) => !img.dataset.madeResized)
        .forEach((img: HTMLImageElement) => {
            let p = 1;
            let p2 = 1;
            const { naturalWidth, naturalHeight } = img;
            let imageWidth = naturalWidth;
            let imageHeight = naturalHeight;
            const style = getComputedStyle(img);
            const maxWidth =
                parseInt(style.width, 10) || parseInt(style.maxWidth, 10);
            const maxHeight =
                parseInt(style.height, 10) || parseInt(style.maxHeight, 10);
            if (maxWidth && imageWidth > maxWidth) p = maxWidth / imageWidth;
            if (maxHeight && imageHeight > maxHeight)
                p2 = maxHeight / imageHeight;
            p = p && p2 ? Math.min(p, p2) : p2 || p;
            if (p < 1) {
                imageWidth *= p;
                imageHeight *= p;
                makeResizer(
                    imageWidth,
                    naturalWidth,
                    imageHeight,
                    naturalHeight,
                    img,
                );
            }
        });
}
