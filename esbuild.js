import * as esbuild from 'esbuild';

await esbuild.build({
    entryPoints: ['Script/app.ts'],
    bundle: true,
    outfile: 'dist/app.js',
    minify: true,
});

await esbuild.build({
    entryPoints: ['Script/acp.ts'],
    bundle: true,
    outfile: 'dist/acp.js',
    minify: true,
});

await esbuild.build({
    entryPoints: ['Script/modcontrols.ts'],
    bundle: true,
    outfile: 'dist/modcontrols.js',
    minify: true,
});
