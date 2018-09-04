import JAX from './JAX/index';
import Uploader from './uploader';
import Sound from './sound';
import RUN from './run';
import IMWindow from './JAX/instant-messaging-window';
import { assign } from './JAX/util';


// Kinda hacky - these are all globals
assign(window, {
  JAX,
  RUN,
  Uploader,
  Sound,

  // TODO: Make this not globally defined
  IMWindow,
});
