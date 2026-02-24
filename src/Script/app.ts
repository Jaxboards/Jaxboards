import IMWindow from "./JAX/instant-messaging-window";
import ModControls from "./modcontrols";
import RUN from "./run";

Object.assign(globalThis, {
  RUN,
  IMWindow,
  ModControls: new ModControls(),
});
