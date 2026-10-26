<?php
require dirname(__DIR__).'/vendor/autoload.php';
$extension = new App\Twig\MoneyExtension();
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader(['money'=>'{{ value|vnd(minor) }}']));
$twig->addExtension($extension);
$cases = [
    ['354000.00', false, '354.000 ₫'],
    [35400000, true, '354.000 ₫'],
    [null, false, 'Chưa có giá'],
    [0, false, '0 ₫'],
    [0, true, '0 ₫'],
    ['0.00', false, '0 ₫'],
    ['000354000.00', false, '354.000 ₫'],
    ['354000.49', false, '354.000 ₫'],
    ['354000.50', false, '354.001 ₫'],
    ['999999.99', false, '1.000.000 ₫'],
    [49, true, '0 ₫'],
    [50, true, '1 ₫'],
    ['-0.49', false, '0 ₫'],
    ['-354000.50', false, '-354.001 ₫'],
    ['99999999999999999999999999.99', false, '100.000.000.000.000.000.000.000.000 ₫'],
    ['900719925474099350', true, '9.007.199.254.740.994 ₫'],
    ['', false, 'Chưa có giá'],
    ['<script>', false, 'Chưa có giá'],
];
foreach ($cases as [$value, $minor, $expected]) {
    $original = $value;
    if ($twig->render('money', compact('value', 'minor')) !== $expected || $original !== $value) {
        throw new RuntimeException('Money formatting failed');
    }
}
echo count($cases)." formatting checks passed; no database access.\n";
