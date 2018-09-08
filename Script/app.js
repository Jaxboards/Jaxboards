import JAX from './JAX/index';
import Sound from './sound';
import RUN from './run';
import IMWindow from './JAX/instant-messaging-window';
import { assign } from './JAX/util';

// Kinda hacky - these are all globals
assign(window, {
  JAX,
  RUN,
  Sound,

  // TODO: Make this not globally defined
  IMWindow,
});
