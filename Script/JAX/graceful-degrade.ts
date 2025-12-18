/* global RUN */
import AutoComplete from '../components/auto-complete';
import CollapseBox from '../components/collapse-box';
import DatePicker from '../components/date-picker';
import Editor from '../components/editor';
import IdleClock from '../components/idle-clock';
import ImageGallery from '../components/image-gallery';
import ImageResizer from '../components/image-resizer';
import Link from '../components/link';
import MediaPlayer from '../components/media-player';
import PageList from '../components/page-list';
import Switch from '../components/switch';
import Tabs from '../components/tabs';
import { selectAll } from './selection';
import { updateDates } from './util';

export default function gracefulDegrade(container: HTMLElement) {
    updateDates();

    // Make BBCode code blocks selectable when clicked
    container
        .querySelectorAll<HTMLDivElement>('.bbcode.code')
        .forEach((codeBlock) => {
            codeBlock.addEventListener('click', () => selectAll(codeBlock));
        });

    // Hydrate all components
    [
        AutoComplete,
        CollapseBox,
        DatePicker,
        Editor,
        IdleClock,
        ImageGallery,
        ImageResizer,
        Link,
        MediaPlayer,
        PageList,
        Switch,
        Tabs,
    ].forEach((Component) => {
        Component.hydrate(container);
    });

    // Wire up AJAX forms
    // NOTE: This needs to come after editors, since they both hook into form onsubmit
    // and the editor hook needs to fire first
    const ajaxForms = container.querySelectorAll<HTMLFormElement>(
        'form[data-ajax-form]',
    );
    ajaxForms.forEach((ajaxForm) => {
        const resetOnSubmit = ajaxForm.dataset.ajaxForm === 'resetOnSubmit';
        ajaxForm.addEventListener('submit', (event) => {
            event.preventDefault();
            RUN.submitForm(ajaxForm, resetOnSubmit);
        });
    });
}
