<?php
header('Content-Type: text/plain');
echo "=== Archivos en /var/www/html ===\n\n";
$files = scandir('/var/www/html');
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = '/var/www/html/' . $file;
    $type = is_dir($path) ? '[DIR]' : '[FILE]';
    echo "$type $file\n";
}

echo "\n=== Verificando install.php ===\n";
if (file_exists('/var/www/html/install.php')) {
    echo "✓ install.php existe\n";
    echo "Tamaño: " . filesize('/var/www/html/install.php') . " bytes\n";
} else {
    echo "✗ install.php NO existe\n";
}
