import Color from './color';

type LineupEntry = (
    el: Element,
) => undefined | [string, string | number, string | number];
/**
 * This class was written before CSS animations existed.
 * It should be replaced.
 */
export default class Animation {
    private readonly el: HTMLElement;

    private readonly delay: number;

    private steps: number;

    private interval?: number;

    private curLineup: number;

    private stepCount: number;

    private readonly lineup: LineupEntry[][];

    constructor(el: HTMLElement, steps = 30, delay = 20) {
        this.el = el;
        this.steps = steps;
        this.delay = delay;
        this.curLineup = 0;
        this.stepCount = 0;
        this.lineup = [[]];
    }

    play() {
        this.interval = setInterval(() => this.step(), this.delay);
        return this;
    }

    morph(from: number[], percent: number, to: number | number[]): number[];

    morph(from: number, percent: number, to: number): number;

    morph(from: number | number[], percent: number, to: number | number[]) {
        // Handles [R,G,B] values
        if (Array.isArray(from) && Array.isArray(to)) {
            return from.map((value: number, i): number =>
                Math.round(this.morph(value, percent, to[i])),
            );
        }
        if (Array.isArray(from) || Array.isArray(to)) {
            // unhandled case
            return 0;
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
                if (/color/i.test(keyFrame[0])) {
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
            t = /(\D*)(-?\d+)(\D*)/.exec(to);
            t.shift();
            fromParsed = Number.parseFloat(/-?\d+/.exec(from)?.[0] ?? '');
        }
        this.lineup.at(-1)?.push([what, fromParsed, t[1], t[0], t[2]]);
        return this;
    }

    andThen(
        what: string | ((el: HTMLElement) => undefined),
        from = undefined,
        to = undefined,
        steps = undefined,
    ) {
        this.lineup.push([]);
        if (steps) this.steps = steps;
        if (typeof what === 'function') {
            this.lineup.at(-1)?.push(what);
        } else if (from !== undefined && to !== undefined) {
            this.add(what, from, to);
        }
        return this;
    }
}

export function dehighlight(el: HTMLElement) {
    el.style.backgroundColor = '#FF0';

    // This is needed to force a repaint
    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
    el.offsetHeight;

    el.style.transition = 'background-color 1s';
    el.style.backgroundColor = '';

    setTimeout(() => {
        el.style.transition = '';
    }, 1000);
}
