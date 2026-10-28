<?php
namespace App\Tests;

use App\Service\CheckoutService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutServiceTest extends TestCase
{
    public function testCompleteRecipientDetailsArePreserved(): void
    {
        $note = (new CheckoutService())->note(' Nguyễn An ', '0901234567', ' 12 Đường Sách, TP.HCM ', 'standard', 'cod', ' Gọi trước khi giao ');
        self::assertStringContainsString('Nguyễn An - 0901234567', $note);
        self::assertStringContainsString('Đ/C: 12 Đường Sách, TP.HCM', $note);
        self::assertStringEndsWith('Ghi chú: Gọi trước khi giao', $note);
    }

    public static function invalidDetails(): iterable
    {
        yield 'missing name' => ['name', ''];
        yield 'long name' => ['name', str_repeat('A', 101)];
        yield 'punctuation phone' => ['phone', '.........'];
        yield 'short phone with separators' => ['phone', '1 (2) 3-4'];
        yield 'misplaced plus' => ['phone', '090+1234567'];
        yield 'too many digits' => ['phone', '1234567890123456'];
        yield 'nine digits' => ['phone', '090123456'];
        yield 'eleven digits' => ['phone', '09012345678'];
        yield 'international format' => ['phone', '+84901234567'];
        yield 'separator' => ['phone', '090 123 4567'];
        yield 'missing address' => ['address', ''];
        yield 'long address' => ['address', str_repeat('A', 201)];
        yield 'unsupported shipping' => ['shipping', 'express'];
        yield 'unknown shipping' => ['shipping', 'free'];
        yield 'unsupported payment' => ['payment', 'vnpay'];
        yield 'unknown payment' => ['payment', 'paid'];
        yield 'long message' => ['note', str_repeat('A', 151)];
        yield 'combined details too long' => ['address', str_repeat('Đ', 200)];
        yield 'UTF-16 storage length' => ['address', str_repeat('📚', 100)];
    }

    #[DataProvider('invalidDetails')]
    public function testInvalidCheckoutIsRejected(string $field, string $value): void
    {
        $details = ['name'=>'Nguyễn An', 'phone'=>'0901234567', 'address'=>'12 Đường Sách', 'shipping'=>'standard', 'payment'=>'cod', 'note'=>''];
        $details[$field] = $value;
        $this->expectException(\DomainException::class);
        (new CheckoutService())->note(...array_values($details));
    }
}
