// returns 8:05pm
function timeAsAMPM(timedate: Date) {
    const hours = timedate.getHours() || 12;
    const minutesPadded = `${timedate.getMinutes()}`.padStart(2, '0');
    return `${hours % 12 || 12}:${minutesPadded}${hours > 12 ? 'pm' : 'am'}`;
}

/**
 * Takes a Unix timestamp (from server) and produces a JS Date.
 * @param timestamp Unix timestamp in seconds
 * @returns {Date}
 */
function fromUnixTimestamp(timestamp: number) {
    return new Date(timestamp * 1000);
}

export const daysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function ucfirst(str: string) {
    return str[0].toUpperCase() + str.slice(1);
}

export function date(gmtUnixTimestamp: number) {
    const localTimeNow = new Date();

    const relative = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
        style: 'long',
    });

    const yesterday = new Date();
    yesterday.setTime(+yesterday - 1000 * 60 * 60 * 24);
    yesterday.setHours(0);
    yesterday.setMinutes(0);
    yesterday.setSeconds(0);

    const serverAsLocalDate = fromUnixTimestamp(gmtUnixTimestamp);

    const deltaInSeconds = Math.round(
        (+serverAsLocalDate - +localTimeNow) / 1000,
    );

    if (deltaInSeconds > -60) {
        return relative.format(deltaInSeconds, 'second');
    }

    if (deltaInSeconds > -3600) {
        return relative.format(Math.round(deltaInSeconds / 60), 'minute');
    }

    // Yesterday + Today
    if (serverAsLocalDate > yesterday) {
        const today = new Date();
        today.setHours(0);
        today.setMinutes(0);
        today.setSeconds(0);

        return `${ucfirst(
            relative.format(serverAsLocalDate > today ? 0 : -1, 'day'),
        )} @ ${timeAsAMPM(serverAsLocalDate)}`;
    }

    return Intl.DateTimeFormat(undefined, {
        month: 'short',
        year: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        hour12: true,
        minute: 'numeric',
    }).format(serverAsLocalDate);
}

export function smalldate(gmtUnixTimestamp: number) {
    const serverAsLocalDate = fromUnixTimestamp(gmtUnixTimestamp);

    let hours = serverAsLocalDate.getHours();
    const ampm = hours >= 12 ? 'pm' : 'am';
    hours %= 12;
    hours = hours || 12;
    const minutes = `${serverAsLocalDate.getMinutes()}`.padStart(2, '0');
    const month = serverAsLocalDate.getMonth() + 1;
    const day = `${serverAsLocalDate.getDate()}`.padStart(2, '0');
    const year = serverAsLocalDate.getFullYear();
    return `${hours}:${minutes}${ampm}, ${month}/${day}/${year}`;
}

/**
 * Fetch the emoji for a given timestamp in the client's local timezone
 *
 * @param {string}  unixTimestamp The timestamp to fetch the emoji for
 *
 * @return {string} The closest clock emoji character
 */
export function emojiTime(unixTimestamp: number) {
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
