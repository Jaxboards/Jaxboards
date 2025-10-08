import Color from './color';
import { getComputedStyle } from './el';

/**
 * This class was written before CSS animations existed.
 * It should be replaced.
 */
class Animation {
    private el: HTMLElement;

    private delay: number;

    private steps: number;

    private interval?: number;

    private curLineup: number;

    private stepCount: number;

    private loop: number;

    private lineup: Array<
        Array<
            (
                el: HTMLElement,
            ) => void | [string, string | number, string | number]
        >
    >;

    constructor(el: HTMLElement, steps = 30, delay = 20, loop = 0) {
        this.el = el;
        this.steps = steps;
        this.delay = delay;
        this.curLineup = 0;
        this.stepCount = 0;
        this.loop = loop;
        this.lineup = [[]];
    }

    play() {
        this.interval = setInterval(() => this.step(), this.delay);
        return this;
    }

    morph(from, percent, to) {
        if (Array.isArray(from) && from.length === to.length) {
            return from.map((value, i) =>
                Math.round(this.morph(value, percent, to[i])),
            );
        }
        return (to - from) * percent + from;
    }

    step() {
        const curL = this.lineup[this.curLineup];
        this.stepCount += 1;
        let sc = this.stepCount;
        if (typeof curL[0] === 'function') {
            curL[0](this.el);
            sc = this.steps;
        } else {
            curL.forEach((keyFrame) => {
                let toValue = this.morph(
                    keyFrame[1],
                    sc / this.steps,
                    keyFrame[2],
                );
                if (keyFrame[0].match(/color/i)) {
                    toValue = `#${new Color(toValue).toHex()}`;
                } else if (keyFrame[0] !== 'opacity')
                    toValue = Math.round(toValue);
                this.el.style[keyFrame[0]] =
                    keyFrame[3] + toValue + keyFrame[4];
            });
        }
        if (sc === this.steps) {
            if (this.lineup.length - 1 > this.curLineup) {
                this.stepCount = 0;
                this.curLineup += 1;
            } else if (this.loop === 1) {
                this.stepCount = 0;
                this.curLineup = 0;
            } else clearInterval(this.interval);
        }
    }

    add(what: string, from: string, to: string): this {
        let t = ['', '', ''];
        let fromParsed;
        if (what.match(/color/i)) {
            fromParsed = new Color(from).toRGB();
            t[1] = new Color(to).toRGB();
        } else {
            t = to.match(/(\D*)(-?\d+)(\D*)/)!;
            t.shift();
            fromParsed = Number.parseFloat(from.match(/-?\d+/)?.[0] ?? '');
        }
        this.lineup[this.lineup.length - 1].push([
            what,
            fromParsed,
            t[1],
            t[0],
            t[2],
        ]);
        return this;
    }

    dehighlight(): this {
        this.el.style.backgroundColor = '';
        const bg = getComputedStyle(this.el).backgroundColor;
        this.el.classList.add('highlight');
        let bg2 = getComputedStyle(this.el).backgroundColor;

        if (bg2 === bg) bg2 = 'FF0'; // yellow
        this.el.classList.add('highlight');

        if (!bg2 || !bg) return this;

        return this.add('backgroundColor', bg2, bg).then(() => {
            this.el.style.backgroundColor = bg;
        });
    }

    andThen(
        what: string | ((el: HTMLElement) => void),
        from = undefined,
        to = undefined,
        steps = undefined,
    ) {
        this.lineup.push([]);
        if (steps) this.steps = steps;
        if (typeof what === 'function') {
            this.lineup[this.lineup.length - 1].push(what);
        } else if (from !== undefined && to !== undefined) {
            this.add(what, from, to);
        }
        return this;
    }
}

export default Animation;
