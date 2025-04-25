<?php
/**
 * filesystem.php — Sharded, zero-SQL CSV store for Dev Slot Sandbox (v3)
 *
 * ✓ PHP 7.1+ compatible (type hints added where applicable)
 * ✓ Works out-of-the-box with the latest index.php & execute.php
 *
 * Design goals
 * ────────────
 * • Accept a logical slug and deterministically map it to
 * /csv/aa/bb/cc/<slug>.csv (aa…cc are md5 shard dirs)
 * • Provide helpers for archiving strings, ingesting existing files,
 * and building a public URL.
 * • Keep the API minimal.
 */

declare(strict_types=1);

class FileSystem
{
    /** Base directory containing the sharded tree */
    // Ensure this path is correct for your environment. Using __DIR__ makes it relative.
    public const ROOT = __DIR__ . '/csv';

    /** Number of shard levels (2 hex chars per level => 256^LEVELS subdirectories) */
    private const LEVELS = 3; // 3 levels = 16.7M possible subdirectories

    /** Directory permissions for created shard directories */
    private const DIR_PERMISSIONS = 0755; // Standard permissions: rwxr-xr-x

    /*──────────────────────────────────────────────────────────────────────*/
    /* Core Public API                                                     */
    /*──────────────────────────────────────────────────────────────────────*/

    /**
     * Calculates the canonical, sharded path for a given logical slug.
     * Creates the necessary directory hierarchy if $ensure is true.
     *
     * @param string $slug The logical identifier for the file (filename without .csv).
     * @param bool $ensure If true, create shard directories if they don't exist.
     * @return string The absolute path to the target CSV file.
     * @throws RuntimeException If directory creation fails when $ensure is true.
     */
    public static function pathFor(string $slug, bool $ensure = true): string
    {
        // Sanitize slug: allow alphanumeric, underscore, hyphen, dot. Replace others with underscore.
        $safeSlug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $slug);
        // Prevent path traversal attempts and empty slugs
        if (empty($safeSlug) || strpos($safeSlug, '..') !== false) {
            throw new InvalidArgumentException("Invalid or potentially unsafe slug provided: '{$slug}'");
        }

        $hash = md5($safeSlug); // Use MD5 for deterministic sharding (can be changed if needed)
        $shardPath = self::getShardPath($hash);
        $fullDir = self::ROOT . '/' . $shardPath;

        // Create directory structure if needed
        if ($ensure && !is_dir($fullDir)) {
            // @ suppresses errors, but we check the result right after
            if (!@mkdir($fullDir, self::DIR_PERMISSIONS, true) && !is_dir($fullDir)) {
                // Check if the failure was due to a race condition (another process created it)
                // If it still doesn't exist, throw the error.
                throw new RuntimeException("Unable to create shard directory: {$fullDir}. Check permissions.");
            }
        }

        return $fullDir . '/' . $safeSlug . '.csv';
    }

    /**
     * Stores a raw CSV string content under the given slug.
     * Writes the CSV data and a JSON sidecar file with metadata.
     *
     * @param string $csvContent The raw CSV data to store.
     * @param string $slug The logical identifier for this CSV data.
     * @return string The absolute path where the CSV file was stored.
     * @throws RuntimeException If file writing fails.
     * @throws InvalidArgumentException If the slug is invalid.
     */
    public static function storeCsv(string $csvContent, string $slug): string
    {
        $path = self::pathFor($slug, true); // Ensure directory exists
        $bytesWritten = file_put_contents($path, $csvContent);

        if ($bytesWritten === false) {
            throw new RuntimeException("Failed to write CSV content to: {$path}. Check permissions.");
        }

        // Write JSON sidecar
        self::writeJsonSidecar($path, strlen($csvContent)); // Pass actual bytes written

        return $path;
    }

    /**
     * Moves an existing CSV file into the sharded hierarchy based on its filename.
     * Handles potential filename collisions by appending '_N' (e.g., _1, _2).
     *
     * @param string $sourceCsvPath Absolute path to the source CSV file to ingest.
     * @return string The absolute path where the file was moved within the hierarchy.
     * @throws InvalidArgumentException If the source file is not valid.
     * @throws RuntimeException If renaming/moving the file fails.
     */
    public static function ingest(string $sourceCsvPath): string
    {
        if (!is_file($sourceCsvPath) || !is_readable($sourceCsvPath)) {
            throw new InvalidArgumentException("Source file is not a valid, readable file: {$sourceCsvPath}");
        }

        $originalSlug = pathinfo($sourceCsvPath, PATHINFO_FILENAME);
        $currentSlug = $originalSlug;
        $attempt = 0;
        $destinationPath = null;

        // Loop to find a non-colliding destination path
        while (true) {
            try {
                 $destinationPath = self::pathFor($currentSlug, true); // Ensure dir exists
            } catch (InvalidArgumentException $e) {
                // If pathFor fails due to slug sanitization after appending _N, rethrow.
                 throw new InvalidArgumentException("Failed to generate path for slug '{$currentSlug}': " . $e->getMessage(), 0, $e);
            }


            if (!file_exists($destinationPath)) {
                break; // Found an available path
            }

            // Collision detected, append _N and try again
            $attempt++;
            $currentSlug = $originalSlug . '_' . $attempt;

            // Safety break to prevent infinite loops in unexpected scenarios
            if ($attempt > 1000) {
                throw new RuntimeException("Failed to find a non-colliding path for '{$originalSlug}' after {$attempt} attempts.");
            }
        }

        // Attempt to move the file
        if (!rename($sourceCsvPath, $destinationPath)) {
             throw new RuntimeException("Failed to move file from '{$sourceCsvPath}' to '{$destinationPath}'. Check permissions.");
        }

        // Write JSON sidecar for the ingested file
        $bytes = filesize($destinationPath);
        if ($bytes !== false) {
            self::writeJsonSidecar($destinationPath, $bytes);
        } else {
             trigger_error("Could not get filesize for ingested file: {$destinationPath}", E_USER_WARNING);
        }


        return $destinationPath;
    }

    /**
     * Generates a relative URL path for accessing a stored CSV file,
     * assuming the ROOT directory is served publicly (e.g., via Apache).
     *
     * @param string $slug The logical identifier of the CSV file.
     * @param string $baseUrl The base URL path mapping to the FileSystem::ROOT directory (e.g., "/csv-data"). Defaults to '/csv'.
     * @return string The relative URL path.
     */
    public static function urlFor(string $slug, string $baseUrl = '/csv'): string
    {
        // Use the same sanitization as pathFor to ensure consistency
        $safeSlug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $slug);
        if (empty($safeSlug) || strpos($safeSlug, '..') !== false) {
             // Return a non-functional URL or throw error for invalid slugs?
             // Returning a predictable non-path might be safer for web context.
             return '#invalid-slug';
        }

        $hash = md5($safeSlug);
        $shardPath = self::getShardPath($hash);

        // Ensure base URL has no trailing slash, and components have slashes
        return rtrim($baseUrl, '/') . '/' . $shardPath . '/' . rawurlencode($safeSlug) . '.csv';
    }


    /*──────────────────────────────────────────────────────────────────────*/
    /* Internal Helpers                                                    */
    /*──────────────────────────────────────────────────────────────────────*/

    /**
     * Calculates the shard sub-path based on a hash.
     * e.g., md5("...") -> "aa/bb/cc" for LEVELS = 3
     *
     * @param string $hash The hash string (e.g., from md5()).
     * @return string The shard path (e.g., "aa/bb/cc").
     */
    private static function getShardPath(string $hash): string
    {
        $parts = [];
        for ($i = 0; $i < self::LEVELS; $i++) {
            // Ensure we don't try to read past the end of the hash string
            if (($i * 2 + 2) <= strlen($hash)) {
                $parts[] = substr($hash, $i * 2, 2);
            } else {
                // Handle cases where hash is too short for desired levels (unlikely with md5)
                // Pad with '00' or throw an error? Padding seems safer.
                $parts[] = '00';
            }
        }
        return implode('/', $parts);
    }

    /**
     * Writes a JSON sidecar file containing metadata for a stored CSV.
     *
     * @param string $csvPath The full path to the stored CSV file.
     * @param int $byteCount The number of bytes in the CSV file.
     * @return bool True on success, false on failure.
     */
    private static function writeJsonSidecar(string $csvPath, int $byteCount): bool
    {
        $jsonPath = substr($csvPath, 0, -4) . '.json'; // Replace .csv with .json
        $metadata = [
            'slug'        => pathinfo($csvPath, PATHINFO_FILENAME), // Store the final slug used
            'bytes'       => $byteCount,
            'stored_at'   => date(DATE_ATOM), // ISO 8601 format timestamp
            'levels'      => self::LEVELS,    // Record sharding config used
            // Add more metadata if needed (e.g., original filename if ingested)
        ];

        $jsonContent = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonContent === false) {
             trigger_error("Failed to encode JSON metadata for: {$csvPath}", E_USER_WARNING);
             return false;
        }

        if (file_put_contents($jsonPath, $jsonContent) === false) {
             trigger_error("Failed to write JSON sidecar to: {$jsonPath}. Check permissions.", E_USER_WARNING);
             return false;
        }
        return true;
    }

    /*──────────────────────────────────────────────────────────────────────*/
    /* CLI Utility                                                         */
    /*──────────────────────────────────────────────────────────────────────*/

    /**
     * Executes CLI commands for filesystem management.
     * Intended to be called only when the script is run directly from CLI.
     */
    public static function runCli(): void
    {
        global $argv; // Access command line arguments

        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        switch ($command) {
            case 'init':
                echo "Initializing shard directories under: " . self::ROOT . PHP_EOL;
                echo "Using " . self::LEVELS . " levels.\n";
                $count = 0;
                $total = pow(256, self::LEVELS); // Approximate total, only creates top level here
                echo "Creating first-level directories (00-ff)...\n";
                for ($i = 0; $i < 256; $i++) {
                    $firstLevelDir = self::ROOT . '/' . sprintf('%02x', $i);
                    if (!is_dir($firstLevelDir)) {
                        if (@mkdir($firstLevelDir, self::DIR_PERMISSIONS, true)) {
                            echo " ✓ Created {$firstLevelDir}\n";
                            $count++;
                        } else {
                            echo " ✗ Failed to create {$firstLevelDir}\n";
                        }
                    }
                }
                echo "Initialization complete. Created {$count} new first-level directories.\n";
                break;

            case 'seed':
                if (empty($args)) {
                    fwrite(STDERR, "Usage: php filesystem.php seed <file1.csv> [file2.csv ...]\n");
                    fwrite(STDERR, "Moves existing CSV files into the sharded hierarchy.\n");
                    exit(1);
                }
                echo "Ingesting files into: " . self::ROOT . PHP_EOL;
                foreach ($args as $sourceFile) {
                    $sourcePath = realpath($sourceFile); // Resolve relative paths
                    if (!$sourcePath) {
                         echo "✗ Skipping invalid source file: {$sourceFile}\n";
                         continue;
                    }
                    try {
                        $destinationPath = self::ingest($sourcePath);
                        echo " ✓ Ingested: {$sourceFile} -> {$destinationPath}\n";
                    } catch (Throwable $e) {
                        echo "✗ Error ingesting {$sourceFile}: {$e->getMessage()}\n";
                    }
                }
                break;

            case 'path':
                $slug = $args[0] ?? null;
                if ($slug === null) {
                     fwrite(STDERR, "Usage: php filesystem.php path <slug>\n");
                     fwrite(STDERR, "Calculates and prints the canonical path without creating directories.\n");
                     exit(1);
                }
                try {
                     echo self::pathFor($slug, false) . PHP_EOL; // ensure=false
                } catch (InvalidArgumentException $e) {
                     fwrite(STDERR, "Error: {$e->getMessage()}\n");
                     exit(1);
                }

                break;

             case 'url':
                 $slug = $args[0] ?? null;
                 $baseUrl = $args[1] ?? '/csv'; // Optional second arg for base URL
                 if ($slug === null) {
                     fwrite(STDERR, "Usage: php filesystem.php url <slug> [/base/url]\n");
                     fwrite(STDERR, "Calculates and prints the relative URL path.\n");
                     exit(1);
                 }
                 echo self::urlFor($slug, $baseUrl) . PHP_EOL;
                 break;

            case 'help':
            default:
                echo "FileSystem CLI Utility\n";
                echo "Usage: php filesystem.php <command> [options]\n\n";
                echo "Commands:\n";
                echo "  init                Initialize first-level shard directories (00-ff) under " . self::ROOT . "\n";
                echo "  seed <file...>      Move existing local CSV files into the hierarchy.\n";
                echo "  path <slug>         Show the calculated sharded path for a slug (no directories created).\n";
                echo "  url <slug> [base]   Show the relative URL for a slug (default base: '/csv').\n";
                echo "  help                Show this help message.\n";
                exit(0);
        }
    }
}

// --- CLI Execution ---
// Only run the CLI handler if the script is executed directly
if (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0]) && realpath(__FILE__) === realpath($_SERVER['argv'][0])) {
    FileSystem::runCli();
}