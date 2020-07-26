function ordsuffix(a) {
  return (
    a +
    (Math.round(a / 10) === 1 ? 'th' : ['', 'st', 'nd', 'rd'][a % 10] || 'th')
  );
}

// returns 8:05pm
function timeAsAMPM(timedate) {
  const hours = timedate.getHours() || 12;
  const minutesPadded = `${timedate.getMinutes()}`.padStart(2, '0');
  return `${hours % 12 || 12}:${minutesPadded}${hours > 12 ? 'pm' : 'am'}`;
}

// Returns month/day/year
function asMDY(mdyDate) {
  return `${mdyDate.getMonth()}/${mdyDate.getDate()}/${mdyDate.getFullYear()}`;
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

export function date(gmtUnixTimestamp) {
  const localTimeNow = new Date();

  const yday = new Date();
  yday.setTime(yday - 1000 * 60 * 60 * 24);

  const serverAsLocalDate = new Date(0);
  serverAsLocalDate.setUTCSeconds(gmtUnixTimestamp);

  const deltaInSeconds = (localTimeNow - serverAsLocalDate) / 1000;

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
    serverAsLocalDate.getDate()
  )}, ${serverAsLocalDate.getFullYear()} @ ${timeAsAMPM(serverAsLocalDate)}`;
}

export function smalldate(serverDate) {
  const serverAsLocalDate = new Date(0);
  serverAsLocalDate.setUTCSeconds(serverDate);
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
