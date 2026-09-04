<?php

namespace App\Form;

use App\Entity\Infos;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InfosType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rank', TextType::class, [
                'label' => 'Rang',
            ])
            ->add('victoire', TextType::class, [
                'label' => 'Victoires',
            ])
            ->add('defaite', TextType::class, [
                'label' => 'Défaites',
            ]);

        if (!$options['is_edit']) {
            $builder->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'label' => 'Utilisateur',
                'placeholder' => 'Choisir un utilisateur',
                'query_builder' => function (UserRepository $repository) {
                    return $repository
                        ->createQueryBuilder('u')
                        ->leftJoin('u.infos', 'i')
                        ->andWhere('i.id IS NULL')
                        ->orderBy('u.username', 'ASC');
                },
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Infos::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}