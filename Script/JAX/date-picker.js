import { getHighestZIndex, getCoordinates } from './el';

const months = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December"
];

const daysshort = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]; // I don't think I'll need a dayslong ever

class DatePicker {
  constructor(el) {
    var dp = document.querySelector("#datepicker");
    var s;
    var c = getCoordinates(el);
    var x;
    if (!dp) {
      dp = document.createElement("table");
      dp.id = "datepicker";
      document.querySelector("#page").appendChild(dp);
    }
    s = dp.style;
    s.display = "table";
    s.zIndex = getHighestZIndex();
    s.top = c.yh + "px";
    s.left = c.x + "px";
    s = el.value.split("/");
    if (s.length == 3) {
      this.selectedDate = [
        parseInt(s[2]),
        parseInt(s[0]) - 1,
        parseInt(s[1])
      ];
    } else this.selectedDate = undefined;

    this.el = el;
    this.generate(s[2], s[0] ? parseInt(s[0]) - 1 : undefined, s[1]);
  };

  // month should be 0 for jan, 11 for dec
  generate(year, month, day) {
    var date = new Date();
    var dp = document.querySelector("#datepicker");
    var row;
    var cell;
    var x;
    var i;
    // date here is today
    if (year == undefined) {
      year = date.getFullYear();
      month = date.getMonth();
      day = date.getDate();
      this.selectedDate = [year, month, day];
    }

    if (month == -1) {
      year--;
      month = 11;
    }
    if (month == 12) {
      year++;
      month = 0;
    }

    this.lastDate = [year, month, day];

    // this date is used to calculate days in month and the day the first is on
    var numdaysinmonth = new Date(year, month + 1, 0).getDate();
    var first = new Date(year, month, 1).getDay();

    date = new Date(year, month, day);
    // generate the table now
    dp.innerHTML = ""; // clear

    // year
    row = dp.insertRow(0);
    cell = row.insertCell(0);
    cell.innerHTML = "<";
    cell.className = "control";
    cell.onclick = function() {
      this.lastYear();
    };
    cell = row.insertCell(1);
    cell.colSpan = "5";
    cell.className = "year";
    cell.innerHTML = year;
    cell = row.insertCell(2);
    cell.innerHTML = ">";
    cell.className = "control";
    cell.onclick = function() {
      this.nextYear();
    };

    // month title
    row = dp.insertRow(1);
    cell = row.insertCell(0);
    cell.innerHTML = "<";
    cell.className = "control";
    cell.onclick = function() {
      this.lastMonth();
    };
    cell = row.insertCell(1);
    cell.colSpan = "5";
    cell.innerHTML = months[month];
    cell.className = "month";
    cell = row.insertCell(2);
    cell.innerHTML = ">";
    cell.className = "control";
    cell.onclick = function() {
      this.nextMonth();
    };

    // weekdays
    row = dp.insertRow(2);
    row.className = "weekdays";
    for (x = 0; x < 7; x++) row.insertCell(x).innerHTML = daysshort[x];

    row = dp.insertRow(3);
    // generate numbers
    for (x = 0; x < numdaysinmonth; x++) {
      if (!x) for (i = 0; i < first; i++) row.insertCell(i);
      if ((first + x) % 7 == 0) row = dp.insertRow(dp.rows.length);
      cell = row.insertCell((first + x) % 7);
      cell.onclick = function() {
        this.insert(this);
      };
      cell.className =
        "day" +
        (year == this.selectedDate[0] &&
        month == this.selectedDate[1] &&
        x + 1 == this.selectedDate[2]
          ? " selected"
          : "");
      cell.innerHTML = x + 1;
    }
  }

  lastYear() {
    var l = this.lastDate;
    this.generate(l[0] - 1, l[1], l[2]);
  }
  nextYear() {
    var l = this.lastDate;
    this.generate(l[0] + 1, l[1], l[2]);
  }
  lastMonth() {
    var l = this.lastDate;
    this.generate(l[0], l[1] - 1, l[2]);
  }
  nextMonth() {
    var l = this.lastDate;
    this.generate(l[0], l[1] + 1, l[2]);
  }

  insert(cell) {
    var l = this.lastDate;
    this.el.value = l[1] + 1 + "/" + cell.innerHTML + "/" + l[0];
    this.hide();
  }
}

// Static methods
DatePicker.init = function(el) {
  return new DatePicker(el);
}
DatePicker.hide = function() {
  document.querySelector("#datepicker").style.display = "none";
}

export default DatePicker;