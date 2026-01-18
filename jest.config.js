import { createDefaultPreset } from "ts-jest";

const tsJestTransformCfg = createDefaultPreset().transform;

/** @type {import("jest").Config} **/
export default {
  testEnvironment: "jsdom",
  preset: "ts-jest",
  transform: {
    ...tsJestTransformCfg,
  },
};
