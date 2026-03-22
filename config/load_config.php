<?php

/**
 * Fichier : config/load_config.php
 */

$parts_dir = __DIR__ . '/parts/';

$mapping = [
    1 => 'A',
    2 => 'B',
    3 => 'C',
    4 => 'D',
    5 => 'E'
];

$decoded_parts = [];

foreach ($mapping as $part_num => $const_name) {
    $file = $parts_dir . 'part' . $part_num . '.php';
    if (!file_exists($file)) {
        die("Erreur de configuration : fragment part{$part_num} manquant.");
    }
    include $file;
    if (!defined($const_name)) {
        die("Erreur de configuration : constante $const_name non définie dans part{$part_num}.php");
    }
    $decoded = base64_decode(constant($const_name));
    if ($decoded === false) {
        die("Erreur de décodage Base64 pour le fragment part{$part_num}.");
    }
    $decoded_parts[] = $decoded;
}

$db_pass = implode('', $decoded_parts);

// Nettoyage immédiat des variables intermédiaires
unset($decoded_parts, $mapping, $part_num, $const_name, $file, $decoded);
