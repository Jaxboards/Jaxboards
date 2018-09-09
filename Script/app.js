import RUN from './run';
import IMWindow from './JAX/instant-messaging-window';
import { assign } from './JAX/util';

// TODO: Make these not globally defined
assign(window, {
  RUN,
  IMWindow,
});
