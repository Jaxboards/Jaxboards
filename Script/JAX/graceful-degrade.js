/* global RUN */
import DatePicker from './date-picker';
import scrollablepagelist from './scrollablepagelist';
import { imageResizer } from './image-resizer';
import makeImageGallery from './image-gallery';
import tooltip from './tooltip';
import { selectAll } from './selection';
import autocompleteDecorator from './autocomplete';
import {
  collapse,
  convertSwitches,
  handleTabs,
  onImagesLoaded,
  updateDates,
} from './util';
import Editor from './editor';
import {
  insertAfter,
} from './el';
import Window from './window';

export default function gracefulDegrade(a) {
  if (typeof RUN !== 'undefined') {
    updateDates();
  }

  // Special rules for all links
  const links = a.querySelectorAll('a');
  links.forEach((link) => {
    // Hande links with tooltips
    if (link.dataset.useTooltip) {
      link.addEventListener('mouseover', () => tooltip(link));
    }

    // Make all links load through AJAX
    if (link.href) {
      const href = link.getAttribute('href');
      if (href.charAt(0) === '?') {
        const oldclick = link.onclick;
        link.addEventListener('click', (event) => {
          // Some links have an onclick that returns true/false based on whether
          // or not the link should execute.
          if (!oldclick || oldclick.call(link) !== false) {
            RUN.stream.location(href);
          }
          event.preventDefault();
        });

      // Open external links in a new window
      } else if (link.getAttribute('href').substr(0, 4) === 'http') {
        link.target = '_BLANK';
      }
    }

    // Hook up autocomplete form fields
    const autoCompleteFields = document.querySelectorAll('[data-autocomplete-action]');
    autoCompleteFields.forEach((field) => {
      autocompleteDecorator(field);
    });
  });

  // Convert checkboxes to icons (checkmark and X)
  convertSwitches(Array.from(a.querySelectorAll('.switch')));

  // Handle image hover magnification
  const bbcodeimgs = Array.from(document.querySelectorAll('.bbcodeimg'));
  if (bbcodeimgs) {
    onImagesLoaded(
      bbcodeimgs,
      () => {
        // resizer on large images
        imageResizer(bbcodeimgs);

        // handle image galleries
        const galleries = Array.from(document.querySelectorAll('.image_gallery'));
        galleries.map(makeImageGallery);
      },
      2000,
    );
  }

  // Initialize page lists that scroll with scroll wheel
  const pages = Array.from(a.querySelectorAll('.pages'));
  if (pages.length) {
    pages.map(scrollablepagelist);
  }

  // Set up date pickers
  const dateElements = Array.from(a.querySelectorAll('input.date'));
  if (dateElements.length) {
    dateElements.forEach((inputElement) => {
      inputElement.onclick = () => DatePicker.init(inputElement);
      inputElement.onkeydown = () => DatePicker.hide();
    });
  }

  // Make BBCode code blocks selectable when clicked
  const codeBlocks = a.querySelectorAll('.bbcode.code');
  codeBlocks.forEach((codeBlock) => {
    codeBlock.addEventListener('click', () => selectAll(codeBlock));
  });

  // Make collapse boxes collapsible
  const collapseBoxes = a.querySelectorAll('.collapse-box');
  collapseBoxes.forEach((collapseBox) => {
    const collapseButton = collapseBox.querySelector('.collapse-button');
    const collapseContent = collapseBox.querySelector('.collapse-content');
    collapseButton.addEventListener('click', () => {
      collapse(collapseContent);
    });
  });

  // Handle tabs
  const tabContainers = a.querySelectorAll('.tabs');
  tabContainers.forEach((tabContainer) => {
    const { tabSelector } = tabContainer.dataset;
    tabContainer.addEventListener('click', event => handleTabs(event, tabContainer, tabSelector));
  });

  // Handle BBCode editors
  const editors = a.querySelectorAll('textarea.bbcode-editor');
  editors.forEach((editor) => {
    const iframe = document.createElement('iframe');
    iframe.addEventListener('load', () => {
      iframe.editor = new Editor(editor, iframe);
    });
    iframe.style.display = 'none';
    insertAfter(iframe, editor);
    editor.closest('form').addEventListener('submit', () => {
      iframe.editor.submit();
    });
  });

  // Handle media players
  const mediaPlayers = a.querySelectorAll('.media');
  mediaPlayers.forEach((player) => {
    const popoutLink = player.querySelector('a.popout');
    const inlineLink = player.querySelector('a.inline');
    const movie = player.querySelector('.movie');

    popoutLink.addEventListener('click', (event) => {
      event.preventDefault();
      const win = new Window({
        title: popoutLink.href,
        content: movie.innerHTML,
      });
      win.create();
    });

    inlineLink.addEventListener('click', (event) => {
      event.preventDefault();
      movie.style.display = 'block';
    });
  });

  // Wire up AJAX forms
  // NOTE: This needs to come after editors, since they both hook into form onsubmit
  // and the editor hook needs to fire first
  const ajaxForms = a.querySelectorAll('form[data-ajax-form]');
  ajaxForms.forEach((ajaxForm) => {
    const resetOnSubmit = ajaxForm.dataset.ajaxForm === 'resetOnSubmit';
    ajaxForm.addEventListener('submit', (event) => {
      event.preventDefault();
      RUN.submitForm(ajaxForm, resetOnSubmit);
    });
  });
}
