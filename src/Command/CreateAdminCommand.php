<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un compte administrateur',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $io->ask('Nom d’utilisateur');

        if (!$username) {
            $io->error('Le nom d’utilisateur est obligatoire.');

            return Command::FAILURE;
        }

        if ($this->userRepository->findOneBy(['username' => $username])) {
            $io->error('Ce nom d’utilisateur existe déjà.');

            return Command::FAILURE;
        }

        $email = $io->ask('Adresse e-mail');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Veuillez saisir une adresse e-mail valide.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Mot de passe');

        if (!$password || strlen($password) < 6) {
            $io->error('Le mot de passe doit contenir au moins 6 caractères.');

            return Command::FAILURE;
        }

        $admin = new User();
        $admin->setUsername($username);
        $admin->setEmail($email);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, $password)
        );

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('Le compte administrateur a été créé.');

        return Command::SUCCESS;
    }
}