<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('vnd', [$this, 'format'])];
    }

    /** Display whole VND, rounding half up using decimal strings only.
     * $minorUnits accepts the integer hundredths already used by the draft cart.
     * This never changes the stored value or any monetary calculation.
     */
    public function format(mixed $amount, bool $minorUnits = false): string
    {
        if ((!is_string($amount) && !is_int($amount)) ||
            !preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', (string)$amount, $parts)) {
            return 'Chưa có giá';
        }
        $whole = $parts[2];
        $fraction = $parts[3] ?? '';
        if ($minorUnits) {
            // No division through float, including for totals above 2^53.
            if ($fraction !== '') { return 'Chưa có giá'; }
            $digits = str_pad($whole, 3, '0', STR_PAD_LEFT);
            $whole = substr($digits, 0, -2);
            $fraction = substr($digits, -2);
        }
        $whole = ltrim($whole, '0') ?: '0';
        if ($fraction !== '' && $fraction[0] >= '5') {
            $carry = true;
            for ($i = strlen($whole) - 1; $i >= 0 && $carry; --$i) {
                if ($whole[$i] === '9') { $whole[$i] = '0'; }
                else { $whole[$i] = (string)((int)$whole[$i] + 1); $carry = false; }
            }
            if ($carry) { $whole = '1'.$whole; }
        }
        $sign = $parts[1] === '-' && $whole !== '0' ? '-' : '';
        return $sign.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).' ₫';
    }
}
