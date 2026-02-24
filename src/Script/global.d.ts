import type { AppState } from "./run";

export {};

declare global {
  const globalSettings: {
    canIM: boolean;
    isAdmin: boolean;
    isMod: boolean;
    groupID: number;
    soundIM: boolean;
    soundShout: boolean;
    shoutLimit: number;
    userID: number;
    username: string;
    wysiwyg: boolean;
  };

  const RUN: AppState;
}
