/* global RUN */
import { addIdleClock } from './date';
import { imageResizer } from './image-resizer';
import tooltip from './tooltip';
import { selectAll } from './selection';
import AutoComplete from '../components/auto-complete';
import CollapseBox from '../components/collapse-box';
import DatePicker from '../components/date-picker';
import ImageGallery from '../components/image-gallery';
import PageList from '../components/page-list';
import Switch from '../components/switch';
import Tabs from '../components/tabs';
import { onImagesLoaded, updateDates } from './util';
import Editor from '../components/editor';
import MediaPlayer from '../components/media-player';

export default function gracefulDegrade(container) {
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
      if (href.charAt(0) === '?') {
        const oldclick = link.onclick;
        link.onclick = undefined;
        link.addEventListener('click', (event) => {
          event.preventDefault();
          // Some links have an onclick that returns true/false based on whether
          // or not the link should execute.
          if (!oldclick || oldclick.call(link) !== false) {
            RUN.stream.location(href);
          }
        });

        // Open external links in a new window
      } else if (link.getAttribute('href').substr(0, 4) === 'http') {
        link.target = '_BLANK';
      }
    }
  });

  // Handle image hover magnification
  const bbcodeimgs = Array.from(container.querySelectorAll('.bbcodeimg'));
  if (bbcodeimgs.length) {
    onImagesLoaded(bbcodeimgs).then(() => {
      // resizer on large images
      imageResizer(bbcodeimgs);
    });
  }

  // Make BBCode code blocks selectable when clicked
  container.querySelectorAll('.bbcode.code').forEach((codeBlock) => {
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
    container
      .querySelectorAll(Component.selector)
      .forEach((element) => new Component(element));
  });

  // Wire up AJAX forms
  // NOTE: This needs to come after editors, since they both hook into form onsubmit
  // and the editor hook needs to fire first
  const ajaxForms = container.querySelectorAll('form[data-ajax-form]');
  ajaxForms.forEach((ajaxForm) => {
    const resetOnSubmit = ajaxForm.dataset.ajaxForm === 'resetOnSubmit';
    ajaxForm.addEventListener('submit', (event) => {
      event.preventDefault();
      RUN.submitForm(ajaxForm, resetOnSubmit);
    });
  });

  // Add idle clocks to user lists
  Array.from(document.querySelectorAll('.idle')).forEach((element) =>
    addIdleClock(element),
  );
}
