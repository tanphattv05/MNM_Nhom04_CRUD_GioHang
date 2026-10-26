<?php
namespace App\Form;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\{TextType, EmailType, PasswordType, RepeatedType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
final class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['name'=>['Họ và tên',120], 'phone'=>['Số điện thoại',20], 'address'=>['Địa chỉ',255]] as $key=>[$label,$max]) {
            $constraints = [new Assert\NotBlank(message:'Vui lòng nhập thông tin.'), new Assert\Length(max:$max)];
            if ($key === 'phone') { $constraints[] = new Assert\Regex(pattern:'/^[+0-9 ().-]{8,20}$/', message:'Số điện thoại không hợp lệ.'); }
            $builder->add($key, TextType::class, ['label'=>$label, 'constraints'=>$constraints, 'attr'=>['maxlength'=>$max]]);
        }
        if ($options['register']) {
            $builder->add('email', EmailType::class, ['label'=>'Email', 'constraints'=>[new Assert\NotBlank(),new Assert\Email(message:'Email không đúng định dạng.'),new Assert\Length(max:150)]]);
            $builder->add('password', RepeatedType::class, ['type'=>PasswordType::class, 'first_options'=>['label'=>'Mật khẩu','attr'=>['autocomplete'=>'new-password']], 'second_options'=>['label'=>'Nhập lại mật khẩu'], 'invalid_message'=>'Hai mật khẩu không trùng khớp.', 'constraints'=>[new Assert\NotBlank(),new Assert\Length(min:8),new Assert\Length(max:72,countUnit:Assert\Length::COUNT_BYTES)], 'options'=>['trim'=>false]]);
        }
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['register'=>false]); $resolver->setAllowedTypes('register','bool'); }
}
