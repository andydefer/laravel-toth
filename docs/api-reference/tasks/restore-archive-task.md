# RestoreArchiveTask - Référence Technique

## Description

Tâche asynchrone qui restaure un modèle à partir d'une archive ou d'un fichier de backup. Récupère les données les plus récentes (base de données ou fichier) et recrée le modèle avec son identifiant original. Les contraintes de clés étrangères sont temporairement désactivées pendant la restauration.

## Hiérarchie

```
AbstractUniqueTask
    └── RestoreArchiveTask
```

**Interfaces implémentées :** `TaskInterface`

## Rôle principal

Restaurer un modèle à son état antérieur en utilisant les données archivées les plus récentes disponibles. La tâche compare les timestamps des données en base de données et des fichiers de backup pour déterminer la source la plus récente.

## Installation

La tâche est automatiquement enregistrée via le `LaravelTothServiceProvider` et dispatchée par le `ArchiveService`.

```php
$payload = StrictDataObject::from([
    'table_name' => 'users',
    'row_id' => '1',
]);
$taskService->register(
    new UniqueTaskFqcnVO(RestoreArchiveTask::class),
    $payload,
    $config
);
```

## API / Méthodes publiques

### `process(): void`

Point d'entrée de la tâche. Orchestre le processus complet de restauration.

```php
protected function process(): void
```

**Retourne :** `void`

**Exceptions :**
- `RuntimeException` - Si aucune donnée n'est trouvée
- `RuntimeException` - Si le model_class n'est pas résolu
- `RuntimeException` - Si le modèle existe déjà en base

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches
// Aucun appel manuel n'est nécessaire
```

## Cas d'utilisation

### Cas 1 : Restauration depuis une archive en base de données

**Problème :** Un utilisateur a été supprimé accidentellement, l'archive existe en base.

**Solution :** La tâche restaure l'utilisateur depuis l'archive la plus récente.

```php
// L'archive existe en DB avec les données de l'utilisateur
// La tâche restaure le modèle avec son ID original
$archive->data = ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'];
// Résultat : User::find(1) retourne l'utilisateur restauré
```

---

### Cas 2 : Restauration depuis un fichier de backup (DB perdue)

**Problème :** L'archive en DB a été supprimée, mais le fichier de backup existe.

**Solution :** La tâche utilise le fichier de backup comme source.

```php
// L'archive en DB n'existe pas
// Le fichier storage/toth/backups/users/1.php existe
// La tâche lit le fichier et restaure l'utilisateur
```

---

### Cas 3 : Priorité à la source la plus récente

**Problème :** L'archive en DB et le fichier de backup existent avec des données différentes.

**Solution :** La tâche compare les timestamps et utilise la source la plus récente.

```php
// DB : last_save_at = 2024-01-15 10:00:00
// File : lastModified = 2024-01-15 10:30:00
// Résultat : Le fichier est utilisé (plus récent)
```

## Flux d'exécution

```
process()
    ↓
Récupération de tableName et rowId depuis le payload
    ↓
findLatestArchive()
    ├── Recherche l'archive la plus récente en DB
    └── Tri par last_save_at:desc
    ↓
getBackupData()
    └── Lecture du fichier storage/toth/backups/tableName/rowId.php
    ↓
getBackupTimestamp()
    └── File::lastModified() du fichier
    ↓
determineRestorationSource()
    ├── Si DB et File existent → comparer les timestamps
    ├── Si DB existe seule → utiliser DB
    ├── Si File existe seul → utiliser File
    └── Si aucun → RuntimeException
    ↓
resolveModelClass()
    ├── Si archive existe → utiliser archive->model_class
    └── Sinon → chercher dans la configuration
    ↓
ensureModelDoesNotExist()
    ├── Vérifier si le modèle existe déjà
    └── Si oui → RuntimeException
    ↓
restoreModel()
    ├── disableForeignKeyChecks()
    ├── Créer le modèle avec l'ID original
    ├── fill() avec les données (sans l'ID)
    ├── setAttribute('id', rowId)
    ├── save()
    └── enableForeignKeyChecks()
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune donnée trouvée | `RuntimeException` | `No data found to restore for {table}:{id}` |
| Model_class non résolu | `RuntimeException` | `No model_class found for {table}:{id}` |
| Modèle existe déjà | `RuntimeException` | `Cannot restore: Model {class} with ID {id} already exists in database` |

## Intégration

### Avec ArchiveRepository

```php
$archive = $this->findLatestArchive($archiveRepository, $tableName, $rowId);
```

### Avec TothConfig

```php
$modelClass = $this->resolveModelClass($archive, $tableName, $config);
```

### Avec BackupFileHelper

```php
$backupData = $this->getBackupData($tableName, $rowId, $config);
```

### Avec les contraintes FK

```php
// Désactivation pour éviter les violations d'intégrité
$this->disableForeignKeyChecks();
// ... restauration ...
$this->enableForeignKeyChecks();
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `findLatestArchive()` | O(log n) | Recherche avec index sur table_name, row_id |
| `getBackupData()` | O(1) | Lecture fichier unique |
| `determineRestorationSource()` | O(1) | Comparaison simple |
| `restoreModel()` | O(1) | Insertion en base |

**Optimisations :**
- Tâche asynchrone (ne bloque pas le processus principal)
- Index sur `(table_name, row_id, last_save_at)` pour accélérer la recherche
- Désactivation des FK uniquement pendant la restauration

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
use AndyDefer\LaravelToth\Tasks\RestoreArchiveTask;
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
    'description' => 'Restore archive task',
]);

// 3. Enregistrer la tâche
$taskService->register(
    new UniqueTaskFqcnVO(RestoreArchiveTask::class),
    $payload,
    $config
);

// 4. La tâche sera exécutée automatiquement
// L'utilisateur avec ID 1 sera restauré depuis l'archive la plus récente
```

## Voir aussi

- `ArchiveService` - Service qui dispatch cette tâche
- `RestoreArchiveTask` - Tâche de restauration
- `UpdateOrCreateArchiveTask` - Tâche de création d'archive
- `BackupFileHelper` - Helper de gestion des fichiers de backup
