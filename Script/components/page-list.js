import Component from '../classes/component';

export default class PageList extends Component {
  static get selector() {
    return '.pages';
  }

  constructor(element) {
    super(element);
    element.addEventListener('wheel', (event) => this.wheel(event));
  }

  wheel(event) {
    event.preventDefault();
    const direction = Math.sign(event.deltaY);
    const pages = Array.from(this.element.querySelectorAll('a'));
    const startPage = parseInt(pages[1].innerHTML, 10);
    const lastPage = parseInt(pages[pages.length - 1].innerHTML, 10);
    const between = pages.length - 2;

    if (
      (direction > 0 && startPage + between < lastPage) ||
      (direction < 0 && startPage > 2)
    ) {
      for (let x = 0; x < between; x += 1) {
        pages[x + 1].href = pages[x + 1].href.replace(
          /\d+$/,
          x + startPage + direction,
        );
        pages[x + 1].innerHTML = startPage + x + direction;
      }
    }
  }
}
