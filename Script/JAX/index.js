import ajax from './ajax';
import browser from './browser';
import { date, smalldate } from './date';
import color from './color';
import datepicker from './date-picker';
import drag from './drag';
import Editor from './editor';
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
import Window from './window';

// TODO: Organize these better
import {
  assign,
  gracefulDegrade,
  checkAll,
  onImagesLoaded,
  handleTabs,
  toggle,
  collapse,
  toggleOverlay,
  scrollTo,
  select,
} from './util';

import tooltip from './tooltip';

export default {
  ajax,
  browser,
  color,
  date,
  datepicker,
  drag,
  Editor,
  el,
  event,
  flashTitle,
  imageResizer,
  makeImageGallery,
  makeResizer,
  scrollablepagelist,
  smalldate,
  sortable,
  sortableTree,
  stopTitleFlashing,
  sfx,
  SWF,
  tooltip,
  Window,

  // TODO: organize
  assign,
  gracefulDegrade,
  checkAll,
  onImagesLoaded,
  handleTabs,
  toggle,
  collapse,
  overlay: toggleOverlay,
  scrollTo,
  select,
};
