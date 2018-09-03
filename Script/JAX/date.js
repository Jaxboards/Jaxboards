const ordsuffix = function(a) {
  return (
    a +
    (Math.round(a / 10) == 1 ? "th" : ["", "st", "nd", "rd"][a % 10] || "th")
  );
};

export const date = function(a) {
  var old = new Date();
  var now = new Date();
  var fmt;
  var hours;
  var mins;
  var delta;
  var ampm;
  var yday = new Date();
  var dstr;
  var months = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec"
  ];
  yday.setTime(yday - 1000 * 60 * 60 * 24);
  old.setTime(a * 1000); // setTime uses milliseconds, we'll be using UNIX Times as the argument
  hours = old.getHours() % 12;
  hours = `${hours || 12}`;
  ampm = hours >= 12 ? "pm" : "am";
  mins = `${old.getMinutes()}`.padStart(2, "0");
  dstr = `${old.getDate()} ${old.getMonth()} ${old.getFullYear()}`;
  delta = (now.getTime() - old.getTime()) / 1000;
  if (delta < 90) {
    fmt = "a minute ago";
  } else if (delta < 3600) {
    fmt = `${Math.round(delta / 60)} minutes ago`;
  } else if (
    now.getDate() + " " + now.getMonth() + " " + now.getFullYear() ==
    dstr
  ) {
    fmt = `Today @ ${hours}:${mins} ${ampm}`;
  } else if (
    yday.getDate() + " " + yday.getMonth() + " " + yday.getFullYear() ==
    dstr
  ) {
    fmt = `Yesterday @ ${hours}:${mins} ${ampm}`;
  } else {
    fmt =
      `${months[old.getMonth()]} ${ordsuffix(old.getDate())}, ${old.getFullYear()} @ ${hours}:${mins} ${ampm}`;
  }
  return fmt;
};

export const smalldate = function(a) {
  var d = new Date();
  d.setTime(a * 1000);
  const hours = d.getHours();
  const ampm = hours >= 12 ? "pm" : "am";
  hours %= 12;
  hours = hours || 12;
  const minutes = `${d.getMinutes()}`.padStart(2, "0");
  const month = d.getMonth() + 1;
  const day = `${d.getDate()}`.padStart(2, "0");
  const year = d.getFullYear()
  return `${hours}:${minutes}${ampm}, ${month}/${day}/${year}`;
};