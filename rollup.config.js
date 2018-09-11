export default [
  {
    input: 'Script/app.js',
    output: {
      file: 'dist/app.js',
      format: 'iife'
    }
  },
  {
    input: 'Script/modcontrols.js',
    output: {
      file: 'dist/modcontrols.js',
      name: 'modcontrols',
      format: 'iife'
    }
  },
  {
    input: 'Script/acp.js',
    output: {
      file: 'dist/acp.js',
      name: 'RUN',
      format: 'iife'
    }
  }
];
