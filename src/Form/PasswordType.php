<?php
namespace App\Form;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType as PasswordField;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
final class PasswordType extends AbstractType
{
    public function getBlockPrefix(): string { return 'change_password'; }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('current', PasswordField::class, ['label'=>'Mật khẩu hiện tại','trim'=>false, 'attr'=>['autocomplete'=>'current-password'], 'constraints'=>[new Assert\NotBlank(),new UserPassword(message:'Mật khẩu hiện tại không đúng.')]]);
        $builder->add('password', RepeatedType::class, ['type'=>PasswordField::class,'options'=>['trim'=>false], 'first_options'=>['label'=>'Mật khẩu mới','attr'=>['autocomplete'=>'new-password']], 'second_options'=>['label'=>'Nhập lại mật khẩu mới'], 'invalid_message'=>'Hai mật khẩu không trùng khớp.', 'constraints'=>[new Assert\NotBlank(),new Assert\Length(min:8),new Assert\Length(max:72,countUnit:Assert\Length::COUNT_BYTES)]]);
    }
}
