<?php
/**
 * execute.php — Self-contained runner for Dev Slot Sandbox (v3-local)
 *
 * • Works both as an HTTP endpoint (JSON API + fallback HTML UI) and CLI.
 * • Requires buffer.php for shared functions (polyfills, get_slot_code).
 * • Requires filesystem.php for CSV archiving (optional, provides stub if missing).
 *
 * ── URL usage ────────────────────────────────────────────────────────────
 * /execute.php?set=mySet.txt&cmd=5
 * /execute.php?set=foo.txt&cmds=0,2-4
 * /execute.php?set=bar.txt&cmds=1&params=user%3Dtest%0Adebug%3D1  (URL encoded params)
 *
 * ── CLI usage ────────────────────────────────────────────────────────────
 * php execute.php mySet.txt 5
 * php execute.php mySet.txt 0,2-4
 * php execute.php mySet.txt 1 user=test debug=1 (Params as subsequent args)
 */

/*──────────────────────────────────────────────────────────────────────────*/
// 1) Environment – STRICT TYPES + Capture PHP errors for JSON encoding
/*──────────────────────────────────────────────────────────────────────────*/

declare(strict_types=1);

// Capture errors instead of displaying them directly
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

$PHP_ERRORS = [];
set_error_handler(function (int $errno, string $msg, string $file = null, int $line = null) use (&$PHP_ERRORS): bool {
    $PHP_ERRORS[] = [
        'type' => $errno,
        'msg'  => $msg,
        'file' => $file ?? 'N/A',
        'line' => $line ?? 'N/A',
    ];
    return true;
});

// Register a shutdown function to catch fatal errors
register_shutdown_function(function () use (&$PHP_ERRORS) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
         // Check if this error was already captured by set_error_handler
        $is_captured = false;
        foreach ($PHP_ERRORS as $captured_error) {
            if ($captured_error['type'] === $error['type'] &&
                $captured_error['msg'] === $error['message'] &&
                $captured_error['file'] === $error['file'] &&
                $captured_error['line'] === $error['line']) {
                $is_captured = true;
                break;
            }
        }
        if (!$is_captured) {
             $PHP_ERRORS[] = [
                 'type' => $error['type'],
                 'msg'  => $error['message'],
                 'file' => $error['file'] ?? 'N/A',
                 'line' => $error['line'] ?? 'N/A',
                 'fatal' => true
             ];
        }

        // If a fatal error occurred, and headers haven't been sent, try to send JSON error
        if (!headers_sent()) {
            header('Content-Type: application/json', true, 500);
            echo json_encode([
                'error'      => 'Fatal error during execution.',
                'run_at'     => date(DATE_ATOM),
                'php_errors' => $PHP_ERRORS
            ], JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
         // Ensure script termination after fatal error handling attempt
         exit(1);
    }
});


/*──────────────────────────────────────────────────────────────────────────*/
// 2) Dependencies – buffer runtime + optional filesystem helper
/*──────────────────────────────────────────────────────────────────────────*/

$root = __DIR__;
$FS_LOADED = false;

// 2-a) Buffer runtime (REQUIRED)
$bufferFile = $root . '/buffer.php';
if (is_file($bufferFile) && is_readable($bufferFile)) {
    require_once $bufferFile;
    // Check if the required function exists after inclusion
    if (!function_exists('get_slot_code')) {
         trigger_error('buffer.php was included but get_slot_code() is missing.', E_USER_ERROR);
         // Error handler will catch this and attempt JSON output
    }
} else {
    trigger_error("Required file buffer.php is missing or not readable at {$bufferFile}", E_USER_ERROR);
    // Error handler will catch this and attempt JSON output
}

// 2-b) Optional sharded CSV store (FileSystem class)
$fsFile = $root . '/filesystem.php';
if (is_file($fsFile) && is_readable($fsFile)) {
    require_once $fsFile;
    // Check if the required class and method exist
    if (class_exists('FileSystem') && method_exists('FileSystem', 'storeCsv')) {
        $FS_LOADED = true;
    } else {
         trigger_error('filesystem.php was included but FileSystem::storeCsv() is missing.', E_USER_WARNING);
    }
}
// Note: If filesystem.php is missing or invalid, $FS_LOADED remains false, and CSV archiving will fail gracefully.


/*──────────────────────────────────────────────────────────────────────────*/
// 3) Helper functions (parse ranges, CSV detector, archive_csv, runner)
/*──────────────────────────────────────────────────────────────────────────*/

/**
 * Return true if the first non-blank line contains a comma -> likely CSV.
 * Handles various line endings.
 */
function looks_like_csv(string $s): bool
{
    $lines = preg_split('/\R/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($lines)) {
        return false;
    }
    // Check the first non-empty line
    return strpos($lines[0], ',') !== false;
}

/**
 * Parse "0,2-4, 8" into [0, 2, 3, 4, 8] (unique, sorted integers).
 * Handles various dash types and extra whitespace.
 */
function parse_ids(string $spec): array
{
    $out = [];
    $parts = explode(',', $spec);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;

        // Normalize dashes
        $part = str_replace(['–', '—'], '-', $part);

        if (strpos($part, '-') !== false) {
            // Handle range
            $range = explode('-', $part, 2);
            if (count($range) === 2 && ctype_digit($range[0]) && ctype_digit($range[1])) {
                $start = (int)$range[0];
                $end = (int)$range[1];
                if ($end >= $start) { // Ensure correct order
                    $out = array_merge($out, range($start, $end));
                }
            } // Ignore invalid ranges like "1-", "-5", "a-b"
        } elseif (ctype_digit($part)) {
            // Handle single number
            $out[] = (int)$part;
        } // Ignore non-numeric parts
    }
    $out = array_unique($out);
    sort($out);
    return $out;
}

/**
 * Store CSV content using FileSystem::storeCsv if available, return metadata.
 */
function archive_csv(string $csvContent, string $set, int $slot): ?array
{
    global $FS_LOADED;
    if (!$FS_LOADED) {
        trigger_error('FileSystem class not available. Cannot archive CSV.', E_USER_WARNING);
        return null;
    }

    // Create a unique slug based on set, slot, and timestamp
    $slug = pathinfo($set, PATHINFO_FILENAME) . "_{$slot}_" . date('Ymd_His');

    try {
        $path = FileSystem::storeCsv($csvContent, $slug);
        return [
            'stored' => true,
            'bytes'  => strlen($csvContent),
            'slug'   => $slug,
            'path'   => $path, // filesystem.php provides the canonical path
        ];
    } catch (Throwable $e) {
        // Capture exception from FileSystem::storeCsv
        trigger_error("Failed to archive CSV for slot {$slot}: " . $e->getMessage(), E_USER_WARNING);
        return null;
	}
}

function run_slot(string $setName, int $slot, array $devParams = []): array
{
    global $PHP_ERRORS;

    $globalErrorsBackup = $PHP_ERRORS;
    $PHP_ERRORS = [];

    $originalRequest = $_REQUEST;
    $_REQUEST = array_merge($_REQUEST, $devParams);

    $result = ['slot' => $slot];

    try {
        $code = get_slot_code($setName, $slot);
        if ($code === null) {
            throw new RuntimeException("Slot {$slot} not found in set '{$setName}'");
        }

        ob_start();
        $returnValue = eval($code);
        $output = ob_get_clean();

        if ($returnValue !== null) {
            if (is_string($returnValue) || is_numeric($returnValue) || (is_object($returnValue) && method_exists($returnValue, '__toString'))) {
                $output .= (string) $returnValue;
            } elseif (is_bool($returnValue)) {
                $output .= $returnValue ? 'true' : 'false';
            } elseif (is_array($returnValue) || is_object($returnValue)) {
                $output .= '[Return Type: ' . gettype($returnValue) . ']';
            }
        }

        $result['output'] = $output;

        if (looks_like_csv($output)) {
            $csvMeta = archive_csv($output, $setName, $slot);
            if ($csvMeta !== null) {
                $result['csv'] = $csvMeta;
            }
        }

    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $result['error'] = $e->getMessage();

        $PHP_ERRORS[] = [
            'type' => $e instanceof Error ? E_ERROR : E_WARNING,
            'msg'  => get_class($e) . ': ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];

    } finally {
        $_REQUEST = $originalRequest;

        if (!empty($PHP_ERRORS)) {
            $result['php_errors'] = $PHP_ERRORS;
        }

        $PHP_ERRORS = array_merge($globalErrorsBackup, $PHP_ERRORS);
    }

    return $result;
}

/*──────────────────────────────────────────────────────────────────────────*/
// 4) Unify CLI arguments and HTTP GET/POST parameters
/*──────────────────────────────────────────────────────────────────────────*/

$params = [];
$devParams = [];

if (PHP_SAPI === 'cli') {
    // CLI: php execute.php <set> <cmds> [param1=value1] [param2=value2] ...
    $params['set'] = $argv[1] ?? null;
    $params['cmds'] = $argv[2] ?? null;
    // Capture remaining args as dev params
    $cliArgs = array_slice($argv, 3);
    foreach ($cliArgs as $arg) {
        if (strpos($arg, '=') !== false) {
            list($key, $value) = explode('=', $arg, 2);
            $devParams[trim($key)] = trim($value);
        }
    }
} else {
    // HTTP: Combine GET and POST (POST overrides GET)
    $params = array_merge($_GET, $_POST);
    // Extract dev params from 'params' query parameter if present
    if (isset($params['params'])) {
        parse_str(str_replace("\n", "&", $params['params']), $parsedDevParams);
        $devParams = array_merge($devParams, $parsedDevParams);
        unset($params['params']);
    }
}

/*──────────────────────────────────────────────────────────────────────────*/
// 5) Serve HTML playground if accessed directly without 'set' parameter (HTTP only)
/*──────────────────────────────────────────────────────────────────────────*/

if (PHP_SAPI !== 'cli' && empty($params['set'])) {
    // Ensure buffer.php was loaded to list sets
    if (!function_exists('get_slot_code')) {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo "Error: buffer.php failed to load. Cannot display HTML playground.";
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    // Use $ALL_TEXT keys which are loaded by buffer.php
    global $ALL_TEXT;
    $sets = is_array($ALL_TEXT) ? array_keys($ALL_TEXT) : [];
    sort($sets);
    ?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Slot Runner Playground</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; background:#f8f9fa; margin:0; padding:2em; color: #212529;}
form{max-width:600px; margin:auto; background:#fff; padding:2em; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.08);}
h2{margin-top:0; color: #007bff;}
label{display:block; margin-top:1em; font-weight:600; margin-bottom: .4em;}
select,textarea,input[type=text]{width:100%; padding:.6em; border:1px solid #ced4da; border-radius:4px; font-family:monospace; font-size: 14px; box-sizing: border-box;}
select{appearance: none; background: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23007bff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') no-repeat right .75rem center/8px 10px; background-color: #fff;}
textarea{height:80px; line-height: 1.5;}
button{margin-top:1.5em; padding:.7em 1.5em; border:none; border-radius:4px; background:#007bff; color:#fff; font-weight:bold; cursor:pointer; font-size: 1em; transition: background .2s;}
button:hover{background:#0056b3;}
#out{white-space:pre-wrap; background:#212529; color:#e9ecef; padding:1.5em; margin-top:2em; border-radius:6px; max-width:800px; margin-left:auto; margin-right:auto; font-size: 13px; max-height: 400px; overflow: auto;}
</style></head><body>
<form id="f">
    <h2>Quick Slot Runner</h2>
    <label for="set">Instruction Set</label>
    <select name="set" id="set">
        <option value="">-- Select a Set --</option>
        <?php foreach($sets as $s) echo "<option value=\"".htmlspecialchars($s)."\">".htmlspecialchars($s)."</option>";?>
    </select>
    <label for="cmds">Command IDs (e.g. 0, 2-4)</label>
    <input type="text" name="cmds" id="cmds" placeholder="e.g., 0, 2-4, 7">
    <label for="params">Dev Params (optional, key=value per line)</label>
    <textarea name="params" id="params" placeholder="e.g.&#10;user=admin&#10;debug=1"></textarea>
    <button type="submit">Run</button>
</form>
<pre id="out">Output will appear here...</pre>
<script>
const out = document.getElementById('out');
const f   = document.getElementById('f');
const btn = f.querySelector('button');

f.onsubmit = async e => {
    e.preventDefault();
    out.textContent = 'Running…';
    btn.disabled = true;
    btn.style.opacity = '0.7';

    // Construct query string from form data
    const formData = new FormData(f);
    const searchParams = new URLSearchParams();
    // Ensure required fields are present
    const setVal = formData.get('set');
    const cmdsVal = formData.get('cmds');

    if (!setVal) {
        out.textContent = 'Error: Please select an instruction set.';
        btn.disabled = false;
        btn.style.opacity = '1';
        return;
    }
     if (!cmdsVal) {
        out.textContent = 'Error: Please enter Command IDs.';
        btn.disabled = false;
        btn.style.opacity = '1';
        return;
    }

    searchParams.append('set', setVal);
    searchParams.append('cmds', cmdsVal);

    const devParams = formData.get('params');
    if (devParams && devParams.trim() !== '') {
        searchParams.append('params', devParams);
    }

    try {
        const response = await fetch(`execute.php?${searchParams.toString()}`);
        const data = await response.json();
        out.textContent = JSON.stringify(data, null, 2);
    } catch (err) {
        console.error("Fetch error:", err);
        out.textContent = `Error during fetch: ${err.message}\n\nCheck the browser console for more details.`;
    } finally {
         btn.disabled = false;
         btn.style.opacity = '1';
    }
};
</script></body></html>
<?php
    exit;
}

/*──────────────────────────────────────────────────────────────────────────*/
// 6) Validate required 'set' parameter
/*──────────────────────────────────────────────────────────────────────────*/

$setParam = $params['set'] ?? '';
if (!$setParam) {
    // This case should generally be caught by section 5 for HTTP
    // but useful for CLI or direct API calls missing the parameter.
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameter: set',
        'run_at' => date(DATE_ATOM),
        'php_errors' => $PHP_ERRORS // Include any errors occurred so far
        ]);
    exit;
}

// Locate the instruction set file
$setFile = null;
// Allow absolute paths as set parameter? For local dev maybe, but risky.
// Let's restrict to basename resolution within instructionSets dir for now.
$setName = basename((string)$setParam);
$potentialPath = $root . '/instructionSets/' . $setName;

if (strpos($setName, '..') !== false || $setName === '') {
     http_response_code(400);
     echo json_encode([
         'error' => 'Invalid set name specified.',
         'run_at' => date(DATE_ATOM),
         'php_errors' => $PHP_ERRORS
         ]);
     exit;
}

// Check using preloaded $ALL_TEXT from buffer.php if available
global $ALL_TEXT;
if (isset($ALL_TEXT) && array_key_exists($setName, $ALL_TEXT)) {
    $setFile = $potentialPath;
} else {
    // Fallback check if $ALL_TEXT wasn't populated correctly (e.g., buffer error)
    if (is_file($potentialPath) && is_readable($potentialPath)) {
         $setFile = $potentialPath;
         // Optionally load content here if needed later, though run_slot uses buffer's version
    }
}


if ($setFile === null) {
    http_response_code(404);
    echo json_encode([
        'error' => "Instruction set '{$setName}' not found or not readable.",
        'run_at' => date(DATE_ATOM),
        'php_errors' => $PHP_ERRORS
        ]);
    exit;
}

// We have a valid set name ($setName) and potentially $setFile path (though $setName is primary key)

/*──────────────────────────────────────────────────────────────────────────*/
// 7) Validate command IDs and decide single vs batch run
/*──────────────────────────────────────────────────────────────────────────*/

$cmdsParam = $params['cmds'] ?? null;
$cmdParam = $params['cmd'] ?? null;

$slotIds = [];
if ($cmdsParam !== null && $cmdsParam !== '') {
    $slotIds = parse_ids((string)$cmdsParam);
} elseif ($cmdParam !== null && ctype_digit((string)$cmdParam)) {
    $slotIds = [(int)$cmdParam];
}

if (empty($slotIds)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing or invalid command IDs. Provide ?cmds=... (e.g., 0,2-4) or ?cmd=... (e.g., 5).',
        'set'   => $setName,
        'run_at' => date(DATE_ATOM),
        'php_errors' => $PHP_ERRORS
        ]);
    exit;
}

/*──────────────────────────────────────────────────────────────────────────*/
// 8) Execute the requested slots
/*──────────────────────────────────────────────────────────────────────────*/

$results = [];
foreach ($slotIds as $slotId) {
    // Pass $devParams to run_slot for potential use inside slot code
    $results[] = run_slot($setName, $slotId, $devParams);
}

/*──────────────────────────────────────────────────────────────────────────*/
// 9) Emit final JSON response
/*──────────────────────────────────────────────────────────────────────────*/

// Check for fatal errors captured by shutdown handler that might prevent JSON output
$hasFatal = false;
foreach ($PHP_ERRORS as $err) {
    if (!empty($err['fatal'])) {
        $hasFatal = true;
        break;
    }
}

// If headers haven't been sent AND no fatal error occurred preventing output
if (!headers_sent() && !$hasFatal) {
    header('Content-Type: application/json');
    echo json_encode([
        'set'       => $setName,
        'run_at'    => date(DATE_ATOM),
        'results'   => $results,
        // Optionally include all accumulated PHP errors (warnings etc.) at the top level
        // 'all_php_errors' => $PHP_ERRORS // Uncomment if needed, but errors are also in results[n].php_errors
    ], JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
} elseif (!$hasFatal) {
     // Headers already sent, likely by an error before output buffering or JSON output started
     // Log this situation if possible
     error_log("execute.php: Headers already sent before final JSON output. Check for errors or premature output.");
     // Cannot send JSON response now.
}
// If $hasFatal, the shutdown handler already attempted to send a JSON error response.

exit;