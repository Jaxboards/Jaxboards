import ajax from './ajax';
import browser from './browser';
import { date, smalldate } from './date';
import color from './color';
import datepicker from './date-picker';
import drag from './drag';
import editor from './editor';
import el from './el';
import event from './event';
import { flashTitle, stopTitleFlashing } from './flashing-title';
import makeImageGallery from './image-gallery';
import { imageResizer, makeResizer } from './image-resizer';
import scrollablepagelist from './scrollablepagelist';
import sortable from './sortable';
import sortableTree from './sortable-tree';
import SWF from './SWF';
import sfx from './animation';
import window from './window';

// TODO: Organize these better
import {
  assign,
  gracefulDegrade,
  convertSwitches,
  checkAll,
  onImagesLoaded,
  handleTabs,
  toggle,
  collapse,
  toggleOverlay,
  scrollTo,
} from './util';

export default {
  ajax,
  browser,
  color,
  date,
  datepicker,
  drag,
  editor,
  el,
  event,
  flashTitle,
  imageResizer,
  makeResizer,
  scrollablepagelist,
  smalldate,
  sortable,
  sortableTree,
  stopTitleFlashing,
  sfx,
  SWF,
  window,

  // TODO: organize
  assign,
  gracefulDegrade,
  convertSwitches,
  checkAll,
  onImagesLoaded,
  handleTabs,
  toggle,
  collapse,
  overlay: toggleOverlay,
  scrollTo
};