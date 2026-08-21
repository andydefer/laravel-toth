# UpdateOrCreateFromFileTask - Référence Technique

## Description

Tâche asynchrone qui lit un fichier de backup depuis le stockage et recrée l'archive correspondante en base de données. Utilisée pour restaurer des archives à partir de fichiers lorsqu'elles ont été perdues en base.

## Hiérarchie

```
AbstractUniqueTask
    └── UpdateOrCreateFromFileTask
```

**Interfaces implémentées :** `TaskInterface`

## Rôle principal

Reconstruire une archive en base de données à partir d'un fichier de backup physique. La tâche lit le fichier PHP, extrait les données, résout le `model_class` à partir de la configuration, et crée ou met à jour l'archive correspondante.

## Installation

La tâche est automatiquement enregistrée via le `LaravelTothServiceProvider` et dispatchée par le `ArchiveService`.

```php
$payload = StrictDataObject::from([
    'table_name' => 'users',
    'row_id' => '1',
]);
$taskService->register(
    new UniqueTaskFqcnVO(UpdateOrCreateFromFileTask::class),
    $payload,
    $config
);
```

## API / Méthodes publiques

### `process(): void`

Point d'entrée de la tâche. Orchestre la lecture du fichier, la résolution du modèle, et la création de l'archive.

```php
protected function process(): void
```

**Retourne :** `void`

**Exceptions :**
- `RuntimeException` - Si le fichier n'existe pas
- `RuntimeException` - Si le fichier est vide
- `RuntimeException` - Si le model_class n'est pas trouvé

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches
// Aucun appel manuel n'est nécessaire
```

## Cas d'utilisation

### Cas 1 : Restauration d'une archive après suppression en base

**Problème :** Une archive a été supprimée en base, mais le fichier de backup existe.

**Solution :** La tâche recrée l'archive depuis le fichier.

```php
// L'archive en DB a été supprimée
// Le fichier storage/toth/backups/users/1.php existe
// La tâche recrée l'archive avec les données du fichier
```

---

### Cas 2 : Synchronisation des archives depuis les fichiers

**Problème :** Les archives en base sont obsolètes, les fichiers sont plus récents.

**Solution :** Utiliser `backupFromFiles()` qui dispatch cette tâche pour chaque fichier.

```bash
./bin/task toth:backup --only-files
# Toutes les archives sont recréées depuis les fichiers
```

---

### Cas 3 : Récupération après une corruption de base de données

**Problème :** La base de données a été corrompue, mais les fichiers de backup sont intacts.

**Solution :** Restaurer toutes les archives depuis les fichiers.

```php
$archiveService->backupFromFiles();
// Chaque fichier de backup est traité par UpdateOrCreateFromFileTask
// Toutes les archives sont recréées en base
```

## Flux d'exécution

```
process()
    ↓
Récupération de tableName et rowId depuis le payload
    ↓
buildFilePath()
    └── backupPath/tableName/rowId.php
    ↓
ensureFileExists()
    ├── Si le fichier existe → continuer
    └── Si non → RuntimeException
    ↓
loadBackupData()
    ├── require du fichier
    ├── Vérification que les données ne sont pas vides
    └── Si vides → RuntimeException
    ↓
resolveModelClass()
    ├── Parcours des modèles configurés
    ├── Comparaison avec le tableName
    └── Retourne le FQCN ou null
    ↓
ensureModelClassFound()
    ├── Si trouvé → continuer
    └── Si non → RuntimeException
    ↓
updateOrCreate()
    ├── Crée ou met à jour l'archive
    ├── table_name = tableName
    ├── row_id = rowId
    ├── model_class = modelClass
    ├── data = StrictAssociative::from($data)
    └── last_save_at = now()
    ↓
Log du succès
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fichier non trouvé | `RuntimeException` | `Backup file not found: {path}` |
| Fichier vide | `RuntimeException` | `Backup file is empty: {path}` |
| Model_class non trouvé | `RuntimeException` | `Model class not found for table: {table}` |

## Intégration

### Avec TothConfig

```php
$backupPath = $config->getBackupFolderPath();
$archivables = $config->getArchivables();
```

### Avec ArchiveRepository

```php
$archiveRepository->updateOrCreate(
    [
        'table_name' => $tableName,
        'row_id' => $rowId,
        'model_class' => $modelClass,
    ],
    [
        'data' => StrictAssociative::from($data),
        'last_save_at' => now()->toIso8601String(),
    ]
);
```

### Avec BackupFileHelper

```php
// Cette tâche est complémentaire à BackupFileHelper
// Elle lit les fichiers que BackupFileHelper crée
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `File::exists()` | O(1) | Vérification disque |
| `require $filePath` | O(n) | n = taille des données |
| `resolveModelClass()` | O(m) | m = nombre de modèles configurés |
| `updateOrCreate()` | O(log n) | Recherche avec index |

**Optimisations :**
- Tâche asynchrone (ne bloque pas le processus principal)
- Les fichiers sont lus une seule fois
- Résolution du model_class mise en cache (via config)

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
use AndyDefer\LaravelToth\Tasks\UpdateOrCreateFromFileTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Container\Container;

$app = Container::getInstance();
$taskService = $app->make(UniqueTaskServiceInterface::class);

// 1. Créer le payload
$payload = StrictDataObject::from([
    'table_name' => 'users',
    'row_id' => '1',
]);

// 2. Créer la configuration de la tâche
$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => now()->addSeconds(5)->toIso8601String(),
    'max_attempts' => 3,
    'grace_period' => 60,
    'description' => 'Update or create archive from file task',
]);

// 3. Enregistrer la tâche
$taskService->register(
    new UniqueTaskFqcnVO(UpdateOrCreateFromFileTask::class),
    $payload,
    $config
);

// 4. La tâche sera exécutée automatiquement
// Le fichier storage/toth/backups/users/1.php est lu
// L'archive est recréée en base
```

## Voir aussi

- `ArchiveService` - Service qui dispatch cette tâche
- `BackupFileHelper` - Helper qui crée les fichiers de backup
- `BackupArchiveTask` - Tâche de création de fichiers de backup
- `RestoreArchiveTask` - Tâche de restauration de modèles