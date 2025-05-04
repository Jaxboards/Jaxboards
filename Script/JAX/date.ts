function ordsuffix(a: number) {
    return (
        a +
        (Math.round(a / 10) === 1
            ? 'th'
            : ['', 'st', 'nd', 'rd'][a % 10] || 'th')
    );
}

// returns 8:05pm
function timeAsAMPM(timedate: Date) {
    const hours = timedate.getHours() || 12;
    const minutesPadded = `${timedate.getMinutes()}`.padStart(2, '0');
    return `${hours % 12 || 12}:${minutesPadded}${hours > 12 ? 'pm' : 'am'}`;
}

// Returns month/day/year
function asMDY(mdyDate: Date) {
    return `${mdyDate.getMonth()}/${mdyDate.getDate()}/${mdyDate.getFullYear()}`;
}

/**
 * Takes a Unix timestamp (from server) and produces a JS Date.
 * @param timestamp Unix timestamp in seconds
 * @returns {Date}
 */
function fromUnixTimestamp(timestamp: number) {
    return new Date(timestamp * 1000);
}

export const monthsShort = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];
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

export function date(gmtUnixTimestamp: number) {
    const localTimeNow = new Date();

    const yday = new Date();
    yday.setTime(+yday - 1000 * 60 * 60 * 24);

    const serverAsLocalDate = fromUnixTimestamp(gmtUnixTimestamp);

    const deltaInSeconds = (+localTimeNow - +serverAsLocalDate) / 1000;

    if (deltaInSeconds < 90) {
        return 'a minute ago';
    }

    if (deltaInSeconds < 3600) {
        return `${Math.round(deltaInSeconds / 60)} minutes ago`;
    }

    // Today
    if (asMDY(localTimeNow) === asMDY(serverAsLocalDate)) {
        return `Today @ ${timeAsAMPM(serverAsLocalDate)}`;
    }

    // Yesterday
    if (asMDY(yday) === asMDY(serverAsLocalDate)) {
        return `Yesterday @ ${timeAsAMPM(serverAsLocalDate)}`;
    }

    return `${monthsShort[serverAsLocalDate.getMonth()]} ${ordsuffix(
        serverAsLocalDate.getDate(),
    )}, ${serverAsLocalDate.getFullYear()} @ ${timeAsAMPM(serverAsLocalDate)}`;
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

    return String.fromCharCode(
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
        emojiTime(parseInt(lastActionClass.slice('lastAction'.length), 10)),
    );
    element.classList.remove(lastActionClass);
}
