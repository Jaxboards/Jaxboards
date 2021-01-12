import Component from '../classes/component';

const ACTIVE_CLASS = 'active';

export default class Tabs extends Component {
  static get selector() {
    return '.tabs';
  }

  constructor(element) {
    super(element);
    element.addEventListener('click', (event) => this.click(event));
  }

  click(event) {
    const { tabSelector } = this.element.dataset;

    let { target } = event;
    if (target.tagName.toLowerCase() !== 'a') {
      return;
    }
    if (tabSelector) {
      target = target.closest(tabSelector);
    }
    const activeTab = this.element.querySelector(`.${ACTIVE_CLASS}`);
    if (activeTab) {
      activeTab.classList.remove(ACTIVE_CLASS);
    }
    target.className = ACTIVE_CLASS;
    target.blur();
  }
}
