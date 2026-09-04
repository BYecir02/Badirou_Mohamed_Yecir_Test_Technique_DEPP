<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $options['data'];

        $currentRole = in_array('ROLE_ADMIN', $user->getRoles(), true)
            ? 'ROLE_ADMIN'
            : 'ROLE_USER';

        $passwordConstraints = [
            new Length(
                min: 6,
                minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
            ),
        ];

        if ($options['password_required']) {
            $passwordConstraints[] = new NotBlank(
                message: 'Veuillez saisir un mot de passe.',
            );
        }

        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d’utilisateur',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'data' => $currentRole,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['password_required']
                    ? 'Mot de passe'
                    : 'Nouveau mot de passe',
                'mapped' => false,
                'required' => $options['password_required'],
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => $passwordConstraints,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => true,
        ]);

        $resolver->setAllowedTypes('password_required', 'bool');
    }
}