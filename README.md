# Laravel Toth

**Un moteur de sauvegarde et d'archivage pour Laravel. Sauvegarde automatique, restauration, snapshots - avec un simple cron.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Pourquoi Laravel Toth ?](#pourquoi-laravel-toth-)
3. [Architecture et concepts clés](#architecture-et-concepts-clés)
4. [Configuration](#configuration)
5. [Découverte automatique des modèles](#découverte-automatique-des-modèles)
6. [Créer une sauvegarde](#créer-une-sauvegarde)
7. [Restaurer des données](#restaurer-des-données)
8. [Les observateurs automatiques](#les-observateurs-automatiques)
9. [Les tâches asynchrones](#les-tâches-asynchrones)
10. [Gestion des fichiers de backup](#gestion-des-fichiers-de-backup)
11. [Cas d'usage concrets](#cas-dusage-concrets)
12. [Intégration avec les cron jobs](#intégration-avec-les-cron-jobs)
13. [Référence des commandes](#référence-des-commandes)

---

## Installation

```bash
composer require andydefer/laravel-toth

php artisan vendor:publish --tag=toth-config
php artisan vendor:publish --tag=toth-migrations
php artisan migrate
```

**Prérequis :** PHP 8.1+ | Laravel 10.x, 11.x ou 12.x

---

## Pourquoi Laravel Toth ?

**Le problème :** Vous perdez des données critiques. Un développeur supprime un enregistrement par erreur. Une migration échoue. Une base de données est corrompue. Vous n'avez pas de solution simple pour revenir en arrière.

**La solution :** Laravel Toth. Un système d'archivage et de sauvegarde qui capture automatiquement l'état de vos modèles, stocke les données en base de données ET en fichiers, et permet de restaurer en un clic.

### Comparatif rapide

| Besoin | Backup DB | Soft Delete | Laravel Toth |
|--------|-----------|-------------|--------------|
| Capture l'état complet d'un modèle | ❌ | ❌ | ✅ |
| Historique des modifications | ❌ | ❌ | ✅ |
| Restauration avec ID original | ❌ | ❌ | ✅ |
| Sauvegarde en base ET en fichiers | ❌ | ❌ | ✅ |
| Asynchrone (ne bloque pas) | ❌ | ❌ | ✅ |
| Fonctionne avec UUID | ❌ | ❌ | ✅ |
| Automatique sur create/update/delete | ❌ | ❌ | ✅ |
| Découverte automatique des modèles | ❌ | ❌ | ✅ |

---

## Architecture et concepts clés

### Le noyau

Le package s'appuie sur **Laravel Directive** pour les commandes CLI et **Laravel Task** pour l'exécution asynchrone. Il utilise un **service central** (`ArchiveService`) qui orchestre toutes les opérations.

```
┌─────────────────────────────────────────────────────────────┐
│                      Application Laravel                    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              ArchiveService                         │    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌───────────┐  │    │
│  │  │ createOrUpd. │  │   backup     │  │ restore   │  │    │
│  │  └──────────────┘  └──────────────┘  └───────────┘  │    │
│  └─────────────────────────────────────────────────────┘    │
│                          │                                  │
│  ┌───────────────────────▼─────────────────────────────┐    │
│  │           Tâches asynchrones (laravel-task)         │    │
│  │  ┌──────────────────┐  ┌──────────────────────────┐ │    │
│  │  │UpdateOrCreateArc.│  │    BackupArchiveTask     │ │    │
│  │  └──────────────────┘  └──────────────────────────┘ │    │
│  │  ┌──────────────────┐  ┌──────────────────────────┐ │    │
│  │  │ RestoreArchive   │  │ UpdateOrCreateFromFile   │ │    │
│  │  └──────────────────┘  └──────────────────────────┘ │    │
│  └─────────────────────────────────────────────────────┘    │
│                          │                                  │
│  ┌───────────────────────▼─────────────────────────────┐    │
│  │              BackupFileHelper                       │    │
│  │        (Création des fichiers .php)                 │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Base de données                        │    │
│  │              Table : archives                       │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

**Les composants clés :**

| Composant | Description |
|-----------|-------------|
| `ArchiveService` | Service central orchestrant toutes les opérations |
| `Archive` | Modèle Eloquent représentant une archive |
| `ArchiveRepository` | Repository pour les opérations CRUD sur les archives |
| `BackupFileHelper` | Helper pour la création de fichiers de backup |
| `TothBackupDirective` | Commande CLI pour créer des sauvegardes |
| `TothRestoreDirective` | Commande CLI pour restaurer des données |
| `TothDiscoveryDirective` | Commande CLI pour découvrir automatiquement les modèles |

---

## Configuration

### Fichier de configuration

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=toth-config
```

### Configuration des modèles archivables

Ajoutez les modèles que vous souhaitez archiver automatiquement :

```php
// config/toth.php
'archivables' => [
    App\Models\User::class,
    App\Models\Post::class,
    App\Models\Comment::class,
],
```

### Paramètres avancés

| Clé | Description | Défaut |
|-----|-------------|--------|
| `backup_folder_path` | Dossier de stockage des backups | `storage_path('toth/backups')` |
| `task_delay_seconds` | Délai avant exécution d'une tâche | `5` |
| `max_attempts` | Nombre de tentatives en cas d'échec | `3` |
| `grace_period_seconds` | Période de grâce avant expiration | `60` |

---

## Découverte automatique des modèles

Laravel Toth propose une commande pour découvrir automatiquement tous vos modèles Eloquent et les ajouter à la configuration.

### Commande de découverte

```bash
# Découverte depuis le dossier app/Models (par défaut)
./bin/task toth:discover

# Découverte depuis plusieurs dossiers
./bin/task toth:discover [app.Models, app.Domain]

# Découverte depuis un dossier de test (notation % pour remonter)
./bin/task toth:discover [%%tests.Fixtures.Models]

# Utilisation des alias
./bin/task discover [app.Models]
./bin/task scan [app.Models]
```

### Fonctionnement

La commande scanne les dossiers spécifiés, analyse les fichiers PHP via l'AST (Abstract Syntax Tree), identifie les classes qui étendent `Illuminate\Database\Eloquent\Model`, et met à jour automatiquement le fichier `config/toth.php`.

### Exemple

```bash
# Avant la découverte
'archivables' => [
    App\Models\User::class,
]

# Après la découverte
./bin/task toth:discover [app.Models, modules.Admin.Models]

# Résultat
'archivables' => [
    App\Models\User::class,
    App\Models\Product::class,
    App\Models\Order::class,
    Modules\Admin\Models\AdminUser::class,
    Modules\Admin\Models\Role::class,
]
```

---

## Créer une sauvegarde

### Via la commande CLI

```bash
# Sauvegarde de tous les modèles configurés
./bin/task toth:backup

# Sauvegarde uniquement des tables users et posts
./bin/task toth:backup [users,posts]

# Sauvegarde uniquement depuis la base de données
./bin/task toth:backup --only-db

# Sauvegarde uniquement depuis les fichiers de stockage
./bin/task toth:backup --only-files

# Utilisation des alias
./bin/task backup [users,posts]
./bin/task bkp [users,posts]
```

### Via le service

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;
use App\Models\User;

class UserController extends Controller
{
    public function __construct(
        private readonly ArchiveServiceInterface $archiveService
    ) {}

    public function backupUser(User $user): void
    {
        // ✅ Sauvegarde automatique
        $this->archiveService->createOrUpdateArchive($user);
    }

    public function backupAll(): void
    {
        // ✅ Sauvegarde de tous les modèles configurés
        $this->archiveService->backupFromModels();
    }

    public function backupFromFiles(): void
    {
        // ✅ Recrée les archives depuis les fichiers
        $this->archiveService->backupFromFiles();
    }
}
```

---

## Restaurer des données

### Via la commande CLI

```bash
# Restauration de tous les modèles configurés
./bin/task toth:restore

# Restauration uniquement des tables users et posts
./bin/task toth:restore [users,posts]

# Restauration uniquement depuis la base de données
./bin/task toth:restore --only-db

# Restauration uniquement depuis les fichiers de stockage
./bin/task toth:restore --only-files

# Utilisation des alias
./bin/task restore [users,posts]
./bin/task rst [users,posts]
```

### Via le service

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;
use App\Models\User;

class UserController extends Controller
{
    public function __construct(
        private readonly ArchiveServiceInterface $archiveService
    ) {}

    public function restoreUser(int $userId): void
    {
        // ✅ Création d'une tâche de restauration
        $this->archiveService->restoreFromModels(['users']);
    }

    public function restoreFromFiles(): void
    {
        // ✅ Restauration depuis les fichiers de backup
        $this->archiveService->restoreFromFiles();
    }
}
```

---

## Les observateurs automatiques

Les observateurs sont attachés automatiquement aux modèles configurés via le service provider.

### ArchivableObserver

L'`ArchivableObserver` capture automatiquement les événements des modèles :

| Événement | Action |
|-----------|--------|
| `created` | Crée une archive lors de la création |
| `updated` | Crée une archive lors de la mise à jour |
| `deleted` | Crée une archive lors de la suppression |

```php
<?php

namespace AndyDefer\LaravelToth\Observers;

use AndyDefer\LaravelToth\Services\ArchiveService;
use Illuminate\Database\Eloquent\Model;

final class ArchivableObserver
{
    public function __construct(private readonly ArchiveService $archiveService) {}

    public function created(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }

    public function updated(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }

    public function deleted(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }
}
```

### ArchiveObserver

L'`ArchiveObserver` capture la suppression d'une archive pour créer un fichier de backup avant suppression définitive.

```php
<?php

namespace AndyDefer\LaravelToth\Observers;

use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Services\ArchiveService;

final class ArchiveObserver
{
    public function __construct(private readonly ArchiveService $archiveService) {}

    public function deleted(Archive $archive): void
    {
        $this->archiveService->backup($archive);
    }
}
```

---

## Les tâches asynchrones

Le package utilise Laravel Task pour l'exécution asynchrone des opérations lourdes.

### Tâches disponibles

| Tâche | Rôle |
|-------|------|
| `UpdateOrCreateArchiveTask` | Crée ou met à jour une archive et génère un fichier de backup |
| `BackupArchiveTask` | Crée un fichier de backup physique |
| `RestoreArchiveTask` | Restaure un modèle depuis une archive ou un fichier |
| `UpdateOrCreateFromFileTask` | Recrée une archive depuis un fichier de backup |

### Fonctionnement

Toutes ces tâches sont exécutées de manière asynchrone via Laravel Task, garantissant que votre application reste réactive même lors d'opérations lourdes.

---

## Gestion des fichiers de backup

### Structure des fichiers

```
storage/toth/backups/
    └── {table_name}/
        └── {row_id}.php
```

### Exemple de fichier

```php
<?php
// storage/toth/backups/users/1.php

return [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
    'role' => 'user',
    'created_at' => '2024-01-15 10:30:00',
    'updated_at' => '2024-01-20 14:20:00',
];
```

### Utilisation programmatique

```php
<?php

use AndyDefer\LaravelToth\Helpers\BackupFileHelper;

$helper = app(BackupFileHelper::class);

// ✅ Création d'un fichier de backup
$filePath = $helper->createBackupFile($archive);

// ✅ Lecture d'un fichier de backup
$data = require $filePath;
```

---

## Cas d'usage concrets

### 1. Sauvegarde automatique des utilisateurs

**Problème :** Vous voulez garder un historique de toutes les modifications des utilisateurs.

**Solution :** Ajouter `User::class` dans la configuration `archivables`.

```php
// config/toth.php
'archivables' => [
    App\Models\User::class,
],
```

Chaque création, mise à jour ou suppression d'utilisateur crée automatiquement une archive.

---

### 2. Snapshots avant déploiement

**Problème :** Vous voulez sauvegarder l'état de l'application avant un déploiement.

**Solution :** Créer un snapshot manuel via la commande CLI.

```bash
./bin/task toth:backup
```

En cas de problème, restaurez les données :

```bash
./bin/task toth:restore
```

---

### 3. Récupération après corruption de base de données

**Problème :** La base de données est corrompue, mais les fichiers de backup sont intacts.

**Solution :** Restaurer depuis les fichiers de backup.

```bash
./bin/task toth:backup --only-files
```

Les archives sont recréées depuis les fichiers PHP.

---

### 4. Migration de données avec rollback

**Problème :** Vous effectuez une migration de données, vous voulez pouvoir revenir en arrière.

**Solution :** Créer un snapshot avant la migration.

```php
$archiveService->backupFromModels(['users', 'posts']);

// Exécution de la migration...

// En cas de problème
$archiveService->restoreFromModels(['users', 'posts']);
```

---

### 5. Audit des modifications

**Problème :** Vous voulez savoir quand et comment un enregistrement a été modifié.

**Solution :** Consulter les archives en base de données.

```php
$archives = Archive::where('table_name', 'users')
    ->where('row_id', '1')
    ->orderBy('last_save_at', 'desc')
    ->get();

foreach ($archives as $archive) {
    echo $archive->last_save_at . ': ' . $archive->data['name'] . "\n";
}
```

---

### 6. Envoi de notifications en cas d'échec

**Problème :** Vous voulez être alerté si une sauvegarde échoue.

**Solution :** Utiliser les hooks des tâches.

```php
<?php

namespace App\Tasks;

use AndyDefer\Task\Abstract\AbstractUniqueTask;

class BackupArchiveTask extends AbstractUniqueTask
{
    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        if (!$success) {
            Notification::route('slack', config('services.slack.webhook'))
                ->notify(new BackupFailedNotification($error));
        }
    }
}
```

---

## Intégration avec les cron jobs

### Configuration recommandée

```bash
# Exécution des tâches de sauvegarde toutes les minutes
* * * * * cd /var/www/project && ./bin/task tasks:watch 30 --mute >> /var/log/toth-watch.log 2>&1

# Backup complet toutes les heures
0 * * * * cd /var/www/project && ./bin/task toth:backup >> /var/log/toth-backup.log 2>&1

# Backup depuis les fichiers (pour resynchronisation)
0 2 * * * cd /var/www/project && ./bin/task toth:backup --only-files >> /var/log/toth-backup-files.log 2>&1
```

---

## Référence des commandes

### TothBackupDirective

| Commande | Description |
|----------|-------------|
| `./bin/task toth:backup` | Sauvegarde de tous les modèles configurés |
| `./bin/task toth:backup [users,posts]` | Sauvegarde uniquement les tables spécifiées |
| `./bin/task toth:backup --only-db` | Sauvegarde uniquement depuis la base de données |
| `./bin/task toth:backup --only-files` | Sauvegarde uniquement depuis les fichiers |
| `./bin/task backup` | Alias de la commande |
| `./bin/task bkp` | Alias court |

### TothRestoreDirective

| Commande | Description |
|----------|-------------|
| `./bin/task toth:restore` | Restauration de tous les modèles configurés |
| `./bin/task toth:restore [users,posts]` | Restauration uniquement les tables spécifiées |
| `./bin/task toth:restore --only-db` | Restauration uniquement depuis la base de données |
| `./bin/task toth:restore --only-files` | Restauration uniquement depuis les fichiers |
| `./bin/task restore` | Alias de la commande |
| `./bin/task rst` | Alias court |

### TothDiscoveryDirective ✨ NOUVEAU

| Commande | Description |
|----------|-------------|
| `./bin/task toth:discover` | Découverte depuis `app.Models` (par défaut) |
| `./bin/task toth:discover [sources]` | Découverte depuis les sources spécifiées |
| `./bin/task discover` | Alias de la commande |
| `./bin/task scan` | Alias court |

**Exemples :**

```bash
# Découverte depuis le dossier app/Models
./bin/task toth:discover

# Découverte depuis plusieurs dossiers
./bin/task toth:discover [app.Models, app.Domain]

# Découverte depuis un dossier de test
./bin/task toth:discover [%%tests.Fixtures.Models]

# Utilisation des alias
./bin/task discover [app.Models]
./bin/task scan [app.Models]
```

---

## Bonnes pratiques

### ✅ Configurer les modèles à archiver

```php
// config/toth.php
'archivables' => [
    App\Models\User::class,
    App\Models\Post::class,
],
```

### ✅ Utiliser les tâches asynchrones

Toutes les opérations lourdes sont asynchrones. Ne bloquez pas votre application.

### ✅ Utiliser la priorité des sources

Lors de la restauration, le système donne la priorité à la source la plus récente (base de données ou fichier).

### ✅ Surveiller les fichiers de backup

```bash
# Vérifier la taille des fichiers
du -sh storage/toth/backups/

# Nettoyer les vieux fichiers (à implémenter)
find storage/toth/backups/ -type f -mtime +30 -delete
```

### ✅ Utiliser la découverte automatique

```bash
# Découvrir automatiquement tous les modèles
./bin/task toth:discover
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
