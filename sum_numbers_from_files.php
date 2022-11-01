#!/usr/bin/php
<?php

declare(strict_types=1);

define("ACCEPTED_PARAMS", [basename(__FILE__), '-h', '--help', '-f', '--files', '-e', '--extensions', '-ep', '--excluded_paths']);

$start               = __DIR__;
$sum                 = 0;
$searchedFiles       = 0;
$maxNestingLevel     = 0;
$currentNestingLevel = 0;
$scannedDirs         = [];
$scannedFiles        = [];

$excludedPaths           = ['.', '..'];
$acceptedFilesNames      = ['count'];
$acceptedFilesExtensions = ['', 'txt'];

$currentDirectory = $start;

run();

function run(): void {
	global $start;

	parseArgs();

	scanDirectory($start);
}

/**
 * Scanning a directory in depth
 *
 * @param string $currentDir Current directory
 *
 * @return void
 */
function scanDirectory(string $currentDir): void {
	global $excludedPaths;
	global $scannedDirs;
	global $scannedFiles;
	global $currentDirectory;

	if (false === ($resource = opendir($currentDir))) {
		exit(sprintf('An error occurred while opening the directory %s', $currentDir) . PHP_EOL);
	}

	$currentDirectory = $currentDir;

	while (false !== ($child = readdir($resource))) {
		$child = $currentDir . '/' . $child;

		if (in_array(basename($child), $excludedPaths) || in_array($child, array_merge($scannedDirs, $scannedFiles))) {
			continue;
		}

		if (is_dir($child)) {
			closedir($resource);

			updateCurrentNestingLevel();

			scanDirectory($child);
		}

		if (is_file($child)) {
			$scannedFiles[] = $child;

			scanFile($child);
		}
	}

	closedir($resource);

	backwardDirectoryScan();

	showSummaryInfo();

	exit;
}

/**
 * Backward directory scan
 *
 * @return void
 */
function backwardDirectoryScan(): void {
	global $start;
	global $scannedDirs;
	global $currentDirectory;

	if ($start === $currentDirectory) {
		return;
	}

	updateScannedDirs();

	while (in_array($currentDirectory, $scannedDirs)) {
		$exploded = explode('/', $currentDirectory);
		array_pop($exploded);

		$currentDirectory = implode('/', $exploded);
	}

	scanDirectory($currentDirectory);
}

/**
 * Updating the list of scanned directories
 *
 * @return void
 */
function updateScannedDirs(): void {
	global $excludedPaths;
	global $scannedDirs;
	global $currentDirectory;

	$childs = scandir($currentDirectory);
	$childs = array_filter($childs, function($child) use ($excludedPaths) {
		return (false === in_array($child, $excludedPaths) && (true === is_dir($child)));
	});
	$childs = array_map(fn($child): string => $currentDirectory . '/' . $child, $childs);
	foreach ($childs as $child) {
		if (false === in_array($child, $scannedDirs)) {
			return;
		}
	}

	$scannedDirs[] = $currentDirectory;
}

/**
 * Update current nesting level
 *
 * @return void
 */
function updateCurrentNestingLevel(): void {
	global $start;
	global $currentDirectory;
	global $maxNestingLevel;
	global $currentNestingLevel;

	$explodedStartDir   = explode('/', $start);
	$explodedCurrentDir = explode('/', $currentDirectory);

	$currentNestingLevel = count($explodedCurrentDir) - count($explodedStartDir);

	$maxNestingLevel = max($maxNestingLevel, $currentNestingLevel);
}

/**
 * Scan file
 *
 * @param string $filepath Absolute path to the file
 *
 * @return void
 */
function scanFile(string $filepath): void {
	global $searchedFiles;
	global $acceptedFilesExtensions;

	if ('count' !== pathInfo($filepath, PATHINFO_FILENAME) || false === in_array(pathinfo($filepath, PATHINFO_EXTENSION), $acceptedFilesExtensions)) {
		return;
	}

	$searchedFiles++;

	$lines = file($filepath);
	foreach ($lines as $line) {
		$numbers = [];
		if (false === preg_match_all("/\d+/", $line, $numbers)) {
			continue;
		}

		sum(reset($numbers));
	}
}

/**
 * Summing numbers from a file with a total value
 *
 * @param string[] $numbers Numbers from file
 *
 * @return void
 */
function sum(array $numbers): void {
	global $sum;

	foreach ($numbers as $number) {
		$sum += intval($number);
	}
}

/**
 * Show summary info
 *
 * @return void
 */
function showSummaryInfo(): void {
	global $sum;
	global $scannedDirs;
	global $scannedFiles;
	global $searchedFiles;
	global $maxNestingLevel;

	echo sprintf('Sum of all numbers - %u', $sum) . PHP_EOL;
	echo sprintf('Number of scanned directories - %u', count($scannedDirs)) . PHP_EOL;
	echo sprintf('Number of scanned files - %u', count($scannedFiles)) . PHP_EOL;
	echo sprintf('Number of searched files - %u', $searchedFiles) . PHP_EOL;
	echo sprintf('Maximum nesting level - %u', $maxNestingLevel) . PHP_EOL;
}

/**
 * Parse arguments
 *
 * @return void
 */
function parseArgs(): void {
	global $argv;
	global $acceptedFilesNames;
	global $acceptedFilesExtensions;
	global $excludedPaths;

	if ([] !== ($notAcceptedParams = array_diff($argv, ACCEPTED_PARAMS))) {
		exit(sprintf('Passed not accepted params: %s', implode(', ', $notAcceptedParams)) . PHP_EOL);
	}

	$helpParam = getopt('h', ['help']);
	if (false !== $helpParam && [] !== $helpParam) {
		showHelp();

		exit;
	}

	$acceptedFilesParam = getopt('f::', ['files::']);
	if (false !== $acceptedFilesParam && [] !== $acceptedFilesParam) {
		if (in_array(false, $acceptedFilesParam)) {
			exit('Filenames not passed.' . PHP_EOL);
		}

		$files              = $acceptedFilesParam['f'] ?? $acceptedFilesParam['files'];
		$acceptedFilesNames = explode(',', $files);
	}

	$extensionsParam = getopt('e::', ['ext::']);
	if (false !== $extensionsParam && [] !== $extensionsParam) {
		if (in_array(false, $extensionsParam)) {
			exit('Extensions not passed.' . PHP_EOL);
		}

		$extensions              = $extensionsParam['e'] ?? $extensionsParam['extensions'];
		$acceptedFilesExtensions = explode(',', $extensions);
	}

	$globPatternOfExcludedDirs = getopt('ep::', ['excluded_paths::']);
	if (false !== $globPatternOfExcludedDirs && [] !== $globPatternOfExcludedDirs) {
		if (in_array(false, $globPatternOfExcludedDirs)) {
			exit('Glob pattern not passed.' . PHP_EOL);
		}

		$pattern = $globPatternOfExcludedDirs['ep'] ?? $globPatternOfExcludedDirs['excluded_paths'];
		if (false !== ($dirs = glob($pattern, GLOB_ONLYDIR))) {
			$excludedPaths = array_merge($excludedPaths, $dirs);
		}
	}
}

/**
 * Show tips
 *
 * @return void
 */
function showHelp(): void {
	echo 'This script can sum numbers in some files with some extensions.' . PHP_EOL . PHP_EOL;
	echo '--help -h            - Show this help;' . PHP_EOL;
	echo '--files -f           - Filenames to search separated by commas. Example: "test,common,file";' . PHP_EOL;
	echo '--extensions -e      - Allowed extensions separated by commas. Example: "txt,rtf,docx";' . PHP_EOL;
	echo '--excluded_paths -ep - Glob pattern to exclude directories;' . PHP_EOL;
}
