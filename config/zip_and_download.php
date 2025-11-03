<?php
$zipname = 'jailtrak.zip';
$dir = __DIR__ . '/jailtrak';

$zip = new ZipArchive;
if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
}

header('Content-Type: application/zip');
header('Content-disposition: attachment; filename=' . basename($zipname));
header('Content-Length: ' . filesize($zipname));
readfile($zipname);
unlink($zipname); // Clean up
exit;
?>