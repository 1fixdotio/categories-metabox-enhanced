#!/usr/bin/env node
// Run PHPUnit inside wp-env's tests-cli container.
//
// wp-env mounts this plugin under wp-content/plugins/<basename(repo path)>
// (see @wordpress/env's parse-source-string.js). Deriving the path here
// instead of hardcoding it keeps the test command working when contributors
// clone into a folder name that differs from the plugin slug.

import { spawn } from 'node:child_process';
import path from 'node:path';

const pluginDir = path.basename( path.resolve( '.' ) );
const args = [
	'wp-env',
	'run',
	'tests-cli',
	`--env-cwd=wp-content/plugins/${ pluginDir }`,
	'./vendor/bin/phpunit',
	...process.argv.slice( 2 ),
];

const child = spawn( 'npx', args, { stdio: 'inherit' } );
child.on( 'exit', ( code ) => process.exit( code ?? 1 ) );
