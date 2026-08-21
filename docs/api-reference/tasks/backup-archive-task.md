# BackupArchiveTask - Référence Technique

## Description

Tâche asynchrone qui crée un fichier de backup physique pour une archive donnée. Génère un fichier PHP contenant les données archivées, stocké dans le répertoire de backup configuré.

## Hiérarchie

```
AbstractUniqueTask
    └── BackupArchiveTask
```

**Interfaces implémentées :** `TaskInterface`

## Rôle principal

Convertir les données d'une archive en un fichier PHP persistant. Cette tâche est déclenchée automatiquement lors de la création ou mise à jour d'une archive, ou manuellement via le service `ArchiveService`.

## Installation

La tâche est automatiquement enregistrée via le `LaravelTothServiceProvider` et dispatchée par le `ArchiveService`.

```php
$payload = StrictDataObject::from(['archive_id' => $archive->id]);
$taskService->register(
    new UniqueTaskFqcnVO(BackupArchiveTask::class),
    $payload,
    $config
);
```

## API / Méthodes publiques

### `process(): void`

Point d'entrée de la tâche. Récupère l'archive, vérifie son existence, et crée le fichier de backup.

```php
protected function process(): void
```

**Retourne :** `void`

**Exceptions :** `RuntimeException` - Si l'archive n'est pas trouvée

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches
// Aucun appel manuel n'est nécessaire
```

## Cas d'utilisation

### Cas 1 : Création automatique lors de la mise à jour d'une archive

**Problème :** Une archive est mise à jour, le fichier de backup doit être régénéré.

**Solution :** `UpdateOrCreateArchiveTask` appelle `ArchiveService::backup()` qui dispatch cette tâche.

```php
// Dans UpdateOrCreateArchiveTask
$archive = $archiveRepository->updateOrCreate(...);
$backupHelper = $this->context->getLaravelApp()->make(BackupFileHelper::class);
$backupHelper->createBackupFile($archive);
```

---

### Cas 2 : Backup manuel d'une archive existante

**Problème :** L'utilisateur souhaite forcer la création d'un fichier de backup pour une archive.

**Solution :** Appeler `ArchiveService::backup()` sur l'archive concernée.

```php
$archive = Archive::find(1);
$archiveService->backup($archive);
// Dispatch BackupArchiveTask
```

---

### Cas 3 : Backup avant suppression d'une archive

**Problème :** Une archive est supprimée, un backup doit être conservé.

**Solution :** L'`ArchiveObserver` appelle `ArchiveService::backup()` lors de la suppression.

```php
// Dans ArchiveObserver
public function deleted(Archive $archive): void
{
    $this->archiveService->backup($archive);
}
```

## Flux d'exécution

```
process()
    ↓
Récupération de l'archiveId depuis le payload
    ↓
Récupération de l'ArchiveRepository via le contexte
    ↓
Récupération de l'Archive
    ↓
Vérification de l'existence
    ├── Si trouvée → continuer
    └── Si non trouvée → Exception RuntimeException
    ↓
Récupération du BackupFileHelper via le contexte
    ↓
Appel de createBackupFile()
    ├── Construction du chemin (backupPath/tableName/rowId.php)
    ├── Création du dossier si nécessaire
    └── Génération du contenu PHP
    ↓
Log du succès
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Archive non trouvée | `RuntimeException` | `Archive not found: ID {id}` |

## Intégration

### Avec BackupFileHelper

```php
$backupHelper = $this->context->getLaravelApp()->make(BackupFileHelper::class);
$filePath = $backupHelper->createBackupFile($archive);
```

### Avec ArchiveRepository

```php
$archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);
$archive = $archiveRepository->find($archiveId);
```

### Avec UniqueTaskContext

```php
$payload = $this->context->getPayload();
$archiveId = $payload->archive_id;
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `ArchiveRepository::find()` | O(1) | Recherche par ID |
| `BackupFileHelper::createBackupFile()` | O(n) | n = taille des données |
| `File::put()` | O(n) | Écriture disque |

**Optimisations :**
- Tâche asynchrone (ne bloque pas le processus principal)
- Utilisation de `BackupFileHelper` pour centraliser la logique
- Création du dossier en une seule opération

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Services\ArchiveService;
use AndyDefer\LaravelToth\Tasks\BackupArchiveTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Container\Container;

$app = Container::getInstance();

// 1. Créer une archive
$archive = Archive::create([
    'table_name' => 'users',
    'row_id' => 1,
    'model_class' => User::class,
    'data' => ['id' => 1, 'name' => 'John Doe'],
    'last_save_at' => now(),
]);

// 2. Créer le payload
$payload = StrictDataObject::from([
    'archive_id' => $archive->id,
]);

// 3. Créer la configuration de la tâche
$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => now()->addSeconds(5)->toIso8601String(),
    'max_attempts' => 3,
    'grace_period' => 60,
    'description' => 'Backup archive task',
]);

// 4. Enregistrer la tâche
$taskService = $app->make(UniqueTaskServiceInterface::class);
$taskService->register(
    new UniqueTaskFqcnVO(BackupArchiveTask::class),
    $payload,
    $config
);

// 5. La tâche sera exécutée automatiquement
// Le fichier sera créé à : storage/toth/backups/users/1.php
```

## Voir aussi

- `BackupFileHelper` - Helper de création de fichiers de backup
- `ArchiveService` - Service qui dispatch cette tâche
- `ArchiveObserver` - Observateur qui déclenche le backup sur suppression
- `UpdateOrCreateArchiveTask` - Tâche qui crée les archives
