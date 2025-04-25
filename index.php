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