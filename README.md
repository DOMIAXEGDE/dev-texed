# dev-texed
A PHP local dev Sandbox for XAMPP

# Dev Slot Sandbox - Local Development Environment Documentation (v3.1)

## 1. Introduction

### 1.1. Characterization & Purpose
The Dev Slot Sandbox is characterized as a lightweight, local-first PHP development environment designed for rapid prototyping and execution of discrete code units, termed "slots". Its primary purpose is to provide a streamlined workflow for developing, testing, managing, and executing small, focused PHP code blocks within a structured context, complete with a web-based UI and optional persistent storage for CSV-formatted output[cite: 1, 5]. It facilitates iterative development by isolating code execution into manageable units.

### 1.2. Scope & Context
This system is intended for local development scenarios, typically running under a standard XAMPP (Apache + PHP) stack on a developer's machine[cite: 1]. It is particularly useful for tasks involving data processing, script automation, API interaction prototyping, and situations where executing distinct PHP logic snippets repeatedly is required. It is *not* inherently designed for production deployment without significant hardening (e.g., authentication, robust input validation, enhanced security).

### 1.3. Key Attributes
* **Modular Execution:** Code is organized into "slots" within "instruction sets"[cite: 3].
* **Web Interface:** Provides a browser-based UI for managing instruction sets/slots and executing them[cite: 6, 7].
* **API & CLI Execution:** Slots can be executed via HTTP GET requests or directly from the command line[cite: 10, 12].
* **Output Capturing:** Captures standard output, return values, and PHP errors/warnings/notices from slot execution[cite: 10, 12].
* **CSV Archiving:** Automatically detects and archives output resembling CSV data into a sharded file structure[cite: 10, 15].
* **Minimal Dependencies:** Relies primarily on PHP and a web server environment like XAMPP.

## 2. System Components

The system fundamentally consists of four PHP files:

1.  **`index.php`**:
    * **Constituents:** HTML structure, CSS styling, client-side JavaScript logic, server-side PHP for AJAX request handling.
    * **Function:** Serves the single-page web UI, provides AJAX endpoints for file/slot management (Create, Read, Update, Delete operations via `$_POST['editor_action']`), and acts as the primary interface for triggering slot execution via `execute.php`[cite: 6, 7]. It initiates asynchronous calls to `execute.php` and displays the resulting JSON output.
2.  **`execute.php`**:
    * **Constituents:** PHP logic for parameter parsing (CLI/HTTP), slot execution, output buffering, error handling, CSV detection, and JSON response generation.
    * **Function:** The core execution engine. It receives requests (via HTTP GET or CLI arguments) specifying an instruction set and slot(s) to run. It utilizes `buffer.php` to load slot code, executes the code using `eval()`, captures all output and errors, interacts with `filesystem.php` to archive CSV data, and always returns results formatted as a JSON object. Provides a fallback HTML playground if accessed via HTTP without parameters.
3.  **`buffer.php`**:
    * **Constituents:** Shared PHP functions, constants, polyfills (PHP < 8), and instruction set registry logic.
    * **Function:** Provides essential shared functionality required by both `index.php` (for listing/loading slots in the UI) and `execute.php` (for retrieving slot code before execution). Key responsibilities include pre-loading instruction set files (`*.txt`) into memory (`$ALL_TEXT`, `$ALL_LINES`), providing the `get_slot_code()` function, ensuring PHP < 8 compatibility, and managing shared environment settings (like error reporting levels). It is explicitly required by both `index.php` and `execute.php`.
4.  **`filesystem.php`**:
    * **Constituents:** The `FileSystem` PHP class with static methods for file path calculation, storage, and ingestion. Includes optional CLI helper commands.
    * **Function:** Manages the persistent storage of CSV output. It deterministically maps a logical "slug" (often derived from the set name, slot number, and timestamp) to a sharded directory structure (e.g., `csv/aa/bb/cc/`) based on an MD5 hash[cite: 16]. Provides methods (`storeCsv`, `ingest`) to save data and manages associated JSON metadata sidecar files. `execute.php` calls its methods to archive detected CSV output.

## 3. Directory Structure

A recommended project organization within your XAMPP `htdocs` directory (e.g., `C:/xampp/htdocs/`):

```
htdocs/
└── dev-sandbox/
    ├── index.php           # Main UI and AJAX endpoint
    ├── execute.php         # Slot execution engine (HTTP/CLI)
    ├── buffer.php          # Shared functions and registry (REQUIRED)
    ├── filesystem.php      # CSV storage manager (REQUIRED for CSV archiving)
    │
    ├── instructionSets/    # Auto-created; stores your *.txt instruction set files
    │   └── exampleSet.txt  # Example instruction set file
    │   └── anotherSet.txt
    │
    └── csv/                # Auto-created by filesystem.php; stores archived CSVs
        └── aa/             # Shard directory level 1 (hexadecimal)
            └── bb/         # Shard directory level 2 (hexadecimal)
                └── cc/     # Shard directory level 3 (hexadecimal)
                    └── exampleSet_0_20250425_120000.csv     # Archived CSV
                    └── exampleSet_0_20250425_120000.json    # Metadata sidecar
```

* **`instructionSets/`**: Automatically created on first run by `buffer.php` (if permissions allow). Contains `.txt` files, each representing an instruction set. Slots are defined within these files using `// slot <number>` comments[cite: 3].
* **`csv/`**: Automatically created by `filesystem.php` when the first CSV is archived[cite: 16, 17]. Stores CSV files in a 3-level sharded structure based on the MD5 hash of the slug to ensure efficient directory listings even with a large number of files.

## 4. Prerequisites

* **XAMPP Environment:** A working XAMPP installation providing Apache and PHP (tested on PHP 7.4+ and PHP 8.x)[cite: 4].
* **PHP Configuration:** Standard PHP configuration usually suffices. Ensure `allow_url_fopen` is enabled if slots need to fetch external URLs (though `curl` is generally preferred).
* **Write Permissions:** The web server process (Apache) must have write permissions on the `dev-sandbox/` directory and its subdirectories (`instructionSets/`, `csv/`) to allow automatic creation and file writing[cite: 5, 21].

## 5. Setup and Configuration

1.  **Copy Files:** Place the four PHP files (`index.php`, `execute.php`, `buffer.php`, `filesystem.php`) into your chosen project directory (e.g., `htdocs/dev-sandbox/`)[cite: 21].
2.  **Set Permissions:** Ensure the `dev-sandbox/` directory is writable by the Apache user. On Linux/macOS, this might involve `chown` or `chmod`. On Windows with XAMPP, default permissions are often sufficient, but verify if issues arise.
3.  **Verify `buffer.php`:** Double-check that `buffer.php` contains *only* PHP code, starting with `<?php` and **omitting** the final closing `?>` tag. Ensure no whitespace exists before the opening tag or after the last line of code.
4.  **Restart Apache:** Restart the Apache service through the XAMPP control panel to ensure all settings are applied[cite: 22].
5.  **Access UI:** Open your web browser and navigate to `http://localhost/dev-sandbox/` (or `http://localhost/dev-sandbox/index.php`)[cite: 22].

## 6. Component Usage and Details

### 6.1. `index.php` - Web UI

* **Execute Tab:** [cite: 6]
    * **Instruction Set:** Dropdown list of available `.txt` files found in `instructionSets/`. Populated via an AJAX call handled by `index.php` itself, which uses functions from `buffer.php`.
    * **Command IDs:** Text input accepting comma-separated slot numbers and ranges (e.g., `0`, `1-3`, `5,8,10-12`). Parsed by JavaScript.
    * **Dev Params:** Text area for key-value pairs (one per line, e.g., `user=admin\ndebug=1`). These are passed as URL parameters to `execute.php` and injected into the `$_REQUEST` superglobal during slot execution by `execute.php`. Useful for passing configuration or test data to slots.
    * **Run Button:** Initiates an asynchronous `fetch` request to `execute.php` with the selected set, commands, and dev params.
    * **Output Console:** Displays the JSON response received from `execute.php`. Includes robust handling to display raw text if the response is not valid JSON.
* **File Browser Tab:** [cite: 7]
    * Provides UI controls (buttons, dropdowns, text areas) for managing instruction sets and slots.
    * **Set Management:** Create new `.txt` files, Delete existing sets.
    * **Slot Management:** View slots within a set, Create new empty slots, Delete existing slots, Edit slot code in a text area (with basic Tab key support), Save changes.
    * **Bulk Operations:** Create or Delete multiple slots based on a range/list input (e.g., `5-10,15`)[cite: 24].
    * **Interaction:** All actions trigger AJAX POST requests back to `index.php` itself, identified by the `editor_action` parameter (e.g., `list_sets`, `load_slot`, `save_slot`, `bulk_create_slots`). `index.php` then performs the necessary file operations within the `instructionSets/` directory.

### 6.2. `execute.php` - Slot Runner

* **Inputs:**
    * **HTTP GET:** Expects `?set=<filename.txt>` and either `?cmd=<id>` or `?cmds=<id_list>`. Optionally accepts `&params=<url_encoded_key_value_pairs>`.
    * **CLI:** Expects arguments `php execute.php <filename.txt> <id_list> [param1=value1] [param2=value2] ...`.
* **Process:**
    1.  Unifies CLI arguments and HTTP parameters.
    2.  Requires `buffer.php` (fatal error if missing/invalid).
    3.  Optionally requires `filesystem.php`.
    4.  Validates the `set` parameter and resolves the instruction set file path.
    5.  Parses the `cmd`/`cmds` parameter into a list of integer slot IDs using `parse_ids()`.
    6.  Iterates through the requested slot IDs.
    7.  For each slot ID, calls `run_slot()`:
        * Injects Dev Params into `$_REQUEST`.
        * Calls `get_slot_code()` (from `buffer.php`) to retrieve the slot's code.
        * Executes the code using `eval()` within an output buffer (`ob_start`/`ob_get_clean`).
        * Appends any non-null return value from the slot code to the captured output.
        * Captures PHP errors/warnings/notices occurring during execution using a custom error handler.
        * Calls `looks_like_csv()` to check if the output resembles CSV data (first non-blank line contains a comma).
        * If CSV is detected and `filesystem.php` is loaded, calls `archive_csv()` which in turn uses `FileSystem::storeCsv()`.
        * Restores original `$_REQUEST`.
        * Returns a result array containing `slot`, `output`, and optionally `error`, `php_errors`, or `csv` metadata.
    8.  Aggregates results from all executed slots.
    9.  Sets the `Content-Type: application/json` header.
    10. Encodes the final aggregated data (set name, timestamp, results array) into a JSON string using `json_encode()`.
    11. Echoes the JSON string as the response body.
* **Outputs:** Always responds with a JSON object, even on errors (returning an `error` key or `php_errors` within results). Includes captured PHP errors/warnings in a `php_errors` array within each slot's result. Includes a `csv` object with archive metadata if CSV output was detected and stored.
* **Error Handling:** Uses `set_error_handler` and `register_shutdown_function` to capture almost all PHP errors (including notices, warnings, fatal errors, exceptions) and incorporate them into the JSON response rather than breaking the output.

### 6.3. `buffer.php` - Shared Runtime

* **Purpose:** Centralizes code and data needed by both the web UI (`index.php`) and the execution engine (`execute.php`). Ensures consistency.
* **Key Functions:**
    * **Preloads Sets:** Reads all `.txt` files from `instructionSets/` on initialization and stores their full content in `$ALL_TEXT` and line-by-line arrays in `$ALL_LINES`. This avoids repeated file reads during execution or UI interaction.
    * **`get_slot_code(setName, slotId)`:** Extracts the specific code block for a given slot from the preloaded text in `$ALL_TEXT` using regular expressions. Returns the code string or `null` if not found.
    * **Polyfills:** Includes function definitions for `str_contains`, `str_starts_with`, `str_ends_with` if the PHP version is less than 8.0.
    * **Error Reporting:** Configures PHP error reporting levels (typically `E_ALL` and `display_errors=1` for local development).
* **Usage:** Included via `require_once` at the beginning of both `index.php` and `execute.php`. Defines a `BFR_LOADED` constant to prevent redundant loading attempts (though `require_once` typically handles this).
* **Important:** Must contain *only* PHP code and **omit** the final `?>` closing tag to prevent premature output issues.

### 6.4. `filesystem.php` - CSV Store

* **Purpose:** Provides a simple, scalable mechanism for storing arbitrary CSV data generated by slots, without requiring a database[cite: 15].
* **Sharding:** Uses the first `LEVELS * 2` hexadecimal characters of the MD5 hash of a "slug" to create a nested directory structure (e.g., `csv/ab/cd/ef/`)[cite: 16]. This distributes files across many directories, preventing performance issues with very large numbers of files in a single directory.
* **Methods:**
    * `FileSystem::pathFor(slug, ensure=true)`: Calculates the full, absolute path for a given slug (e.g., `/path/to/dev-sandbox/csv/ab/cd/ef/mySlug.csv`). Creates the shard directories if `ensure` is true. Throws exceptions on invalid slugs or directory creation failures.
    * `FileSystem::storeCsv(csvContent, slug)`: Saves the provided string `$csvContent` to the path determined by `$slug`. Automatically calls `pathFor` to ensure directories exist. Writes a `.json` sidecar file containing metadata (slug, bytes, timestamp).
    * `FileSystem::ingest(sourceCsvPath)`: Moves an existing file from `$sourceCsvPath` into the sharded hierarchy based on its filename. Handles filename collisions by appending `_N` to the slug. Also creates a metadata sidecar.
    * `FileSystem::urlFor(slug, baseUrl='/csv')`: Generates a relative URL path suitable for accessing the file via HTTP, assuming the `csv/` directory (or a parent) is web-accessible.
* **CLI Utility:** Provides commands for basic maintenance:
    * `php filesystem.php init`: Pre-creates the first level (00-ff) of shard directories.
    * `php filesystem.php seed <file...>`: Ingests existing CSV files into the store.
    * `php filesystem.php path <slug>`: Calculates and prints the canonical path for a slug without creating directories.
    * `php filesystem.php url <slug> [baseUrl]`: Calculates and prints the relative URL for a slug.

## 7. Best Practices

* **Version Control:** Keep your `instructionSets/` directory under version control (e.g., Git) to track changes to your slot code[cite: 23].
* **Small Slots:** Design slots to be small, focused, and potentially composable units of work[cite: 23]. Avoid overly complex logic within a single slot.
* **Use Dev Params:** Leverage the "Dev Params" feature in the UI or CLI arguments to pass configuration (API keys, file paths, flags) into your slots instead of hardcoding them[cite: 24]. Access them within slot code via `$_REQUEST['paramName']`.
* **CSV Archiving:** Utilize the automatic CSV archiving for any slot that produces tabular, comma-separated data. The stored files and metadata provide a useful execution history[cite: 25].
* **Idempotency:** Where possible, design slots to be idempotent (running them multiple times with the same input produces the same result).
* **Clear Naming:** Use descriptive names for instruction sets (`.txt` files) and meaningful numbers for slots.

## 8. Troubleshooting

* **Empty `instructionSets/` UI:** If the dropdowns in `index.php` are empty, ensure the `instructionSets/` directory exists and is readable by Apache. Check that `buffer.php` has permissions to create it if it was missing[cite: 26]. Verify `buffer.php` itself is loading correctly (no PHP errors).
* **CSV Not Archiving:**
    * Verify `filesystem.php` exists and is readable by Apache[cite: 26].
    * Confirm `execute.php` is successfully including `filesystem.php` (check for warnings in `php_errors` output).
    * Check permissions on the `csv/` directory and its subdirectories[cite: 26].
    * Ensure the slot output actually resembles CSV (first non-blank line contains a comma).
* **PHP Errors Not Showing in UI Console:** `execute.php` captures errors and returns them within the `php_errors` key in the JSON response[cite: 27]. Check the browser's developer console (for the raw JSON) or the `outputConsole` field in the UI. Direct PHP error output to the browser is suppressed by `execute.php`.
* **JSON Parsing Errors (JavaScript `SyntaxError`):** This usually indicates premature output before the JSON begins. Most commonly caused by whitespace or text outside `<?php ... ?>` tags (especially before `<?php` or after `?>`) in `buffer.php` or, less likely, `execute.php`. Ensure the final `?>` is omitted in `buffer.php`. Use the browser's Network tab to inspect the raw response from `execute.php`.
* **`Call to undefined function` Errors:** Ensure the required file (e.g., `buffer.php`) is correctly included via `require_once` *before* the function is called. Verify the function definition exists and is correctly spelled in the included file and the calling file.
* **404 Error on `execute.php`:** Ensure the URL is correct and `execute.php` exists in the specified location. If using CLI, invoke via `php execute.php ...`, not via a browser or `curl` without parameters[cite: 28].
* **Permission Denied Errors:** Usually related to file/directory write permissions for the Apache user on `instructionSets/` or `csv/`.

## 9. Potential Enhancements

* **Authentication:** Implement an authentication/authorization layer in `index.php` to restrict access to slot creation, editing, and execution[cite: 31].
* **Static Serving/CDN:** Configure Apache (or Nginx) to serve the `csv/` directory directly, or sync archived CSVs to a CDN for efficient public access[cite: 31]. Use `FileSystem::urlFor()` to generate appropriate links.
* **Unit Testing:** Add PHPUnit tests, particularly around `buffer.php` (`get_slot_code`) and `execute.php`'s CSV detection (`looks_like_csv`) and parameter parsing logic[cite: 32].
* **Webhooks:** Modify `execute.php` to optionally send a notification (webhook) to an external service after each run, potentially including the result summary or CSV metadata[cite: 32].
* **Input Validation:** Add more stringent input validation within slots, especially for data coming from `$_REQUEST` (Dev Params).
* **Alternative Storage:** Replace `filesystem.php` with a different implementation (e.g., using AWS S3, Google Cloud Storage, a database blob store) by maintaining the same public static method signatures (`pathFor`, `storeCsv`, `ingest`, `urlFor`).

```php
<?php
/*───────────────────────────────
  Local Dev Version – Single‑file index.php
  (Assumes buffer.php is present for shared logic)
────────────────────────────────*/

// ─── Initialization ─────────────────────────────────────────────────────
session_start();
$setsDir = __DIR__ . '/instructionSets';
if (!is_dir($setsDir)) {
    mkdir($setsDir, 0755, true);
}
header('Access-Control-Allow-Origin: *');

// ─── Include Shared Buffer Logic ────────────────────────────────────────
require_once __DIR__ . '/buffer.php';

// ─── Helpers (originally x000000007) ────────────────────────────────────
function jsonReply($a) {
    header('Content-Type: application/json');
    echo json_encode($a);
    exit;
}
function slot_pattern($slot) {
    // Use the get_slot_code function's regex logic indirectly or reimplement if needed,
    // but ideally buffer.php should provide necessary parsing if this level needs it.
    // For now, keep the original simple pattern for matching start comments.
    return '#^//\s*(?:slot|command)\s+' . preg_quote((string)$slot, '#') . '\R#m';
}
function sanitize_set($s) {
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $s);   // safe file name
}
function parse_ids($spec) {
    $out = [];
    foreach (explode(',', $spec) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        // Allow various dash types for ranges
        $p = str_replace(['‑','–','—'], '-', $p);
        if (strpos($p, '-') !== false) {
            [$a, $b] = array_map('intval', explode('-', $p, 2));
            if ($b >= $a) { // Ensure correct order for range
                $out = array_merge($out, range($a, $b));
            }
        } else {
            $out[] = (int) $p;
        }
    }
    sort($out); // Ensure ascending order
    return array_values(array_unique($out));
}

// ─── AJAX Editor Actions (originally x000000008) ────────────────────────
if (isset($_REQUEST['editor_action'])) {
    $a = $_REQUEST['editor_action'];
    $set = isset($_REQUEST['set']) ? basename($_REQUEST['set']) : null;
    $path = $set ? "$setsDir/$set" : null;

    /* LIST SETS */
    if ($a === 'list_sets') {
        jsonReply(['status' => 'ok', 'sets' => array_map('basename', glob("{$setsDir}/*.txt"))]);
    }

    /* CREATE & DELETE SET */
    if ($a === 'create_set' && !empty($_POST['set_name'])) {
        $name = sanitize_set($_POST['set_name']);
        if (!str_ends_with($name, '.txt')) $name .= '.txt';
        $newPath = "$setsDir/$name";
        if (is_file($newPath)) jsonReply(['status' => 'error', 'msg' => 'Set already exists']);
        // Create with a default slot 0
        if (file_put_contents($newPath, "// slot 0\n\n") === false) {
             jsonReply(['status' => 'error', 'msg' => 'Failed to create file. Check permissions.']);
        }
        jsonReply(['status' => 'ok', 'set' => $name]);
    }
    if ($a === 'delete_set' && $path) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        if (!unlink($path)) jsonReply(['status' => 'error', 'msg' => 'Failed to delete file. Check permissions.']);
        jsonReply(['status' => 'ok']);
    }

    /* LIST SLOTS */
    if ($a === 'list_slots' && $path) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $content = file_get_contents($path);
        // Use the regex from get_slot_code (assuming it's loaded via buffer.php)
        // or a similar one to find all slot definitions reliably.
        preg_match_all('/^\s*\/\/\s*(?:slot|command)\s+(\d+)/m', $content, $m);
        $slots = array_map('intval', $m[1]);
        sort($slots);
        jsonReply(['status' => 'ok', 'slots' => array_values(array_unique($slots))]);
    }

    /* LOAD SLOT CODE */
    if ($a === 'load_slot' && $path && isset($_REQUEST['slot'])) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $slot = (int) $_REQUEST['slot'];
        // Use the function loaded from buffer.php
        $code = get_slot_code($set, $slot);
        if ($code !== null) {
            jsonReply(['status' => 'ok', 'code' => $code]);
        } else {
            jsonReply(['status' => 'error', 'msg' => 'Slot not found in set']);
        }
    }

    /* SAVE SLOT CODE */
     if ($a === 'save_slot' && $path && isset($_POST['slot'], $_POST['code'])) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $slot = (int) $_POST['slot'];
        $code = rtrim($_POST['code']);
        $text = file_get_contents($path);
        $block = "// slot $slot\n$code\n";

        // More robust pattern to find the specific slot block
        $pattern = '/(^\s*\/\/\s*(?:slot|command)\s+' . preg_quote((string)$slot, '/') . '\s*\R)([\s\S]*?)(?=(^\s*\/\/\s*(?:slot|command)\s+\d+\s*\R)|\z)/m';

        if (preg_match($pattern, $text)) {
             // Replace existing block - use preg_replace_callback for safety with $ replacement chars
             $text = preg_replace_callback($pattern, function($matches) use ($block) {
                 return $block;
             }, $text, 1);
        } else {
            // Append new block, ensuring newline separation
            $text = rtrim($text) . "\n\n" . $block;
        }

        if (file_put_contents($path, $text) === false) {
            jsonReply(['status' => 'error', 'msg' => 'Failed to save file. Check permissions.']);
        }
        jsonReply(['status' => 'ok']);
    }


    /* CREATE SINGLE SLOT */
    if ($a === 'create_slot' && $path && isset($_POST['slot'])) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $slot = (int) $_POST['slot'];
        $txt  = file_get_contents($path);
        // Check if slot already exists using a reliable pattern
        $exists_pattern = '/^\s*\/\/\s*(?:slot|command)\s+' . preg_quote((string)$slot, '/') . '\s*\R/m';
        if (preg_match($exists_pattern, $txt)) {
            jsonReply(['status' => 'error', 'msg' => 'Slot already exists']);
        }
        // Append new empty slot, ensure newline separation
        $new_block = "// slot $slot\n\n";
        $txt = rtrim($txt) . "\n\n" . $new_block;
        if (file_put_contents($path, $txt) === false) {
            jsonReply(['status' => 'error', 'msg' => 'Failed to write file. Check permissions.']);
        }
        jsonReply(['status' => 'ok']);
    }

    /* DELETE SINGLE SLOT */
    if ($a === 'delete_slot' && $path && isset($_POST['slot'])) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $slot = (int) $_POST['slot'];
        $txt  = file_get_contents($path);
        // Pattern to find the whole block for deletion
        $pattern = '/(^\s*\/\/\s*(?:slot|command)\s+' . preg_quote((string)$slot, '/') . '\s*\R)([\s\S]*?)(?=(^\s*\/\/\s*(?:slot|command)\s+\d+\s*\R)|\z)/m';
        if (!preg_match($pattern, $txt)) {
             jsonReply(['status' => 'error', 'msg' => 'Slot not found']);
        }
        // Remove the matched block, including potential leading/trailing whitespace from removal
        $txt = preg_replace($pattern, '', $txt, 1);
        $txt = trim($txt);

        if (file_put_contents($path, $txt . "\n") === false) { // Ensure file ends with newline
            jsonReply(['status' => 'error', 'msg' => 'Failed to write file. Check permissions.']);
        }
        jsonReply(['status' => 'ok']);
    }

    /* BULK SLOT CREATE */
    if ($a === 'bulk_create_slots' && $path && isset($_POST['slots'])) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $ids = parse_ids($_POST['slots']);
        if (empty($ids)) jsonReply(['status' => 'error', 'msg' => 'No valid slot IDs provided']);

        $txt = file_get_contents($path);
        $existing_slots_text = $txt;
        $created = [];
        $skipped = [];
        $added_content = "";

        // Find existing slots first
        preg_match_all('/^\s*\/\/\s*(?:slot|command)\s+(\d+)/m', $existing_slots_text, $m);
        $existing_slot_nums = array_map('intval', $m[1] ?? []);

        foreach ($ids as $slot) {
            if (in_array($slot, $existing_slot_nums)) {
                $skipped[] = $slot;
                continue;
            }
            // Add to temporary string to append at the end
            $added_content .= "// slot $slot\n\n";
            $created[] = $slot;
        }

        if (!empty($created)) {
             $txt = rtrim($txt) . "\n\n" . trim($added_content) . "\n"; // Append all new slots
             if (file_put_contents($path, $txt) === false) {
                 jsonReply(['status' => 'error', 'msg' => 'Failed to write file during bulk create. Check permissions.']);
             }
        }
        jsonReply(['status' => 'ok', 'created' => $created, 'skipped' => $skipped]);
    }

    /* BULK SLOT DELETE */
    if ($a === 'bulk_delete_slots' && $path && isset($_POST['slots'])) {
        if (!is_file($path)) jsonReply(['status' => 'error', 'msg' => 'Set not found']);
        $ids = parse_ids($_POST['slots']);
         if (empty($ids)) jsonReply(['status' => 'error', 'msg' => 'No valid slot IDs provided']);

        $txt = file_get_contents($path);
        $original_txt = $txt;
        $deleted = [];
        $missing = [];

        // Find existing slots first
        preg_match_all('/^\s*\/\/\s*(?:slot|command)\s+(\d+)/m', $txt, $m);
        $existing_slot_nums = array_map('intval', $m[1] ?? []);

        $slots_to_delete = [];
        foreach ($ids as $slot) {
            if (in_array($slot, $existing_slot_nums)) {
                 $slots_to_delete[] = $slot;
                 $deleted[] = $slot;
            } else {
                $missing[] = $slot;
            }
        }

        if (!empty($slots_to_delete)) {
            // Build a regex to match any of the blocks to delete
            $delete_pattern = '/(^\s*\/\/\s*(?:slot|command)\s+(?:' . implode('|', array_map('preg_quote', $slots_to_delete)) . ')\s*\R)([\s\S]*?)(?=(^\s*\/\/\s*(?:slot|command)\s+\d+\s*\R)|\z)/m';
            $txt = preg_replace($delete_pattern, '', $txt);
            $txt = trim($txt);

            if (file_put_contents($path, $txt . "\n") === false) {
                jsonReply(['status' => 'error', 'msg' => 'Failed to write file during bulk delete. Check permissions.']);
            }
        }
        jsonReply(['status' => 'ok', 'deleted' => $deleted, 'missing' => $missing]);
    }

    // Fallback for unknown actions
    jsonReply(['status' => 'error', 'msg' => 'Unknown or invalid editor action']);
}

// ─── HTML+JS Client (originally x000000009-x000000011) ──────────────────
$sets = array_map('basename', glob("{$setsDir}/*.txt"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dev Slot Sandbox</title>
    <style>
        body{font-family:sans-serif;background:#f4f4f4;margin:0;padding:1em;}
        .tabs{max-width:950px;margin:1em auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
        .tab-links{display:flex;margin:0;padding:0;list-style:none;background:#eee;border-bottom: 1px solid #ddd;}
        .tab-links li{flex:1;margin:0;}
        .tab-links a{display:block;padding:1em;text-align:center;text-decoration:none;color:#333;border-bottom: 3px solid transparent; transition: background .2s, border-color .2s;}
        .tab-links a:hover{background:#e0e0e0;}
        .tab-links a.active{background:#fff;font-weight:bold;border-bottom-color: #007acc;}
        .tab-content{padding:1.5em}.tab-content>div{display:none}.tab-content>div.active{display:block}
        label{display:block;margin-top:1em;margin-bottom:.3em;font-weight:600;color:#333;}
        select,textarea,input[type=text]{width:100%;font-family:'Consolas', 'Monaco', 'Courier New', monospace;font-size:14px;padding:.6em;border:1px solid #ccc;border-radius:4px;background:#fff;box-sizing:border-box;margin-top:.2em;}
        textarea{line-height:1.5;}
        #editorCode{height:350px;background:#2b2b2b;color:#f8f8f2;border:1px solid #444;font-size:13px;tab-size: 4;}
        #outputConsole{background:#222;color:#eee;padding:1em;min-height:150px;max-height: 400px; overflow:auto;margin-top:1em;border-radius:4px;font-size:13px;white-space:pre-wrap; word-wrap: break-word;}
        button{padding:.6em 1.2em;font-size:1em;border:none;border-radius:4px;background:#007acc;color:#fff;font-weight:bold;cursor:pointer;margin-top:.5em;transition: background .2s;}
        button:hover{background:#005fa3;}
        .small-btn{padding:.35em .8em;font-size:.9em;}
        .button-group{display:flex;gap:.5em;align-items:center;flex-wrap: wrap;}
        #editorMsg{margin-left:1em;color:green;font-weight: bold;}
    </style>
</head>
<body>
<div class="tabs" id="tabs">
    <h1>Dev Slot Sandbox</h1>
    <ul class="tab-links">
        <li><a href="#exec" class="active">Execute</a></li>
        <li><a href="#browse">File Browser</a></li>
    </ul>
    <div class="tab-content">
        <div id="exec" class="active">
            <form id="execForm">
                <label for="runSet">Instruction Set</label>
                <select name="set" id="runSet">
                    <?php foreach ($sets as $s) echo "<option value=\"".htmlspecialchars($s)."\">".htmlspecialchars($s)."</option>"; ?>
                </select>

                <label for="runCmds">Command IDs</label>
                <input type="text" name="cmds" id="runCmds" placeholder="e.g., 0, 2-5, 7">

                <label for="runParams">Dev Params (optional)</label>
                <textarea name="params" id="runParams" rows="3" placeholder="key=value per line (passed to execute.php)"></textarea>

                <button type="submit">Run Selected Slots</button>
            </form>
            <label for="outputConsole">Output</label>
            <div id="outputConsole">Awaiting execution...</div>
        </div>

        <div id="browse">
            <label for="editorSet">Instruction Sets</label>
            <div class="button-group">
                <select id="editorSet" style="flex:1"></select>
                <button id="newSetBtn" class="small-btn">New Set</button>
                <button id="delSetBtn" class="small-btn">Delete Set</button>
            </div>

            <label for="editorSlot">Slots in Selected Set</label>
            <div class="button-group">
                <select id="editorSlot" style="flex:1"></select>
                <button id="newSlotBtn" class="small-btn">New Slot</button>
                <button id="delSlotBtn" class="small-btn">Delete Slot</button>
            </div>

            <label for="bulkInput">Bulk Slot IDs</label>
             <input type="text" id="bulkInput" placeholder="e.g., 1, 4-10, 15">
            <div class="button-group" style="margin-top:.3em;">
                <button id="bulkCreateBtn" class="small-btn">Bulk Create Slots</button>
                <button id="bulkDeleteBtn" class="small-btn">Bulk Delete Slots</button>
            </div>

            <label for="editorCode">Slot Code</label>
            <textarea id="editorCode" spellcheck="false"></textarea>
            <button id="saveSlotBtn">Save Slot Code</button>
            <span id="editorMsg"></span>
        </div>
    </div>
</div>

<script>
// Helper function for API calls
const api = async (formData) => {
    try {
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('API call failed:', error);
        alert(`API Request Failed: ${error.message}`);
        return { status: 'error', msg: error.message };
    }
};

// Simple debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// DOM Elements Cache
const ui = {
    tabs: document.getElementById('tabs'),
    runSet: document.getElementById('runSet'),
    runCmds: document.getElementById('runCmds'),
    runParams: document.getElementById('runParams'),
    execForm: document.getElementById('execForm'),
    outputConsole: document.getElementById('outputConsole'),
    editorSet: document.getElementById('editorSet'),
    newSetBtn: document.getElementById('newSetBtn'),
    delSetBtn: document.getElementById('delSetBtn'),
    editorSlot: document.getElementById('editorSlot'),
    newSlotBtn: document.getElementById('newSlotBtn'),
    delSlotBtn: document.getElementById('delSlotBtn'),
    bulkInput: document.getElementById('bulkInput'),
    bulkCreateBtn: document.getElementById('bulkCreateBtn'),
    bulkDeleteBtn: document.getElementById('bulkDeleteBtn'),
    editorCode: document.getElementById('editorCode'),
    saveSlotBtn: document.getElementById('saveSlotBtn'),
    editorMsg: document.getElementById('editorMsg'),
    tabLinks: document.querySelectorAll('.tab-links a'),
    tabContents: document.querySelectorAll('.tab-content>div')
};

// --- UI Logic ---

// Tab Switching
ui.tabLinks.forEach(a => {
    a.onclick = e => {
        e.preventDefault();
        const targetId = a.getAttribute('href').substring(1);

        ui.tabLinks.forEach(link => link.classList.remove('active'));
        a.classList.add('active');

        ui.tabContents.forEach(div => {
            div.classList.toggle('active', div.id === targetId);
        });
    };
});

// Editor Functions
async function refreshSets(selectedSet = null) {
    const fd = new FormData();
    fd.append('editor_action', 'list_sets');
    const data = await api(fd);

    if (data.status !== 'ok') return;

    // Clear existing options
    ui.editorSet.innerHTML = '';
    ui.runSet.innerHTML = '';

    // Populate options
    const sets = data.sets || [];
    if (sets.length === 0) {
        ui.editorSet.innerHTML = '<option value="">-- No sets found --</option>';
        ui.runSet.innerHTML = '<option value="">-- No sets found --</option>';
        clearSlotEditor(); // Clear editor if no sets
    } else {
        sets.forEach(name => {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            ui.editorSet.appendChild(option.cloneNode(true));
            ui.runSet.appendChild(option);
        });

        // Select the desired set if provided, otherwise default to first
        ui.editorSet.value = selectedSet && sets.includes(selectedSet) ? selectedSet : sets[0];
        ui.runSet.value = ui.editorSet.value; // Sync run set
    }

    loadSlots(); // Load slots for the selected set
}

async function loadSlots(selectedSlot = null) {
    const set = ui.editorSet.value;
    ui.editorSlot.innerHTML = '';
    ui.editorCode.value = '';

    if (!set) {
        ui.editorSlot.innerHTML = '<option value="">-- Select a set --</option>';
        return;
    }

    const fd = new FormData();
    fd.append('editor_action', 'list_slots');
    fd.append('set', set);
    const data = await api(fd);

    if (data.status !== 'ok' || !data.slots || data.slots.length === 0) {
        ui.editorSlot.innerHTML = '<option value="">-- No slots found --</option>';
        return;
    }

    data.slots.forEach(num => {
        const option = document.createElement('option');
        option.value = num;
        option.textContent = num;
        ui.editorSlot.appendChild(option);
    });

    // Select the desired slot if provided and available, otherwise default to first
    ui.editorSlot.value = selectedSlot && data.slots.includes(parseInt(selectedSlot)) ? selectedSlot : data.slots[0];

    loadSlotCode();
}

async function loadSlotCode() {
    const set = ui.editorSet.value;
    const slot = ui.editorSlot.value;
    ui.editorCode.value = '';

    if (!set || slot === '') return;

    const fd = new FormData();
    fd.append('editor_action', 'load_slot');
    fd.append('set', set);
    fd.append('slot', slot);
    const data = await api(fd);

    if (data.status === 'ok') {
        ui.editorCode.value = data.code || '';
    } else {
        console.warn(`Could not load slot ${slot}: ${data.msg}`);
        // Optionally display error in UI
    }
}

function clearSlotEditor() {
    ui.editorSlot.innerHTML = '<option value="">-- Select a set --</option>';
    ui.editorCode.value = '';
}

function showEditorMessage(msg, isError = false) {
    ui.editorMsg.textContent = msg;
    ui.editorMsg.style.color = isError ? 'red' : 'green';
    setTimeout(() => ui.editorMsg.textContent = '', 2500);
}

// Event Listeners
window.addEventListener('DOMContentLoaded', () => {
    refreshSets();
    ui.editorSet.onchange = () => loadSlots();
    ui.editorSlot.onchange = loadSlotCode;

    // New Set Button
    ui.newSetBtn.onclick = async () => {
        const name = prompt('Enter new set name (e.g., my_set.txt):');
        if (!name || !name.trim()) return;
        const fd = new FormData();
        fd.append('editor_action', 'create_set');
        fd.append('set_name', name.trim());
        const data = await api(fd);
        if (data.status === 'ok') {
             showEditorMessage(`Set '${data.set}' created.`);
             refreshSets(data.set);
        } else {
             showEditorMessage(`Error: ${data.msg}`, true);
        }
    };

    // Delete Set Button
    ui.delSetBtn.onclick = async () => {
        const set = ui.editorSet.value;
        if (!set || !confirm(`Are you sure you want to delete the set "${set}"? This cannot be undone.`)) return;
        const fd = new FormData();
        fd.append('editor_action', 'delete_set');
        fd.append('set', set);
        const data = await api(fd);
         if (data.status === 'ok') {
             showEditorMessage(`Set '${set}' deleted.`);
             refreshSets();
        } else {
             showEditorMessage(`Error: ${data.msg}`, true);
        }
    };

    // New Slot Button
    ui.newSlotBtn.onclick = async () => {
        const set = ui.editorSet.value;
        if (!set) {
             showEditorMessage("Select a set first.", true);
             return;
        }
        const slot = prompt('Enter new slot number:');
        if (slot === null || !/^\d+$/.test(slot.trim())) {
             if (slot !== null) showEditorMessage("Invalid slot number.", true);
             return;
        }
        const fd = new FormData();
        fd.append('editor_action', 'create_slot');
        fd.append('set', set);
        fd.append('slot', slot.trim());
        const data = await api(fd);
        if (data.status === 'ok') {
            showEditorMessage(`Slot ${slot} created.`);
            loadSlots(slot);
        } else {
            showEditorMessage(`Error: ${data.msg}`, true);
        }
    };

    // Delete Slot Button
    ui.delSlotBtn.onclick = async () => {
        const set = ui.editorSet.value;
        const slot = ui.editorSlot.value;
        if (!set || slot === '' || !confirm(`Delete slot ${slot} from set "${set}"?`)) return;
        const fd = new FormData();
        fd.append('editor_action', 'delete_slot');
        fd.append('set', set);
        fd.append('slot', slot);
        const data = await api(fd);
        if (data.status === 'ok') {
            showEditorMessage(`Slot ${slot} deleted.`);
            loadSlots(); // Reload slots
        } else {
            showEditorMessage(`Error: ${data.msg}`, true);
        }
    };

    // Bulk Create Button
    ui.bulkCreateBtn.onclick = async () => {
        const set = ui.editorSet.value;
        const slots = ui.bulkInput.value.trim();
        if (!set || !slots) {
             showEditorMessage("Select a set and enter slot IDs.", true);
             return;
        }
        const fd = new FormData();
        fd.append('editor_action', 'bulk_create_slots');
        fd.append('set', set);
        fd.append('slots', slots);
        const data = await api(fd);
        if (data.status === 'ok') {
            let message = `Bulk Create: ${data.created?.length || 0} created.`;
            if (data.skipped?.length) message += ` ${data.skipped.length} skipped (already exist).`;
            showEditorMessage(message);
            loadSlots(data.created?.[0]);
        } else {
            showEditorMessage(`Error: ${data.msg}`, true);
        }
    };

    // Bulk Delete Button
    ui.bulkDeleteBtn.onclick = async () => {
        const set = ui.editorSet.value;
        const slots = ui.bulkInput.value.trim();
        if (!set || !slots || !confirm(`Delete slots [${slots}] from set "${set}"?`)) return;
        const fd = new FormData();
        fd.append('editor_action', 'bulk_delete_slots');
        fd.append('set', set);
        fd.append('slots', slots);
        const data = await api(fd);
        if (data.status === 'ok') {
            let message = `Bulk Delete: ${data.deleted?.length || 0} deleted.`;
            if (data.missing?.length) message += ` ${data.missing.length} skipped (not found).`;
            showEditorMessage(message);
            loadSlots();
        } else {
            showEditorMessage(`Error: ${data.msg}`, true);
        }
    };

    // Save Slot Button
    ui.saveSlotBtn.onclick = async () => {
        const set = ui.editorSet.value;
        const slot = ui.editorSlot.value;
        const code = ui.editorCode.value;
        if (!set || slot === '') {
             showEditorMessage("Select a set and slot to save.", true);
             return;
        }
        const fd = new FormData();
        fd.append('editor_action', 'save_slot');
        fd.append('set', set);
        fd.append('slot', slot);
        fd.append('code', code);
        const data = await api(fd);
        if (data.status === 'ok') {
            showEditorMessage(`Slot ${slot} saved successfully.`);
        } else {
            showEditorMessage(`Error saving slot ${slot}: ${data.msg}`, true);
        }
    };

    // Execute Form Submission
    ui.execForm.onsubmit = async (e) => {
        e.preventDefault();
        ui.outputConsole.textContent = 'Executing...';
        const formData = new FormData(ui.execForm);
        const params = new URLSearchParams(formData).toString();
		
        try {
			// In this snippet, we use try catch block to check if text is valid JSON
			// and display it differently in the console.
			const response = await fetch(`execute.php?${params}`);
			const text = await response.text();
			try {
				const data = JSON.parse(text);
				ui.outputConsole.textContent = JSON.stringify(data, null, 2);
			} catch (jsonError) {
				ui.outputConsole.textContent = `Invalid JSON:\n${text}`;
			}

        } catch (error) {
            console.error('Execution failed:', error);
            ui.outputConsole.textContent = `Execution Request Failed:\n${error}\n\nCheck browser console and PHP error logs for details. Is execute.php accessible and returning valid JSON?`;
        }
    };

    // Enable TAB key in textarea
    ui.editorCode.addEventListener('keydown', e => {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = ui.editorCode.selectionStart;
            const end = ui.editorCode.selectionEnd;
            // Insert tab character at cursor position
            ui.editorCode.value = ui.editorCode.value.substring(0, start) + '\t' + ui.editorCode.value.substring(end);
            // Move cursor position
            ui.editorCode.selectionStart = ui.editorCode.selectionEnd = start + 1;
        }
    });

});
</script>

</body>
</html>
```

```php
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
```

```php
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
```

```php
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
```
