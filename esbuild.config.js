/**
 * ESBuild Configuration for Labyrinth
 *
 * @package Labyrinth
 * @license GPL-2.0+
 */

/* eslint-disable no-console */
/* eslint-disable @typescript-eslint/no-var-requires */

const fs = require('fs');
const path = require('path');

const { build } = require('esbuild');

const isProduction = process.env.NODE_ENV === 'production';

/**
 * Generate asset manifest from esbuild metafile.
 *
 * @param {Object} metafile - ESBuild metafile object
 * @return {Object} Asset manifest mapping logical names to hashed names
 */
function generateManifest(metafile) {
	const manifest = {
		generated: new Date().toISOString(),
	};

	try {
		const packagePath = path.join(__dirname, 'package.json');
		if (fs.existsSync(packagePath)) {
			const packageJson = JSON.parse(
				fs.readFileSync(packagePath, 'utf8')
			);
			if (packageJson.version) {
				manifest.version = packageJson.version;
			}
		}
	} catch (error) {
		console.warn(`Could not read version: ${error.message}`);
	}

	if (metafile && metafile.outputs) {
		for (const [outputPath, outputMeta] of Object.entries(
			metafile.outputs
		)) {
			if (outputMeta.entryPoint) {
				const hashedFilename = path.basename(outputPath);

				const match = hashedFilename.match(
					/^(.+?)\.[A-Z0-9]+\.([^.]+)$/
				);
				if (match) {
					const [, name, ext] = match;
					const logicalName = `${name}.${ext}`;
					manifest[logicalName] = hashedFilename;
				}
			}
		}
	}

	return manifest;
}

/**
 * Clean up old hashed files that are no longer in the manifest.
 *
 * @param {Object} manifest - Current manifest with active files
 * @return {void}
 */
function cleanOldHashedFiles(manifest) {
	const buildDir = path.join(__dirname, 'build');

	if (!fs.existsSync(buildDir)) {
		return;
	}

	try {
		const files = fs.readdirSync(buildDir);
		const activeFiles = new Set(Object.values(manifest).filter(Boolean));

		const hashedPattern = /^(.+?)\.[A-Za-z0-9]{8}\.([^.]+)$/;

		files.forEach((file) => {
			if (
				!hashedPattern.test(file) ||
				file === 'asset-manifest.json' ||
				file.endsWith('.map') ||
				file.endsWith('.css')
			) {
				return;
			}

			if (!activeFiles.has(file)) {
				const filePath = path.join(buildDir, file);
				try {
					fs.unlinkSync(filePath);
					console.log(`Cleaned up old file: ${file}`);
				} catch (unlinkError) {
					console.warn(
						`Failed to remove old file ${file}: ${unlinkError.message}`
					);
				}
			}
		});
	} catch (error) {
		console.warn(`Failed to clean old files: ${error.message}`);
	}
}

/**
 * Write manifest to disk.
 *
 * @param {Object} manifest - Manifest object to write
 * @return {void}
 */
function writeManifest(manifest) {
	const manifestPath = path.join(__dirname, 'build', 'asset-manifest.json');
	const buildDir = path.dirname(manifestPath);

	if (!fs.existsSync(buildDir)) {
		fs.mkdirSync(buildDir, { recursive: true });
	}

	fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), 'utf8');

	cleanOldHashedFiles(manifest);

	console.log('Generated asset manifest');
}

/**
 * Base configuration for all builds.
 */
const baseConfig = {
	bundle: true,
	platform: 'browser',
	target: 'es2019',
	jsx: 'automatic',
	resolveExtensions: ['.tsx', '.ts', '.jsx', '.js'],
	loader: {
		'.ts': 'ts',
		'.tsx': 'tsx',
	},
	define: {
		'process.env.NODE_ENV': JSON.stringify(
			process.env.NODE_ENV || 'production'
		),
	},
	splitting: true,
	metafile: true,
};

/**
 * Production configuration.
 */
const productionConfig = {
	...baseConfig,
	entryPoints: {
		'labyrinth-admin-analytics': 'src/admin-analytics.ts',
	},
	outdir: 'build',
	format: 'esm',
	entryNames: '[dir]/[name].[hash]',
	chunkNames: '[name]-[hash]',
	minify: true,
	treeShaking: true,
	drop: ['console', 'debugger'],
	legalComments: 'none',
	keepNames: true,
	minifyWhitespace: true,
	minifyIdentifiers: false,
	minifySyntax: true,
};

/**
 * Development configuration.
 */
const developmentConfig = {
	...baseConfig,
	entryPoints: {
		'labyrinth-admin-analytics': 'src/admin-analytics.ts',
	},
	outdir: 'build',
	format: 'esm',
	entryNames: '[dir]/[name].[hash]',
	chunkNames: '[name]-[hash]',
	sourcemap: true,
	minify: false,
};

/**
 * Development watch function.
 */
async function startDevWatch() {
	const { context } = require('esbuild');

	try {
		console.log('Starting development watch mode...');

		const watchConfig = {
			...developmentConfig,
			plugins: [
				{
					name: 'manifest-plugin',
					setup(buildInstance) {
						buildInstance.onEnd((result) => {
							if (result.errors.length === 0) {
								if (result.metafile) {
									const manifest = generateManifest(
										result.metafile
									);
									writeManifest(manifest);
								}
								console.log(
									'Build completed, manifest updated'
								);
							} else {
								console.error('Build failed:', result.errors);
							}
						});
					},
				},
			],
		};

		const ctx = await context(watchConfig);
		await ctx.watch();
		console.log('Watching for changes...');

		process.on('SIGINT', async () => {
			console.log('\nStopping watch mode...');
			await ctx.dispose();
			process.exit(0);
		});

		process.on('SIGTERM', async () => {
			console.log('\nStopping watch mode...');
			await ctx.dispose();
			process.exit(0);
		});
	} catch (error) {
		console.error('Watch mode failed:', error);
		process.exit(1);
	}
}

module.exports = {
	baseConfig,
	productionConfig,
	developmentConfig,
	startDevWatch,
};

/**
 * Build function for CLI usage.
 */
async function buildAll() {
	try {
		console.log('Building Labyrinth assets...\n');

		let result;
		if (isProduction) {
			console.log('Building production bundle...');
			result = await build(productionConfig);
			console.log('Production bundle complete\n');
		} else {
			console.log('Building development bundle...');
			result = await build(developmentConfig);
			console.log('Development bundle complete\n');
		}

		if (result.metafile) {
			const manifest = generateManifest(result.metafile);
			writeManifest(manifest);
		}

		console.log('All builds completed successfully!');
	} catch (error) {
		console.error('Build failed:', error);
		process.exit(1);
	}
}

if (require.main === module) {
	buildAll();
}
