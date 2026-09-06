# Test technique 

## Technologies utilisées

- PHP 8.3
- Symfony 7
- PostgreSQL
- Doctrine ORM
- Twig
- HTML / CSS
- JavaScript

## Prérequis

Avant de lancer le projet, il est nécessaire d'avoir installé :

- PHP 8.3
- Composer
- PostgreSQL
- Symfony CLI (optionnel mais recommandé)

## Installation

Cloner le dépôt :

```bash
git clone https://github.com/BYecir02 Badirou_Mohamed_Yecir_Test_Technique_DEPP.git
cd Badirou_Mohamed_Yecir_Test_Technique_DEPP
```

Installer les dépendances :

```bash
composer install
```

## Configuration de la base de données

Créer un fichier `.env.local` à la racine du projet.

Configurer la variable `DATABASE_URL` avec vos identifiants PostgreSQL :

```env
DATABASE_URL="postgresql://postgres:mot_de_passe@127.0.0.1:5432/test_cisad?serverVersion=17&charset=utf8"
```

Adapter si nécessaire :

- le nom d'utilisateur PostgreSQL ;
- le mot de passe ;
- la version de PostgreSQL.

La base de données attendue par le projet est :

```text
test_cisad
```

Créer la base de données si elle n'existe pas :

```bash
php bin/console doctrine:database:create
```

Exécuter les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

## Création d'un administrateur

Une commande Symfony permet de créer un compte administrateur :

```bash
php bin/console app:create-admin
```

La commande demande :

- un nom d'utilisateur ;
- une adresse e-mail ;
- un mot de passe.

Le mot de passe est saisi de manière masquée puis hashé avant son enregistrement en base de données.

Aucun compte administrateur avec des identifiants prédéfinis n'est fourni dans le projet.

## Lancer l'application

Avec Symfony CLI :

```bash
symfony serve
```

Puis ouvrir :

```text
http://127.0.0.1:8000
```

La page d'accueil redirige automatiquement vers la page de connexion.

## Authentification

L'application propose :

- une page d'inscription ;
- une page de connexion ;
- une déconnexion ;
- une gestion des accès selon le rôle de l'utilisateur.

La connexion est effectuée avec le **nom d'utilisateur** et le mot de passe.

Lors d'une inscription publique, le compte créé reçoit automatiquement le rôle :

```text
ROLE_USER
```

Les mots de passe sont hashés avant leur enregistrement en base de données.

## Rôles

Deux rôles sont utilisés dans l'application.

### ROLE_USER

Un utilisateur peut :

- se connecter ;
- consulter son propre profil ;
- consulter ses informations personnelles ;
- se déconnecter.

### ROLE_ADMIN

Un administrateur dispose également des droits d'un utilisateur.

Il peut en plus :

- consulter la liste des utilisateurs ;
- créer un utilisateur ;
- consulter un utilisateur ;
- modifier un utilisateur ;
- supprimer un utilisateur ;
- gérer les informations associées aux utilisateurs ;
- importer plusieurs utilisateurs à partir d'un fichier CSV.

## Gestion des utilisateurs

Un utilisateur possède les informations suivantes :

- `id`
- `username`
- `email`
- `password`
- `roles`

Le nom d'utilisateur est unique.

Lors de la modification d'un utilisateur par un administrateur, le changement du mot de passe est facultatif.

Si aucun nouveau mot de passe n'est renseigné, le mot de passe actuel est conservé.

## Informations utilisateur

La table `infos` contient :

- `id`
- `rank`
- `victoire`
- `defaite`
- `user_id`

Chaque enregistrement `Infos` est associé à un utilisateur avec une relation **OneToOne**.

Un utilisateur ne peut donc posséder qu'un seul ensemble d'informations.

## Import CSV

Les administrateurs peuvent importer plusieurs utilisateurs depuis un fichier CSV.

Le séparateur utilisé est le point-virgule `;`.

Le fichier doit contenir exactement les colonnes suivantes :

```csv
username;email;password;roles;rank;victoire;defaite
```

Exemple :

```csv
username;email;password;roles;rank;victoire;defaite
user_01;user01@test.fr;qwerty123;ROLE_USER;Silver;5;5
user_02;user02@test.fr;qwerty123;ROLE_USER;Iron;0;10
admin_01;admin01@test.fr;qwerty123;ROLE_ADMIN;;;
```

Les rôles acceptés sont :

```text
ROLE_USER
ROLE_ADMIN
```

Les champs suivants sont facultatifs :

```text
rank
victoire
defaite
```

Cependant, lorsqu'une information de classement est renseignée, les trois champs doivent être présents ensemble.

Par exemple :

```csv
user_03;user03@test.fr;qwerty123;ROLE_USER;Gold;6;4
```

est valide.

Alors que :

```csv
user_03;user03@test.fr;qwerty123;ROLE_USER;Gold;;
```

est considéré comme invalide.

### Prévisualisation avant import

Avant l'enregistrement en base de données, le fichier est analysé.

L'administrateur peut voir :

- les utilisateurs valides ;
- les lignes invalides ;
- la raison d'une erreur ;
- le nombre de lignes valides et invalides.

Les mots de passe ne sont jamais affichés dans la prévisualisation.

### Sélection multiple

Les lignes valides peuvent être sélectionnées individuellement avant l'import.

Une option permet également de sélectionner ou désélectionner tous les utilisateurs valides.

Les lignes invalides ne peuvent pas être sélectionnées.

Seuls les utilisateurs sélectionnés sont enregistrés en base de données après confirmation.

### Sécurité de l'import

Lors de l'import :

- le format du fichier est vérifié ;
- les champs obligatoires sont contrôlés ;
- les adresses e-mail sont validées ;
- les rôles sont contrôlés ;
- les doublons de nom d'utilisateur sont détectés ;
- les mots de passe sont hashés ;
- les lignes invalides ne sont pas importées ;
- une vérification CSRF protège la confirmation de l'import.

## Routes principales

Les principales pages de l'application sont :

```text
/                       Accueil
/connexion              Connexion
/inscription            Inscription
/profil                  Profil utilisateur
/admin/utilisateurs      Gestion des utilisateurs
/admin/informations      Gestion des informations
/admin/utilisateurs/import
                        Import CSV
```

Les routes situées sous `/admin` sont réservées aux utilisateurs possédant le rôle `ROLE_ADMIN`.

## Vérifications du projet

Quelques commandes utiles pour vérifier le projet :

```bash
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
php bin/console debug:router
```

## Fonctionnalités supplémentaires

En complément des fonctionnalités demandées, l'application intègre notamment :

- une prévisualisation du fichier CSV avant import ;
- l'affichage des erreurs ligne par ligne ;
- la sélection multiple des utilisateurs à importer ;
- un compteur dynamique des utilisateurs sélectionnés ;
- une confirmation sécurisée de l'import ;
- une commande dédiée à la création d'un administrateur.

## Auteur

**Badirou Mohamed Yecir**

