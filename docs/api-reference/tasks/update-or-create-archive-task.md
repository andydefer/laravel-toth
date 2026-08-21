# UpdateOrCreateArchiveTask - Référence Technique

## Description

Tâche asynchrone qui capture l'état actuel d'un modèle en créant ou mettant à jour une archive en base de données, puis génère un fichier de backup physique. Cette tâche est le cœur du système d'archivage.

## Hiérarchie

```
AbstractUniqueTask
    └── UpdateOrCreateArchiveTask
```

**Interfaces implémentées :** `TaskInterface`

## Rôle principal

Persister l'état d'un modèle dans deux formats : une archive en base de données (pour une récupération rapide) et un fichier PHP (pour une sauvegarde durable). La tâche utilise `updateOrCreate` pour garantir qu'il n'y a qu'une seule archive par modèle.

## Installation

La tâche est automatiquement enregistrée via le `LaravelTothServiceProvider` et dispatchée par le `ArchiveService`.

```php
$payload = StrictDataObject::from([
    'model_class' => User::class,
    'model_id' => 1,
]);
$taskService->register(
    new UniqueTaskFqcnVO(UpdateOrCreateArchiveTask::class),
    $payload,
    $config
);
```

## API / Méthodes publiques

### `process(): void`

Point d'entrée de la tâche. Récupère le modèle, crée/met à jour l'archive, et génère le fichier de backup.

```php
protected function process(): void
```

**Retourne :** `void`

**Exceptions :**
- `RuntimeException` - Si le modèle n'est pas trouvé

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches
// Aucun appel manuel n'est nécessaire
```

## Cas d'utilisation

### Cas 1 : Archivage d'un nouveau modèle

**Problème :** Un nouvel utilisateur est créé, ses données doivent être archivées.

**Solution :** L'`ArchivableObserver` déclenche `createOrUpdateArchive()` qui dispatch cette tâche.

```php
// Création d'un utilisateur
$user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
// Une tâche UpdateOrCreateArchiveTask est dispatchée
// L'archive est créée avec les données de l'utilisateur
```

---

### Cas 2 : Mise à jour d'un modèle existant

**Problème :** Un utilisateur est modifié, l'archive doit être mise à jour.

**Solution :** La tâche utilise `updateOrCreate` pour mettre à jour l'archive existante.

```php
// Mise à jour de l'utilisateur
$user->update(['name' => 'Jane Doe']);
// Une tâche UpdateOrCreateArchiveTask est dispatchée
// L'archive existante est mise à jour avec les nouvelles données
```

---

### Cas 3 : Suppression d'un modèle

**Problème :** Un utilisateur est supprimé, ses données doivent être archivées avant suppression.

**Solution :** L'`ArchivableObserver` déclenche l'archivage sur l'événement `deleted`.

```php
// Suppression de l'utilisateur
$user->delete();
// Une tâche UpdateOrCreateArchiveTask est dispatchée
// L'archive capture les données avant suppression
```

## Flux d'exécution

```
process()
    ↓
Récupération de modelClass et modelId depuis le payload
    ↓
Recherche du modèle en base
    ├── Si trouvé → continuer
    └── Si non trouvé → RuntimeException
    ↓
Récupération de l'ArchiveRepository via le contexte
    ↓
Récupération du BackupFileHelper via le contexte
    ↓
updateOrCreate()
    ├── Recherche d'une archive existante (table_name, row_id, model_class)
    ├── Si trouvée → mise à jour
    └── Si non trouvée → création
    ↓
createBackupFile()
    ├── Construction du chemin
    ├── Création du dossier
    └── Génération du contenu PHP
    ↓
Log du succès
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle non trouvé | `RuntimeException` | `Model not found: {class} with ID {id}` |

## Intégration

### Avec ArchiveRepository

```php
$archive = $archiveRepository->updateOrCreate(
    [
        'table_name' => $model->getTable(),
        'row_id' => $model->getKey(),
        'model_class' => get_class($model),
    ],
    [
        'data' => $model->toArray(),
        'last_save_at' => now()->toIso8601String(),
    ]
);
```

### Avec BackupFileHelper

```php
$backupHelper = $this->context->getLaravelApp()->make(BackupFileHelper::class);
$backupHelper->createBackupFile($archive);
```

### Avec UniqueTaskContext

```php
$payload = $this->context->getPayload();
$modelClass = $payload->model_class;
$modelId = $payload->model_id;
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `Model::find()` | O(1) | Recherche par ID |
| `updateOrCreate()` | O(log n) | Recherche avec index |
| `BackupFileHelper::createBackupFile()` | O(n) | n = taille des données |

**Optimisations :**
- Tâche asynchrone (ne bloque pas le processus principal)
- `updateOrCreate` évite les doublons
- Le fichier de backup est écrasé à chaque mise à jour (pas d'historique)

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
use AndyDefer\LaravelToth\Tasks\UpdateOrCreateArchiveTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use App\Models\User;
use Illuminate\Container\Container;

$app = Container::getInstance();
$taskService = $app->make(UniqueTaskServiceInterface::class);

// 1. Créer un utilisateur
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// 2. Créer le payload
$payload = StrictDataObject::from([
    'model_class' => User::class,
    'model_id' => $user->id,
]);

// 3. Créer la configuration de la tâche
$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => now()->addSeconds(5)->toIso8601String(),
    'max_attempts' => 3,
    'grace_period' => 60,
    'description' => 'Update or create archive task',
]);

// 4. Enregistrer la tâche
$taskService->register(
    new UniqueTaskFqcnVO(UpdateOrCreateArchiveTask::class),
    $payload,
    $config
);

// 5. La tâche sera exécutée automatiquement
// L'archive sera créée en base et le fichier de backup généré
// storage/toth/backups/users/1.php
```

## Voir aussi

- `ArchiveService` - Service qui dispatch cette tâche
- `BackupFileHelper` - Helper de création de fichiers de backup
- `ArchivableObserver` - Observateur qui déclenche l'archivage
- `BackupArchiveTask` - Tâche de backup
