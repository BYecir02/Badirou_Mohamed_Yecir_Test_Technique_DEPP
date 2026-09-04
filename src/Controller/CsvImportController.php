<?php

namespace App\Controller;

use App\Entity\Infos;
use App\Entity\User;
use App\Form\CsvImportType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CsvImportController extends AbstractController
{
    #[Route('/admin/utilisateurs/import', name: 'app_csv_import', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
    ): Response {
        $form = $this->createForm(CsvImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('csvFile')->getData();

            $handle = fopen($file->getPathname(), 'r');

            if ($handle === false) {
                $this->addFlash('error', 'Impossible de lire le fichier CSV.');

                return $this->redirectToRoute('app_csv_import');
            }

            $header = fgetcsv($handle, 0, ';', '"', '');

            if ($header === false) {
                fclose($handle);

                $this->addFlash('error', 'Le fichier CSV est vide.');

                return $this->redirectToRoute('app_csv_import');
            }

            // Suppression éventuelle du BOM UTF-8.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];

            $expectedHeader = [
                'username',
                'email',
                'password',
                'roles',
                'rank',
                'victoire',
                'defaite',
            ];

            if ($header !== $expectedHeader) {
                fclose($handle);

                $this->addFlash(
                    'error',
                    'Le format du fichier CSV ne correspond pas au format attendu.'
                );

                return $this->redirectToRoute('app_csv_import');
            }

            $usersToCreate = [];
            $infosToCreate = [];
            $usernamesInFile = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                $lineNumber++;

                // Ignore une éventuelle ligne complètement vide.
                if (count(array_filter(
                    $row,
                    fn ($value) => trim((string) $value) !== ''
                )) === 0) {
                    continue;
                }

                if (count($row) !== 7) {
                    fclose($handle);

                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : le nombre de colonnes est invalide."
                    );

                    return $this->redirectToRoute('app_csv_import');
                }
                [
                    $username,
                    $email,
                    $password,
                    $role,
                    $rank,
                    $victoire,
                    $defaite,
                ] = array_map('trim', $row);
                if ($username === '' || $email === '' || $password === '' || $role === '') {
                    fclose($handle);

                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : username, email, password et roles sont obligatoires."
                    );

                    return $this->redirectToRoute('app_csv_import');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    fclose($handle);

                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : l'adresse e-mail est invalide."
                    );

                    return $this->redirectToRoute('app_csv_import');
                }
                if (strlen($password) < 6) {
                    fclose($handle);
                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : le mot de passe doit contenir au moins 6 caractères."
                    );
                    return $this->redirectToRoute('app_csv_import');
                }

                if (!in_array($role, ['ROLE_USER', 'ROLE_ADMIN'], true)) {
                    fclose($handle);

                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : le rôle doit être ROLE_USER ou ROLE_ADMIN."
                    );

                    return $this->redirectToRoute('app_csv_import');
                }
                if (
                    isset($usernamesInFile[$username])
                    || $userRepository->findOneBy(['username' => $username])
                ) {
                    fclose($handle);

                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : le nom d'utilisateur \"$username\" existe déjà."
                    );
                    return $this->redirectToRoute('app_csv_import');
                }

                $hasInfos = $rank !== '' || $victoire !== '' || $defaite !== '';

                if (
                    $hasInfos
                    && ($rank === '' || $victoire === '' || $defaite === '')
                ) {
                    fclose($handle);

                    $this->addFlash(
                        'error',
                        "Ligne $lineNumber : rank, victoire et defaite doivent être renseignés ensemble."
                    );
                    return $this->redirectToRoute('app_csv_import');
                }

                $usernamesInFile[$username] = true;

                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setRoles([$role]);
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $password)
                );

                $usersToCreate[] = $user;

                if ($hasInfos) {
                    $infos = new Infos();
                    $infos->setRank($rank);
                    $infos->setVictoire($victoire);
                    $infos->setDefaite($defaite);
                    $infos->setUser($user);

                    $infosToCreate[] = $infos;
                }
            }

            fclose($handle);

            if ($usersToCreate === []) {
                $this->addFlash('error', 'Aucun utilisateur à importer.');
                return $this->redirectToRoute('app_csv_import');
            }

            foreach ($usersToCreate as $user) {
                $entityManager->persist($user);
            }

            foreach ($infosToCreate as $infos) {
                $entityManager->persist($infos);
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                count($usersToCreate).' utilisateur(s) importé(s) avec succès.'
            );
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('csv_import/index.html.twig', [
            'form' => $form,
        ]);
    }
}