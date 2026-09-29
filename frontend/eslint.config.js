// @ts-check
const eslint = require('@eslint/js');
const { defineConfig } = require('eslint/config');
const tseslint = require('typescript-eslint');
const angular = require('angular-eslint');
const boundaries = require('eslint-plugin-boundaries');

// Layers inside a domain (src/app/<context>/<domain>/<layer>); captures context (shop | admin) and domain.
const LAYERS = ['domain', 'data-access', 'state', 'ui', 'feature'];

const sameDomain = (...types) => ({
  element: {
    type: types,
    captured: { context: '{{ from.element.captured.context }}', domain: '{{ from.element.captured.domain }}' },
  },
});
const sameContext = (...types) => ({
  element: { type: types, captured: { context: '{{ from.element.captured.context }}' } },
});

module.exports = defineConfig([
  {
    files: ['**/*.ts'],
    extends: [
      eslint.configs.recommended,
      tseslint.configs.recommended,
      tseslint.configs.stylistic,
      angular.configs.tsRecommended,
    ],
    processor: angular.processInlineTemplates,
    rules: {
      '@angular-eslint/directive-selector': [
        'error',
        {
          type: 'attribute',
          prefix: 'app',
          style: 'camelCase',
        },
      ],
      '@angular-eslint/component-selector': [
        'error',
        {
          type: 'element',
          prefix: 'app',
          style: 'kebab-case',
        },
      ],
    },
  },
  {
    files: ['**/*.html'],
    extends: [angular.configs.templateRecommended, angular.configs.templateAccessibility],
    rules: {},
  },

  // Frontend architecture: contexts (shop, admin) -> domains -> layers.
  {
    files: ['src/**/*.ts'],
    plugins: { boundaries },
    settings: {
      'import/resolver': { typescript: { project: './tsconfig.app.json' } },
      'boundaries/legacy-templates': false,
      // The first matching descriptor wins, so the more specific folders come first.
      'boundaries/elements': [
        { type: 'core', pattern: 'src/app/core/*', capture: ['module'] },
        { type: 'shared', pattern: 'src/app/shared/*', capture: ['module'] },
        { type: 'shell', pattern: 'src/app/*/shell/*', capture: ['context', 'name'] },
        ...LAYERS.map((layer) => ({ type: layer, pattern: `src/app/*/*/${layer}`, capture: ['context', 'domain'] })),
        // What is left in a domain folder is its index.ts: the domain's public API.
        { type: 'public-api', pattern: 'src/app/*/*', capture: ['context', 'domain'] },
        // What is left in a context folder is its <context>.routes.ts.
        { type: 'routes', pattern: 'src/app/*', capture: ['context'] },
        // src/app/app*.ts is the composition root and stays unclassified: a catch-all "src/app" element would swallow everything above.
      ],
    },
    rules: {
      'boundaries/dependencies': [
        'error',
        {
          default: 'disallow',
          policies: [
            { allow: { to: { module: { origin: ['external', 'core'] } } } },
            { from: { element: { type: 'core' } }, allow: { to: { element: { type: ['core', 'shared'] } } } },
            { from: { element: { type: 'shared' } }, allow: { to: { element: { type: 'shared' } } } },
            {
              from: { element: { type: 'routes' } },
              allow: { to: [{ element: { type: 'core' } }, sameContext('public-api', 'shell')] },
            },
            {
              from: { element: { type: 'shell' } },
              allow: { to: [{ element: { type: ['core', 'shared'] } }, sameContext('public-api', 'shell')] },
            },
            // A domain's index.ts re-exports what other domains and the shell may use.
            { from: { element: { type: 'public-api' } }, allow: { to: sameDomain(...LAYERS) } },
            // Layers only depend downwards within their own domain.
            { from: { element: { type: 'domain' } }, allow: { to: sameDomain('domain') } },
            {
              from: { element: { type: 'data-access' } },
              allow: { to: [sameDomain('domain', 'data-access'), { element: { type: 'shared' } }] },
            },
            {
              from: { element: { type: 'state' } },
              allow: { to: [sameDomain('domain', 'data-access', 'state'), { element: { type: 'shared' } }] },
            },
            {
              from: { element: { type: 'ui' } },
              allow: { to: [sameDomain('domain', 'ui'), { element: { type: 'shared' } }] },
            },
            // Features may reach other domains of the same context, but only through their index.ts.
            {
              from: { element: { type: 'feature' } },
              allow: {
                to: [sameDomain(...LAYERS), sameContext('public-api'), { element: { type: ['core', 'shared'] } }],
              },
            },
          ],
        },
      ],
    },
  },
  {
    // Only data-access talks to the API; session and shared account API are the app-wide exceptions.
    files: ['src/app/*/*/{domain,state,ui,feature}/**/*.ts', 'src/app/*/shell/**/*.ts'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          paths: [{ name: '@angular/common/http', importNames: ['HttpClient'], message: 'Use the domain data-access layer.' }],
        },
      ],
    },
  },
  {
    // The domain layer is plain TypeScript: no framework, no streams.
    files: ['src/app/*/*/domain/**/*.ts'],
    rules: {
      'no-restricted-imports': [
        'error',
        { patterns: [{ group: ['@angular/*', 'rxjs', 'rxjs/*'], message: 'Keep the domain layer framework-free.' }] },
      ],
    },
  },
]);
