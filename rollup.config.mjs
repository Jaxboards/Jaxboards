import terser from '@rollup/plugin-terser';

export default [
  {
    input: 'Script/app.js',
    output: {
      file: 'dist/app.js',
      format: 'iife',
      plugins: [terser()],
    },
  },
  {
    input: 'Script/modcontrols.js',
    output: {
      file: 'dist/modcontrols.js',
      name: 'modcontrols',
      format: 'iife',
      plugins: [terser()],
    },
  },
  {
    input: 'Script/acp.js',
    output: {
      file: 'dist/acp.js',
      name: 'RUN',
      format: 'iife',
      plugins: [terser()],
    },
  },
];
