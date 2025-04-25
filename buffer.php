<?php
declare(strict_types=1);
define('BFR_LOADED', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        if (strlen($haystack) < $length) {
            return false;
        }
        return substr($haystack, -$length) === $needle;
    }
}
$setsDir = __DIR__ . '/instructionSets';
if (!is_dir($setsDir)) {
    if (!@mkdir($setsDir, 0755, true) && !is_dir($setsDir)) {
        trigger_error("Failed to create instruction sets directory: {$setsDir}. Please check permissions.", E_USER_WARNING);
    }
}
$ALL_TEXT = [];
$ALL_LINES = [];
if (is_dir($setsDir) && is_readable($setsDir)) {
    try {
        $dirIterator = new DirectoryIterator($setsDir);
        foreach ($dirIterator as $fileinfo) {
            if ($fileinfo->isFile() && $fileinfo->isReadable() && $fileinfo->getExtension() === 'txt') {
                $name = $fileinfo->getBasename();
                $path = $fileinfo->getPathname();
                $text = file_get_contents($path);

                if ($text !== false) {
                    $ALL_TEXT[$name] = $text;
                    $ALL_LINES[$name] = preg_split('/\R/u', $text);
                } else {
                    trigger_error("Failed to read instruction set file: {$path}", E_USER_WARNING);
                }
            }
        }
    } catch (Exception $e) {
         trigger_error("Error reading instruction sets directory '{$setsDir}': " . $e->getMessage(), E_USER_WARNING);
    }

} else {
     trigger_error("Instruction sets directory '{$setsDir}' is not accessible.", E_USER_WARNING);
}
function get_slot_code(string $setName, int $slot): ?string
{
    global $ALL_TEXT;
    if (!isset($ALL_TEXT[$setName])) {
        return null;
    }
    $pattern = '/^\s*\/\/\s*(?:slot|command)\s+' . preg_quote((string)$slot, '/') . '\s*\R([\s\S]*?)(?=(?:^\s*\/\/\s*(?:slot|command)\s+\d+)|\z)/m';

    if (preg_match($pattern, $ALL_TEXT[$setName], $matches)) {
        return trim($matches[1]);
    }

    return null;
}