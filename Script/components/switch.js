import Component from '../classes/component';
import { insertAfter } from '../JAX/el';
import { assign } from '../JAX/util';

export default class Switch extends Component {
  static get selector() {
    return 'input.switch';
  }

  constructor(element) {
    super(element);
    // Hide original checkbox
    element.style.display = 'none';

    const button = assign(document.createElement('button'), {
      type: 'button',
      className: element.className,
    });

    const toggle = () => {
      button.style.backgroundPosition = element.checked ? 'top' : 'bottom';
    };
    toggle();
    button.addEventListener('click', () => {
      element.checked = !element.checked;
      toggle();
      element.dispatchEvent(new Event('change'));
    });
    insertAfter(button, element);
  }
}
