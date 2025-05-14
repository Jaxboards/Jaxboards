/* global RUN */
import AutoComplete from '../components/auto-complete';
import CollapseBox from '../components/collapse-box';
import DatePicker from '../components/date-picker';
import Editor from '../components/editor';
import ImageGallery from '../components/image-gallery';
import MediaPlayer from '../components/media-player';
import PageList from '../components/page-list';
import Switch from '../components/switch';
import Tabs from '../components/tabs';
import { addIdleClock } from './date';
import { imageResizer } from './image-resizer';
import { selectAll } from './selection';
import tooltip from './tooltip';
import { onImagesLoaded, updateDates } from './util';

export default function gracefulDegrade(container: HTMLElement) {
    updateDates();

    // Special rules for all links
    const links = container.querySelectorAll('a');
    links.forEach((link) => {
        // Handle links with tooltips
        if (link.dataset.useTooltip) {
            link.addEventListener('mouseover', () => tooltip(link));
        }

        // Make all links load through AJAX
        if (link.href) {
            const href = link.getAttribute('href');
            const { host, pathname } = window.location;
            const isLocalLink =
                !link.target &&
                link.host === host &&
                link.pathname === pathname;

            if (isLocalLink) {
                const oldclick = link.onclick;
                link.onclick = null;
                link.addEventListener('click', (event: MouseEvent) => {
                    event.preventDefault();
                    // Some links have an onclick that returns true/false based on whether
                    // or not the link should execute.
                    if (!oldclick || oldclick.call(link, event) !== false) {
                        RUN.stream.location(href);
                    }
                });

                // Open external links in a new window
            } else {
                link.target = '_BLANK';
            }
        }
    });

    // Handle image hover magnification
    const bbcodeimgs = Array.from(container.querySelectorAll<HTMLImageElement>('.bbcodeimg'));
    if (bbcodeimgs.length) {
        onImagesLoaded(bbcodeimgs).then(() => {
            // resizer on large images
            imageResizer(bbcodeimgs);
        });
    }

    // Make BBCode code blocks selectable when clicked
    container.querySelectorAll<HTMLDivElement>('.bbcode.code').forEach((codeBlock) => {
        codeBlock.addEventListener('click', () => selectAll(codeBlock));
    });

    // Hydrate all components
    [
        AutoComplete,
        CollapseBox,
        DatePicker,
        Editor,
        ImageGallery,
        MediaPlayer,
        PageList,
        Switch,
        Tabs,
    ].forEach((Component) => {
        Component.selector(container);
    });

    // Wire up AJAX forms
    // NOTE: This needs to come after editors, since they both hook into form onsubmit
    // and the editor hook needs to fire first
    const ajaxForms = container.querySelectorAll<HTMLFormElement>('form[data-ajax-form]');
    ajaxForms.forEach((ajaxForm) => {
        const resetOnSubmit = ajaxForm.dataset.ajaxForm === 'resetOnSubmit';
        ajaxForm.addEventListener('submit', (event) => {
            event.preventDefault();
            RUN.submitForm(ajaxForm, resetOnSubmit);
        });
    });

    // Add idle clocks to user lists
    Array.from(document.querySelectorAll<HTMLAnchorElement>('.idle')).forEach((element) =>
        addIdleClock(element),
    );
}
