<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\AccountRepository;

/** Validate the checkout details supported by the current database schema. */
final class CheckoutService
{
    public function note(string $name, string $phone, string $address, string $shipping, string $payment, string $note): string
    {
        $name = trim($name);
        $phone = trim($phone);
        $address = trim($address);
        $note = trim($note);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new \DomainException('Họ tên người nhận bắt buộc nhập và tối đa 100 ký tự.');
        }
        $phone = AccountRepository::phone($phone);
        if ($address === '' || mb_strlen($address) > 200) {
            throw new \DomainException('Địa chỉ giao hàng bắt buộc nhập và tối đa 200 ký tự.');
        }
        if ($shipping !== 'standard') {
            throw new \DomainException('Hiện chỉ hỗ trợ giao hàng tiêu chuẩn miễn phí.');
        }
        if ($payment !== 'cod') {
            throw new \DomainException('Hiện chỉ hỗ trợ thanh toán khi nhận hàng (COD).');
        }
        if (mb_strlen($note) > 150) {
            throw new \DomainException('Lời nhắn tối đa 150 ký tự.');
        }

        $summary = "Người nhận: {$name} - {$phone} | Đ/C: {$address} | PTTT: COD | VC: Tiêu chuẩn";
        if ($note !== '') { $summary .= ' | Ghi chú: '.$note; }
        // The legacy schema has one NVARCHAR(255) field for these details.
        // SQL Server counts UTF-16 code units, including surrogate pairs.
        $length = strlen(iconv('UTF-8', 'UTF-16LE', $summary)) / 2;
        if ($length > 255) {
            throw new \DomainException('Thông tin nhận hàng và lời nhắn vượt giới hạn 255 ký tự lưu trữ. Vui lòng rút gọn lời nhắn hoặc địa chỉ; dữ liệu chưa được lưu.');
        }

        return $summary;
    }
}
