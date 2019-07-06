module.exports = {
  extends: ['airbnb-base', 'plugin:prettier/recommended'],
  plugins: ['prettier', 'jest'],

  rules: {
    // Disables the rule preventing modifying properties on objects passed in
    'no-param-reassign': [2, { props: false }],
    'prettier/prettier': ['error', { singleQuote: true }],
    'prefer-rest-params': 0
  },

  env: {
    node: true,
    "jest/globals": true
  }
};
