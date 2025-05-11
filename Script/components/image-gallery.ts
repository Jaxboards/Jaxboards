import Component from '../classes/component';

export default class ImageGallery extends Component {
    index: number;

    images: NodeListOf<HTMLImageElement>;

    max: number;

    static get selector() {
        return '.image_gallery';
    }

    constructor(element: HTMLDivElement) {
        super(element);
        const controls = document.createElement('div');
        const next = document.createElement('button');
        const prev = document.createElement('button');
        this.index = 0;
        this.images = element.querySelectorAll('img');
        this.max = Math.max(this.images.length, 1);

        next.innerHTML = 'Next &raquo;';
        next.addEventListener('click', () => {
            this.showNext();
        });

        prev.innerHTML = 'Prev &laquo;';
        prev.addEventListener('click', () => {
            this.showPrev();
        });

        this.update();
        controls.appendChild(prev);
        controls.appendChild(document.createTextNode(' '));
        controls.appendChild(next);
        element.appendChild(controls);
    }

    showNext() {
        if (this.index < this.max - 1) {
            this.index += 1;
        }
        this.update();
    }

    showPrev() {
        if (this.index > 0) {
            this.index -= 1;
        }
        this.update();
    }

    update() {
        this.images.forEach((img, i) => {
            const container = img.dataset.madeResized
                ? (img.parentNode as HTMLElement)
                : img;

            if (!container) return;

            container.style.display = i !== this.index ? 'none' : 'block';
        });
    }
}
