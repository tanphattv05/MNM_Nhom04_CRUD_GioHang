<?php
namespace App\Command;

use App\Repository\AccountRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputArgument,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name:'app:account:password',description:'Chuyển mật khẩu cũ hoặc đặt lại mật khẩu cho một tài khoản phát triển được chỉ định.')]
final class AccountPasswordCommand extends Command
{
    public function __construct(private AccountRepository $accounts, private UserPasswordHasherInterface $hasher, private KernelInterface $kernel) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addArgument('kind',InputArgument::REQUIRED,'user hoặc admin')->addArgument('id',InputArgument::REQUIRED,'Mã thành viên hoặc tên Admin')->addOption('convert-legacy',null,InputOption::VALUE_NONE,'Hash mật khẩu văn bản đang có')->addOption('apply',null,InputOption::VALUE_NONE,'Cho phép ghi thay đổi cho đúng tài khoản này');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input,$output);
        if ($this->kernel->getEnvironment() !== 'dev') { $io->error('Lệnh chỉ dành cho môi trường dev.'); return Command::FAILURE; }
        if (!in_array($input->getArgument('kind'),['admin','user'],true)) { $io->error('Loại tài khoản không hợp lệ.'); return Command::INVALID; }
        $user = $this->accounts->byId($input->getArgument('id'),$input->getArgument('kind') === 'admin');
        if (!$user) { $io->error('Không tìm thấy tài khoản.'); return Command::FAILURE; }
        $hashed = (bool)password_get_info($user->getPassword())['algo'];
        $io->text($hashed ? 'Mật khẩu hiện có đã được hash.' : 'Mật khẩu hiện có chưa được hash.');
        if (!$input->getOption('apply')) { $io->note('Chỉ kiểm tra. Sao lưu trước, thêm --apply để ghi thay đổi cho tài khoản được chỉ định.'); return Command::SUCCESS; }
        if ($input->getOption('convert-legacy')) {
            if ($hashed) { $io->success('Bỏ qua mật khẩu đã hash.'); return Command::SUCCESS; }
            $password = $user->getPassword();
        } else {
            if (!$input->isInteractive()) { $io->error('Cần nhập mật khẩu ẩn trong terminal tương tác.'); return Command::INVALID; }
            $password = $io->askHidden('Mật khẩu mới (8–72 ký tự)', static function ($value) {
                if (!is_string($value) || mb_strlen($value)<8 || strlen($value)>72) { throw new \RuntimeException('Mật khẩu phải có 8–72 ký tự và tối đa 72 byte.'); }
                return $value;
            });
            if ($password !== $io->askHidden('Nhập lại mật khẩu mới')) { $io->error('Mật khẩu không trùng khớp.'); return Command::INVALID; }
        }
        $this->accounts->changePassword($user,$this->hasher->hashPassword($user,$password));
        $io->success('Đã lưu mật khẩu hash cho một tài khoản.'); return Command::SUCCESS;
    }
}
