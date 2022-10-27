#!/usr/bin/php
<?php

declare(strict_types=1);

$start           = __DIR__;
$sum             = 0;
$dirs            = 0;
$files           = 0;
$searchedFiles   = 0;
$maxNestingLevel = 0;

$excludedPaths = ['.', '..'];

/**
 * Сканирование каталога в глубину
 *
 * @param string $currentDir Текущая директория
 *
 * @return void
 */
function directoryScan(string $currentDir): void {
	global $dirs;
	global $files;
    global $excludedPaths;

	static $scannedDirs         = [];
	static $scannedFiles        = [];
	static $currentNestingLevel = 0;

	if (false === ($resource = opendir($currentDir))) {
		echo sprintf('Произошла ошибка при открытии каталога %s', $currentDir);

		exit;
	}

	while (false !== ($child = readdir($resource))) {
		$child = $currentDir . '/' . $child;

		if (in_array(basename($child), $excludedPaths) || in_array($child, array_merge($scannedDirs, $scannedFiles))) {
			continue;
		}

		if (is_dir($child)) {
			closedir($resource);

			changeCurrentNestingLevel($currentNestingLevel, $child);

			directoryScan($child);
		}

		if (is_file($child)) {
			$scannedFiles[] = $child;

			scanFile($child);
		}
	}

	closedir($resource);

	backwardDirectoryScan($currentDir, $scannedDirs);

	$files = count($scannedFiles);
	$dirs  = count($scannedDirs);

	echoResult();

	exit;
}

/**
 * Обратное сканирование каталога
 *
 * @param string   $dir           Текущая директория
 * @param string[] $scannedDirs   Массив просканированных директорий
 * @param string[] $excludedPaths Массив исключенных директорий
 *
 * @return void
 */
function backwardDirectoryScan(string $dir, array &$scannedDirs): void {
	global $start;
	global $excludedPaths;

	if ($start === $dir) {
		return;
	}

	updateScannedDirs($dir, $scannedDirs, $excludedPaths);

	while (in_array($dir, $scannedDirs)) {
		$exploded = explode('/', $dir);
		array_pop($exploded);

		$dir = implode('/', $exploded);
	}

	directoryScan($dir);
}

/**
 * Обновление списка просканированных директорий
 *
 * @param string   $currentDir    Текущая директория
 * @param string[] $scannedDirs   Массив просканированных директорий
 *
 * @return void
 */
function updateScannedDirs(string $currentDir, array &$scannedDirs): void {
    global $excludedPaths;

	$childs = scandir($currentDir);
	$childs = array_filter($childs, function($child) use ($excludedPaths) {
		return (false === in_array($child, $excludedPaths) && (true === is_dir($child)));
	});
	$childs = array_map(fn($child): string => $currentDir . '/' . $child, $childs);
	foreach ($childs as $child) {
		if (false === in_array($child, $scannedDirs)) {
			return;
		}
	}

	$scannedDirs[] = $currentDir;
}

/**
 * Обновление текущего уровня вложенности
 *
 * @param int    $currentNestingLevel Текущий уровень вложенности
 * @param string $dir                 Текущая директория
 *
 * @return void
 */
function changeCurrentNestingLevel(int &$currentNestingLevel, string $dir): void {
	global $start;
	global $maxNestingLevel;

	$explodedStartDir   = explode('/', $start);
	$explodedCurrentDir = explode('/', $dir);

	$currentNestingLevel = count($explodedCurrentDir) - count($explodedStartDir);

	$maxNestingLevel = max($maxNestingLevel, $currentNestingLevel);
}

/**
 * Сканирование файла
 *
 * @param string $filepath Абсолютный путь к файлу
 *
 * @return void
 */
function scanFile(string $filepath): void {
	global $searchedFiles;

	$acceptedExtensions = ['', 'txt'];

	if ('count' !== pathInfo($filepath, PATHINFO_FILENAME) || false === in_array(pathinfo($filepath, PATHINFO_EXTENSION), $acceptedExtensions)) {
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
 * Суммирование чисел из файла с итоговым значением
 *
 * @param string[] $numbers Числа из файла
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
 * Вывод итоговой информации
 *
 * @return void
 */
function echoResult(): void {
	global $sum;
	global $dirs;
	global $files;
	global $searchedFiles;
	global $maxNestingLevel;

	echo sprintf('Сумма всех чисел - %u', $sum) . PHP_EOL;
	echo sprintf('Количество просканированных директорий - %u', $dirs) . PHP_EOL;
	echo sprintf('Количество просканированных файлов - %u', $files) . PHP_EOL;
	echo sprintf('Количество искомых файлов - %u', $searchedFiles) . PHP_EOL;
	echo sprintf('Максимальный уровень вложенности - %u', $maxNestingLevel) . PHP_EOL;
}

directoryScan($start);
