import AutoComplete from '../components/auto-complete';
import CollapseBox from '../components/collapse-box';
import DatePicker from '../components/date-picker';
import Editor from '../components/editor';
import Form from '../components/form';
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
        Form,
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
}
