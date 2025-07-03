<pre>
<?php
define('__OHCRUD_DEBUG_EXPANDED_LEVEL__', 3);
error_reporting(E_ERROR | E_PARSE);
include('./vendor/fellowgeek/php-ref/ref.php');

function tailJsonLogWithTotal(string $filePath, int $limit = 10, int $offset = 0, int &$totalRecords = 0): array {
    $fp = fopen($filePath, 'r');
    if (!$fp) {
        throw new RuntimeException("Unable to open log file: $filePath");
    }

    $bufferSize = 4096;
    $pos = -1;
    $lines = [];
    $currentLine = '';
    $foundLines = 0;

    fseek($fp, 0, SEEK_END);
    $fileSize = ftell($fp);

    while ($fileSize + $pos > 0) {
        $seekSize = min($bufferSize, $fileSize + $pos);
        $pos -= $seekSize;
        fseek($fp, $pos, SEEK_END);
        $chunk = fread($fp, $seekSize);

        $currentLine = $chunk . $currentLine;

        $linesInChunk = explode("\n", $currentLine);
        $currentLine = array_shift($linesInChunk); // Save partial line for next round

        foreach (array_reverse($linesInChunk) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            $totalRecords++; // Count every valid log line

            if ($foundLines < ($limit + $offset)) {
                $lines[] = $trimmed;
                $foundLines++;
            }
        }
    }

    // Handle final remaining line
    $trimmed = trim($currentLine);
    if ($trimmed !== '') {
        $totalRecords++;
        if ($foundLines < ($limit + $offset)) {
            $lines[] = $trimmed;
        }
    }

    fclose($fp);

    // Apply offset and limit, and decode JSON
    $result = array_slice($lines, $offset, $limit);
    return array_values(array_filter(array_map('json_decode', $result)));
}


$total  = 0;
$logs = tailJsonLogWithTotal('/logs/app.log', 10, 320, $total); // Get the last 5 entries
r($total);
foreach ($logs as $log) {
    r($log); // or use as needed
}