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
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        /* Supprime une ancienne prévisualisation lors d'un retour sur la page. */
        if ($request->isMethod('GET')) {
            $request->getSession()->remove('csv_import_rows');
        }

        $form = $this->createForm(CsvImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('csvFile')->getData();

            $fileName = $file->getClientOriginalName();

            $handle = fopen($file->getPathname(), 'r');

            if ($handle === false) {
                $this->addFlash(
                    'error',
                    'Impossible de lire le fichier CSV.'
                );

                return $this->redirectToRoute('app_csv_import');
            }

            /* Lecture de l'en-tête. */
            $header = fgetcsv($handle, 0, ';', '"', '');

            if ($header === false) {
                fclose($handle);

                $this->addFlash(
                    'error',
                    'Le fichier CSV est vide.'
                );

                return $this->redirectToRoute('app_csv_import');
            }

            /* Suppression éventuelle du BOM UTF-8. */
            $header[0] = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                $header[0]
            ) ?? $header[0];

            $expectedHeader = [
                'username',
                'email',
                'password',
                'roles',
                'rank',
                'victoire',
                'defaite',
            ];

            /* Vérification du format de l'en-tête. */
            if ($header !== $expectedHeader) {
                fclose($handle);

                $this->addFlash(
                    'error',
                    'Le format du fichier CSV ne correspond pas au format attendu.'
                );

                return $this->redirectToRoute('app_csv_import');
            }

            /* Données affichées dans la prévisualisation. */
            $previewRows = [];

            /* Données valides conservées pour l'import final. */
            $importRows = [];

            $usernamesInFile = [];
            $lineNumber = 1;

            /* Analyse des lignes du fichier CSV. */
            while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                $lineNumber++;

                /* Ignore les lignes complètement vides. */
                if (count(array_filter(
                    $row,
                    fn ($value) => trim((string) $value) !== ''
                )) === 0) {
                    continue;
                }

                /* Vérification du nombre de colonnes. */
                if (count($row) !== 7) {
                    $previewRows[] = [
                        'line' => $lineNumber,
                        'username' => '',
                        'email' => '',
                        'role' => '',
                        'rank' => '',
                        'victoire' => '',
                        'defaite' => '',
                        'valid' => false,
                        'error' => 'Le nombre de colonnes est invalide.',
                    ];

                    continue;
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

                $error = null;

                /* Vérification des champs obligatoires. */
                if (
                    $username === ''
                    || $email === ''
                    || $password === ''
                    || $role === ''
                ) {
                    $error = 'Username, email, password et roles sont obligatoires.';
                }

                /* Validation de l'adresse e-mail. */
                if (
                    $error === null
                    && !filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {
                    $error = 'Adresse e-mail invalide.';
                }

                /* Validation minimale du mot de passe. */
                if (
                    $error === null
                    && strlen($password) < 6
                ) {
                    $error = 'Le mot de passe doit contenir au moins 6 caractères.';
                }

                /* Vérification du rôle. */
                if (
                    $error === null
                    && !in_array(
                        $role,
                        ['ROLE_USER', 'ROLE_ADMIN'],
                        true
                    )
                ) {
                    $error = 'Le rôle doit être ROLE_USER ou ROLE_ADMIN.';
                }

                /* Vérifie les doublons dans le fichier et dans la base de données. */
                if (
                    $error === null
                    && (
                        isset($usernamesInFile[$username])
                        || $userRepository->findOneBy([
                            'username' => $username,
                        ])
                    )
                ) {
                    $error = sprintf(
                        'Le nom d\'utilisateur "%s" existe déjà.',
                        $username
                    );
                }

                /* Les informations sont facultatives mais doivent être complètes. */
                $hasInfos =
                    $rank !== ''
                    || $victoire !== ''
                    || $defaite !== '';

                if (
                    $error === null
                    && $hasInfos
                    && (
                        $rank === ''
                        || $victoire === ''
                        || $defaite === ''
                    )
                ) {
                    $error = 'Rank, victoire et defaite doivent être renseignés ensemble.';
                }

                /* Mémorise les usernames déjà rencontrés dans le fichier. */
                if ($username !== '') {
                    $usernamesInFile[$username] = true;
                }

                /* Ajoute la ligne à la prévisualisation sans le mot de passe. */
                $previewRows[] = [
                    'line' => $lineNumber,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'rank' => $rank,
                    'victoire' => $victoire,
                    'defaite' => $defaite,
                    'valid' => $error === null,
                    'error' => $error,
                ];

                /* Prépare l'import uniquement si la ligne est valide. */
                if ($error === null) {
                    $userForHash = new User();

                    $userForHash->setUsername($username);
                    $userForHash->setEmail($email);
                    $userForHash->setRoles([$role]);

                    /* Le mot de passe est hashé avant d'être conservé en session. */
                    $passwordHash = $passwordHasher->hashPassword(
                        $userForHash,
                        $password
                    );

                    $importRows[] = [
                        'username' => $username,
                        'email' => $email,
                        'passwordHash' => $passwordHash,
                        'role' => $role,
                        'rank' => $rank,
                        'victoire' => $victoire,
                        'defaite' => $defaite,
                        'hasInfos' => $hasInfos,
                    ];
                }
            }

            fclose($handle);

            /* Vérifie que le fichier contient au moins un utilisateur. */
            if ($previewRows === []) {
                $this->addFlash(
                    'error',
                    'Le fichier CSV ne contient aucun utilisateur.'
                );

                return $this->redirectToRoute('app_csv_import');
            }

            /* Conserve temporairement les lignes valides pour la confirmation. */
            if ($importRows !== []) {
                $request->getSession()->set(
                    'csv_import_rows',
                    $importRows
                );
            } else {
                $request->getSession()->remove('csv_import_rows');
            }

            return $this->render('csv_import/index.html.twig', [
                'form' => $form,
                'previewRows' => $previewRows,
                'fileName' => $fileName,
            ]);
        }

        return $this->render('csv_import/index.html.twig', [
            'form' => $form,
            'previewRows' => null,
            'fileName' => null,
        ]);
    }

    #[Route(
        '/admin/utilisateurs/import/confirmer',
        name: 'app_csv_import_confirm',
        methods: ['POST']
    )]
    public function confirm(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
    ): Response {
        /* Vérification du token CSRF. */
        if (!$this->isCsrfTokenValid(
            'csv_import_confirm',
            $request->request->getString('_token')
        )) {
            $this->addFlash(
                'error',
                'La confirmation de l\'import est invalide.'
            );

            return $this->redirectToRoute('app_csv_import');
        }

        /* Récupère les lignes validées pendant la prévisualisation. */
        $importRows = $request->getSession()->get(
            'csv_import_rows',
            []
        );

        /* Récupère les utilisateurs sélectionnés dans la prévisualisation. */
        $selectedUsernames = $request->request->all('selectedUsernames');

        if ($importRows === []) {
            $this->addFlash(
                'error',
                'Aucun utilisateur n\'est disponible pour l\'import.'
            );

            return $this->redirectToRoute('app_csv_import');
        }

        if ($selectedUsernames === []) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner au moins un utilisateur.'
            );

            return $this->redirectToRoute('app_csv_import');
        }

        /* Conserve uniquement les utilisateurs sélectionnés. */
        $selectedRows = array_filter(
            $importRows,
            fn (array $row) => in_array(
                $row['username'],
                $selectedUsernames,
                true
            )
        );

        if ($selectedRows === []) {
            $this->addFlash(
                'error',
                'Aucun utilisateur valide n\'a été sélectionné.'
            );

            return $this->redirectToRoute('app_csv_import');
        }

        /* Vérifie à nouveau les doublons avant l'insertion. */
        foreach ($selectedRows as $row) {
            if ($userRepository->findOneBy([
                'username' => $row['username'],
            ])) {
                $request->getSession()->remove('csv_import_rows');

                $this->addFlash(
                    'error',
                    sprintf(
                        'Le nom d\'utilisateur "%s" existe déjà. Veuillez prévisualiser à nouveau le fichier.',
                        $row['username']
                    )
                );

                return $this->redirectToRoute('app_csv_import');
            }
        }

        /* Création des utilisateurs et de leurs informations éventuelles. */
        foreach ($selectedRows as $row) {
            $user = new User();

            $user->setUsername($row['username']);
            $user->setEmail($row['email']);
            $user->setRoles([$row['role']]);
            $user->setPassword($row['passwordHash']);

            $entityManager->persist($user);

            if ($row['hasInfos']) {
                $infos = new Infos();

                $infos->setRank($row['rank']);
                $infos->setVictoire($row['victoire']);
                $infos->setDefaite($row['defaite']);
                $infos->setUser($user);

                $entityManager->persist($infos);
            }
        }

        /* Un seul flush pour tout l'import. */
        $entityManager->flush();

        $importedCount = count($selectedRows);

        /* Supprime les données temporaires après l'import. */
        $request->getSession()->remove('csv_import_rows');

        $this->addFlash(
            'success',
            $importedCount.' utilisateur(s) importé(s) avec succès.'
        );

        return $this->redirectToRoute('app_user_index');
    }
}