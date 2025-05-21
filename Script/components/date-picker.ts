import { daysShort, months } from '../JAX/date';
import { getCoordinates, getHighestZIndex } from '../JAX/el';
import { supportsDateInput } from '../JAX/util';

export default class DatePicker {
    picker: HTMLTableElement;

    element: HTMLInputElement;

    selectedDate?: number[];

    lastDate?: number[];

    static selector(container: HTMLElement) {
        if (supportsDateInput()) {
            return;
        }
        return container
            .querySelectorAll<HTMLInputElement>('input[type=date]')
            .forEach((el) => new this(el));
    }

    constructor(element: HTMLInputElement) {
        this.element = element;
        this.picker = this.getPicker();

        // Disable browser autocomplete
        element.autocomplete = 'off';
        element.addEventListener('focus', () => this.openPicker());
        element.addEventListener('keydown', () => this.closePicker());
    }

    getPicker() {
        if (this.picker) {
            return this.picker;
        }

        let picker = document.querySelector<HTMLTableElement>('#datepicker');
        if (!picker) {
            picker = Object.assign(document.createElement('table'), {
                id: 'datepicker',
            });
            document.body.appendChild(picker);
            picker.style.display = 'none';
        }

        return picker;
    }

    openPicker() {
        const c = getCoordinates(this.element);
        Object.assign(this.picker.style, {
            display: '',
            zIndex: getHighestZIndex(),
            position: 'absolute',
            top: `${c.yh}px`,
            left: `${c.x}px`,
        });

        const [month, day, year] = this.element.value
            .split('/')
            .map((s) => parseInt(s, 10));
        if (month && day && year) {
            this.selectedDate = [year, month - 1, day];
        } else this.selectedDate = undefined;

        this.generate(year, month, day);
    }

    closePicker() {
        this.picker.style.display = 'none';
    }

    // month should be 0 for jan, 11 for dec
    generate(iyear: number, imonth: number, iday: number) {
        let date = new Date();
        const dp = this.getPicker();
        let row;
        let cell;
        let [year, month, day] = [iyear, imonth, iday];
        // date here is today
        if (year === undefined) {
            year = date.getFullYear();
            month = date.getMonth();
            day = date.getDate();
            this.selectedDate = [year, month, day];
        }

        if (month === -1) {
            year -= 1;
            month = 11;
        }
        if (month === 12) {
            year += 1;
            month = 0;
        }

        this.lastDate = [year, month, day];

        // this date is used to calculate days in month and the day the first is on
        const numdaysinmonth = new Date(year, month + 1, 0).getDate();
        const first = new Date(year, month, 1).getDay();

        date = new Date(year, month, day);
        // generate the table now
        dp.innerHTML = ''; // clear

        // year
        row = dp.insertRow(0);

        // previous year button
        cell = row.insertCell(0);
        cell.innerHTML = '&lt;';
        cell.className = 'control';
        cell.onclick = () => this.lastYear();

        // current year heading
        cell = row.insertCell(1);
        cell.colSpan = 5;
        cell.className = 'year';
        cell.innerHTML = `${year}`;

        // next year button
        cell = row.insertCell(2);
        cell.innerHTML = '>';
        cell.className = 'control';
        cell.onclick = () => this.nextYear();

        // month title
        row = dp.insertRow(1);
        cell = row.insertCell(0);
        cell.innerHTML = '<';
        cell.className = 'control';
        cell.onclick = () => this.lastMonth();

        cell = row.insertCell(1);
        cell.colSpan = 5;
        cell.innerHTML = months[month];
        cell.className = 'month';
        cell = row.insertCell(2);
        cell.innerHTML = '>';
        cell.className = 'control';
        cell.onclick = () => this.nextMonth();

        // weekdays
        row = dp.insertRow(2);
        row.className = 'weekdays';
        for (let x = 0; x < 7; x += 1) {
            row.insertCell(x).innerHTML = daysShort[x];
        }

        row = dp.insertRow(3);
        // generate numbers
        for (let x = 0; x < numdaysinmonth; x += 1) {
            if (!x) {
                for (let i = 0; i < first; i += 1) {
                    row.insertCell(i);
                }
            }
            if ((first + x) % 7 === 0) {
                row = dp.insertRow(dp.rows.length);
            }
            cell = row.insertCell((first + x) % 7);
            cell.onclick = this.insert.bind(this, cell);

            const isSelected =
                !!this.selectedDate &&
                year === this.selectedDate[0] &&
                month === this.selectedDate[1] &&
                x + 1 === this.selectedDate[2];
            cell.className = `day${isSelected ? ' selected' : ''}`;
            cell.innerHTML = `${x + 1}`;
        }
    }

    lastYear() {
        const l = this.lastDate;
        if (l) this.generate(l[0] - 1, l[1], l[2]);
    }

    nextYear() {
        const l = this.lastDate;
        if (l) this.generate(l[0] + 1, l[1], l[2]);
    }

    lastMonth() {
        const l = this.lastDate;
        if (l) this.generate(l[0], l[1] - 1, l[2]);
    }

    nextMonth() {
        const l = this.lastDate;
        if (l) this.generate(l[0], l[1] + 1, l[2]);
    }

    insert(cell: HTMLTableCellElement) {
        const l = this.lastDate;
        if (l) {
            this.element.value = `${l[1] + 1}/${cell.innerHTML}/${l[0]}`;
        }
        this.closePicker();
    }
}
