export default [
  {
    input: 'Script/app.js',
    output: {
      file: 'dist/app.js',
      // Kinda hacky - these are all globals
      name: 'window',
      format: 'iife',
    },
  },
  {
    input: 'Script/modcontrols.js',
    output: {
      file: 'dist/modcontrols.js',
      name: 'modcontrols',
      format: 'iife',
    },
  },
  {
    input: 'Script/run.js',
    output: {
      file: 'dist/run.js',
      name: 'RUN',
      format: 'iife',
    },
  },
];