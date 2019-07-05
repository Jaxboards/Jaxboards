// TODO: Use this file on the client side as well to keep date formatting consistent

const Handlebars = require('handlebars');

const SECOND = 1000;
const MINUTE = 60 * SECOND;
const HOUR = 60 * MINUTE;

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

const monthsShort = [
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
  'Dec'
];

module.exports = function dateHelper(dateString) {
  const priorDate = new Date(dateString);
  const now = new Date();
  const diff = now - priorDate;
  const yday = new Date();
  yday.setTime(yday - HOUR * 24);

  let humanReadableDate = dateString;

  if (diff <= MINUTE * 1.5) {
    humanReadableDate = 'a minute ago';
  } else if (diff < HOUR) {
    humanReadableDate = `${Math.round(diff / MINUTE)} minutes ago`;

    // Today
  } else if (asMDY(now) === asMDY(priorDate)) {
    humanReadableDate = `Today @ ${timeAsAMPM(priorDate)}`;

    // Yesterday
  } else if (asMDY(yday) === asMDY(priorDate)) {
    humanReadableDate = `Yesterday @ ${timeAsAMPM(priorDate)}`;
  } else {
    humanReadableDate = `${monthsShort[priorDate.getMonth()]} ${ordsuffix(
      priorDate.getDate()
    )}, ${priorDate.getFullYear()} @ ${timeAsAMPM(priorDate)}`;
  }

  return new Handlebars.SafeString(
    `<date class="autodate" data-date="${dateString}">${humanReadableDate}</date>`
  );
};
