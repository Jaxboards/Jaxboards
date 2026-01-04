import { createDefaultPreset } from 'ts-jest';

const tsJestTransformCfg = createDefaultPreset().transform;

/** @type {import("jest").Config} **/
export default {
    testEnvironment: 'node',
    preset: 'ts-jest',
    transform: {
        ...tsJestTransformCfg,
    },
};
