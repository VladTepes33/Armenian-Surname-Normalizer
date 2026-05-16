<?php

require_once 'fonctions_2.php'; // included armenianNormalizer_v1.php

$tests = [
    "Hagopian"        => "agopian",
    "Der Hagopian"    => "agopian",
    "Hagopyan"        => "agopian",
    "Petrossian"      => "petrosian",
    "Petrosian"       => "petrosian",
    "Krikorian"       => "krikorian",
    "Grigorian"       => "grigorian", 
    "Terzibachian"    => "terzibakian",
    "Terzibajian"     => "terzibakian",
    "Frenkian"        => "frenkian",
    "Frenghian"       => "frenkian",
    "Manoogian"       => "manukian",
    "Manouchian"      => "manukian",
];

$success = 0;
$total = count($tests);

foreach ($tests as $input => $expected) {
    $result = normalize_armenian_v7($input);

    if ($result === $expected) {
        echo "[OK] $input -> $result <br>" ;
        $success++;
    } else {
        echo "[FAIL] $input -> $result (attendu: $expected)\n";
    }
}

echo "\nRésultat: $success / $total tests réussis\n";
