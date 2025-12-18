import { fromUnixTimestamp } from '../JAX/date';

/**
 * Fetch the emoji for a given timestamp in the client's local timezone
 *
 * @param {string}  unixTimestamp The timestamp to fetch the emoji for
 *
 * @return {string} The closest clock emoji character
 */
export function emojiTime(unixTimestamp: number): string {
    const time = fromUnixTimestamp(unixTimestamp);

    return String.fromCodePoint(
        0xd83d,
        0xdd50 +
            // the emoji start at 1:00 and end at 12:00
            ((time.getHours() % 12 || 12) - 1) +
            // half hours are 12 characters above their base hour
            (time.getMinutes() > 29 ? 12 : 0),
    );
}

/**
 * Add idle clock to an element
 *
 * @param {HTMLAnchorElement} element Element to add the idle clock to
 */
export function addIdleClock(element: HTMLAnchorElement) {
    const lastActionClass = Array.from(element.classList).find((classItem) =>
        classItem.startsWith('lastAction'),
    );
    if (lastActionClass === undefined) {
        return;
    }
    element.prepend(
        emojiTime(
            Number.parseInt(lastActionClass.slice('lastAction'.length), 10),
        ),
    );
    element.classList.remove(lastActionClass);
}

export default class IdleClock {
    static selector(container: HTMLElement): void {
        container
            .querySelectorAll<HTMLAnchorElement>('.idle')
            .forEach((element) => addIdleClock(element));
    }
}
