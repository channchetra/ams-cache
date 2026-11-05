<?php
/**
 * Fail CI when PHPUnit line coverage drops below a threshold.
 *
 * Usage: php bin/check-coverage.php build/coverage.xml 60
 */

$file = isset( $argv[1] ) ? $argv[1] : 'build/coverage.xml';
$minimum = isset( $argv[2] ) ? (float) $argv[2] : 60.0;

if ( ! is_file( $file ) ) {
	fwrite( STDERR, "Coverage file not found: {$file}\n" );
	exit( 1 );
}

$xml = simplexml_load_file( $file );

if ( false === $xml || ! isset( $xml->project->metrics ) ) {
	fwrite( STDERR, "Coverage file is not valid Clover XML: {$file}\n" );
	exit( 1 );
}

$metrics = $xml->project->metrics;
$covered = (int) $metrics['coveredstatements'];
$total   = (int) $metrics['statements'];

if ( $total <= 0 ) {
	fwrite( STDERR, "Coverage file contains no statements.\n" );
	exit( 1 );
}

$percent = ( $covered / $total ) * 100;

printf( "Line coverage: %.2f%% (%d/%d), minimum %.2f%%\n", $percent, $covered, $total, $minimum );

if ( $percent < $minimum ) {
	fwrite( STDERR, "Coverage gate failed.\n" );
	exit( 1 );
}

echo "Coverage gate passed.\n";
