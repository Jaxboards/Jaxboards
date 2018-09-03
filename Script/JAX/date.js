function ordsuffix(a) {
  return (
    a
    + (Math.round(a / 10) === 1 ? 'th' : ['', 'st', 'nd', 'rd'][a % 10] || 'th')
  );
}

export function date(a) {
  const old = new Date();
  const now = new Date();
  let fmt;
  const yday = new Date();
  const months = [
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
  yday.setTime(yday - 1000 * 60 * 60 * 24);
  old.setTime(a * 1000); // setTime uses milliseconds, we'll be using UNIX Times as the argument
  const hours = `${old.getHours() % 12 || 12}`;
  const ampm = hours >= 12 ? 'pm' : 'am';
  const mins = `${old.getMinutes()}`.padStart(2, '0');
  const dstr = `${old.getDate()} ${old.getMonth()} ${old.getFullYear()}`;
  const delta = (now.getTime() - old.getTime()) / 1000;
  if (delta < 90) {
    fmt = 'a minute ago';
  } else if (delta < 3600) {
    fmt = `${Math.round(delta / 60)} minutes ago`;
  } else if (
    `${now.getDate()} ${now.getMonth()} ${now.getFullYear()}`
    === dstr
  ) {
    fmt = `Today @ ${hours}:${mins} ${ampm}`;
  } else if (
    `${yday.getDate()} ${yday.getMonth()} ${yday.getFullYear()}`
    === dstr
  ) {
    fmt = `Yesterday @ ${hours}:${mins} ${ampm}`;
  } else {
    fmt = `${months[old.getMonth()]} ${ordsuffix(old.getDate())}, ${old.getFullYear()} @ ${hours}:${mins} ${ampm}`;
  }
  return fmt;
}

export function smalldate(a) {
  const d = new Date();
  d.setTime(a * 1000);
  let hours = d.getHours();
  const ampm = hours >= 12 ? 'pm' : 'am';
  hours %= 12;
  hours = hours || 12;
  const minutes = `${d.getMinutes()}`.padStart(2, '0');
  const month = d.getMonth() + 1;
  const day = `${d.getDate()}`.padStart(2, '0');
  const year = d.getFullYear();
  return `${hours}:${minutes}${ampm}, ${month}/${day}/${year}`;
}
