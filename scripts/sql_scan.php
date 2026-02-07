<?php
// Simple scanner to find potential SQL injection spots by pattern matching
$root = __DIR__ . '/../';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$patterns = [
    // direct interpolation inside quotes: "...$var..." or '...$var...'
    '/\"[^\"]*\$[a-zA-Z_][a-zA-Z0-9_]*[^\"]*\"/m',
    '/\'[^\']*\$[a-zA-Z_][a-zA-Z0-9_]*[^\']*\'/m',
    // concatenation into $query or other SQL variables
    '/\$query\s*\./m',
    '/\$query\s*\.\=/',
    // pattern: AND something = '$var'
    '/=\s*\'\$[a-zA-Z_][a-zA-Z0-9_]*\'/m',
    // pattern: " AND p.category = '$category'"
    '/=\s*\"\$[a-zA-Z_][a-zA-Z0-9_]*\"/m',
    // direct ->query with string containing $
    '/->query\s*\(\s*\"[^\"]*\$[a-zA-Z_][a-zA-Z0-9_]*[^\"]*\"/m',
];

$files = [];
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (stripos($path, '/vendor/') !== false) continue;
    if (!preg_match('/\.php$/i', $path)) continue;
    $files[] = $path;
}

$results = [];
foreach ($files as $file) {
    $lines = file($file);
    foreach ($lines as $num => $line) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                $results[] = [
                    'file' => $file,
                    'line' => $num+1,
                    'match' => trim($line),
                    'pattern' => $pattern,
                ];
                break;
            }
        }
    }
}

if (empty($results)) {
    echo "No potential SQL interpolation issues found by the scanner.\n";
    exit(0);
}

// Group by file
$grouped = [];
foreach ($results as $r) {
    $grouped[$r['file']][] = $r;
}

foreach ($grouped as $file => $items) {
    echo "\nFile: $file\n";
    foreach ($items as $it) {
        echo "  Line {$it['line']}: {$it['match']}\n";
    }
}

echo "\nScanner finished. Review the lines above and convert to prepared statements or validate inputs where appropriate.\n";

?>