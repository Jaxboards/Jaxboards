import {
    getComputedStyle,
    getCoordinates,
    getHighestZIndex,
    isChildOf,
} from './el';

export type DragSession = {
    el: HTMLElement;
    mx: number;
    my: number;
    ex?: number;
    ey?: number;
    info?: DragSession;
    zIndex?: string;
    left?: number;
    top?: number;
    droptarget?: HTMLElement;
};
class Drag {
    sess: DragSession;

    boundEvents: {
        drag: (event: MouseEvent) => void;
        drop: (event: MouseEvent) => boolean;
    };

    droppables: HTMLElement[];

    noChild: boolean = false;

    bounds?: number[];

    onstart?: (data: object) => void;

    ondragover?: (sess: object) => void;

    ondrag?: (sess: object) => void;

    ondragout?: (sess: object) => void;

    ondrop?: (sess: object) => void;

    autoZIndex: boolean = true;

    constructor() {
        this.droppables = [];
        // This session line is only here to make typescript happy
        this.sess = { el: document.body, mx: 0, my: 0 };
        this.boundEvents = {
            drag: (event2: MouseEvent) => this.drag(event2),
            drop: () => this.drop(),
        };
    }

    start(event: MouseEvent, target?: HTMLElement, handle?: HTMLElement) {
        event.stopPropagation();
        const el = target || (event.target as HTMLElement);
        const style = getComputedStyle(el);
        const highz = getHighestZIndex();
        if (this.noChild && event.target !== (handle || el)) {
            return;
        }
        if (el.getAttribute('draggable') === 'false') {
            return;
        }
        this.sess = {
            el,
            mx: event.pageX,
            my: event.pageY,
            ex: parseInt(style?.left ?? '', 10) || 0,
            ey: parseInt(style?.top ?? '', 10) || 0,
            info: { el, mx: 0, my: 0 },
            zIndex: el.style.zIndex,
        };
        if (!this.sess.zIndex || Number(this.sess.zIndex) < highz - 1) {
            el.style.zIndex = `${highz}`;
        }
        this.onstart?.({
            ...this.sess,
            droptarget: this.testDrops(this.sess.mx, this.sess.my),
        });
        document.addEventListener('mousemove', this.boundEvents.drag);
        document.addEventListener('mouseup', this.boundEvents.drop);
        this.drag(event);
    }

    drag(event: MouseEvent) {
        event.stopPropagation();
        const s = this.sess.el.style;
        const tx = event.pageX;
        const ty = event.pageY;
        let mx = tx;
        let my = ty;
        let tmp2;
        let left = (this.sess.ex ?? 0) + mx - this.sess.mx;
        let top = (this.sess.ey ?? 0) + my - this.sess.my;
        const b = this.bounds;
        if (b) {
            if (left < b[0]) {
                mx = mx - left + b[0];
                [left] = b;
            } else if (left > b[0] + b[2]) left = b[0] + b[2];
            if (top < b[1]) {
                my = my - top + b[1];
                [top] = b;
            } else if (top > b[1] + b[3]) top = b[1] + b[3];
        }
        s.left = `${left}px`;
        s.top = `${top}px`;
        const sessInfo = this.sess.info;
        const dropTarget = sessInfo?.droptarget;
        const sess = {
            left,
            top,
            el: this.sess.el,
            mx,
            my,
            droptarget: this.testDrops(tx, ty),
        };
        this.sess.info = sess;
        this.ondrag?.(sess);
        if (sess.droptarget && dropTarget !== sess.droptarget) {
            this.ondragover?.(sess);
        }
        if (dropTarget && sess.droptarget !== dropTarget) {
            tmp2 = sess.droptarget;
            sess.droptarget = dropTarget;
            this.ondragout?.(sess);
            sess.droptarget = tmp2;
        }
    }

    boundingBox(x: number, y: number, w: number, h: number) {
        this.bounds = [x, y, w, h];
        return this;
    }

    drop() {
        if (this.boundEvents) {
            document.removeEventListener('mouseup', this.boundEvents.drop);
            document.removeEventListener('mousemove', this.boundEvents.drag);
        }
        this.ondrop?.(this.sess.info!);
        if (this.autoZIndex) {
            this.sess.el.style.zIndex = this.sess.zIndex ?? '';
        }
        return true;
    }

    testDrops(mouseX: number, mouseY: number): HTMLElement | undefined {
        const { droppables } = this;
        let z;
        let max = [9999, 9999];
        if (!droppables.length) {
            return undefined;
        }
        return droppables.findLast((droppable): HTMLElement | undefined => {
            if (
                droppable === this.sess.el ||
                isChildOf(droppable, this.sess.el)
            ) {
                return undefined;
            }
            z = getCoordinates(droppable);
            if (
                max[0] > z.w &&
                max[1] > z.h &&
                mouseX >= z.x &&
                mouseY >= z.y &&
                mouseX <= z.xw &&
                mouseY <= z.yh
            ) {
                max = [z.w, z.h];
                return droppable;
            }
            return undefined;
        });
    }

    drops(a: HTMLElement[]) {
        this.droppables = a;
        return this;
    }

    addDrops(a: HTMLElement[]) {
        if (!this.droppables) {
            return this.drops(a);
        }
        this.droppables = this.droppables.concat(a);
        return this;
    }

    addListener(listeners: object) {
        Object.assign(this, listeners);
        return this;
    }

    apply(el: HTMLElement, target?: HTMLElement) {
        if (Array.isArray(el)) {
            el.forEach((el2) => this.apply(el2));
            return this;
        }

        const style = getComputedStyle(el);
        const pos = style?.position;
        if (!pos || pos === 'static') {
            el.style.position = 'relative';
        }
        (target || el).onmousedown = target
            ? (e) => this.start(e, el, target)
            : (e) => this.start(e, el);
        return this;
    }

    autoZ() {
        this.autoZIndex = false;
        return this;
    }

    noChildActivation() {
        this.noChild = true;
        return this;
    }

    reset(el = this.sess.el) {
        el.style.top = '0';
        el.style.left = '0';
        return this;
    }
}

export default Drag;
