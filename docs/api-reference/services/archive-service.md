# ArchiveService - Référence Technique

## Description

Service central qui orchestre l'ensemble des opérations d'archivage, de sauvegarde et de restauration. Gère le cycle de vie complet des archives, de la création à la restauration, en passant par la génération de fichiers de backup.

## Hiérarchie

```
ArchiveServiceInterface
    └── ArchiveService
```

**Interfaces implémentées :** `ArchiveServiceInterface`

## Rôle principal

Coordonner toutes les opérations liées aux archives. Le service agit comme un orchestrateur qui :
- Crée et met à jour les archives
- Génère des fichiers de backup
- Restaure les données depuis les archives ou les fichiers
- Annule les tâches en double pour éviter les exécutions redondantes
- Enregistre les observateurs sur les modèles configurables

## Installation

Le service est automatiquement enregistré dans le conteneur Laravel via le `LaravelTothServiceProvider`.

```php
$archiveService = app(ArchiveServiceInterface::class);
```

## API / Méthodes publiques

### `createOrUpdateArchive(Model $model): ?Archive`

Crée ou met à jour une archive pour un modèle donné.

```php
public function createOrUpdateArchive(Model $model): ?Archive
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model` | Le modèle Eloquent à archiver |

**Retourne :** `?Archive` - L'archive créée ou mise à jour, ou `null` si non trouvée

**Exceptions :** Aucune (les erreurs sont propagées via les tâches)

**Exemple :**
```php
$user = User::find(1);
$archive = $archiveService->createOrUpdateArchive($user);
```

---

### `backup(Archive $archive): void`

Crée un fichier de backup pour une archive donnée.

```php
public function backup(Archive $archive): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$archive` | `Archive` | L'archive à sauvegarder |

**Retourne :** `void`

**Exceptions :** Aucune (la tâche est dispatchée de manière asynchrone)

**Exemple :**
```php
$archive = Archive::find(1);
$archiveService->backup($archive);
```

---

### `backupFromModels(array $tables = []): void`

Crée des archives pour tous les modèles configurables.

```php
public function backupFromModels(array $tables = []): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tables` | `array<string>` | Liste optionnelle des tables à filtrer |

**Retourne :** `void`

**Exceptions :** Aucune (les opérations sont dispatchées de manière asynchrone)

**Exemple :**
```php
// Backup de tous les modèles configurés
$archiveService->backupFromModels();

// Backup uniquement des tables users et posts
$archiveService->backupFromModels(['users', 'posts']);
```

---

### `backupFromFiles(array $tables = []): void`

Crée des archives à partir des fichiers de backup existants.

```php
public function backupFromFiles(array $tables = []): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tables` | `array<string>` | Liste optionnelle des tables à filtrer |

**Retourne :** `void`

**Exceptions :** Aucune (les opérations sont dispatchées de manière asynchrone)

**Exemple :**
```php
// Restaure toutes les archives depuis les fichiers
$archiveService->backupFromFiles();

// Restaure uniquement les tables users et posts
$archiveService->backupFromFiles(['users', 'posts']);
```

---

### `restoreFromModels(array $tables = []): void`

Restaure les modèles depuis les archives en base de données.

```php
public function restoreFromModels(array $tables = []): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tables` | `array<string>` | Liste optionnelle des tables à filtrer |

**Retourne :** `void`

**Exceptions :** Aucune (les opérations sont dispatchées de manière asynchrone)

**Exemple :**
```php
// Restaure tous les modèles depuis les archives DB
$archiveService->restoreFromModels();

// Restaure uniquement les tables users et posts
$archiveService->restoreFromModels(['users', 'posts']);
```

---

### `restoreFromFiles(array $tables = []): void`

Restaure les modèles depuis les fichiers de backup.

```php
public function restoreFromFiles(array $tables = []): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tables` | `array<string>` | Liste optionnelle des tables à filtrer |

**Retourne :** `void`

**Exceptions :** Aucune (les opérations sont dispatchées de manière asynchrone)

**Exemple :**
```php
// Restaure tous les modèles depuis les fichiers
$archiveService->restoreFromFiles();

// Restaure uniquement les tables users et posts
$archiveService->restoreFromFiles(['users', 'posts']);
```

---

### `registerObservers(): void`

Enregistre les observateurs sur tous les modèles configurables.

```php
public function registerObservers(): void
```

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
// Appelé automatiquement dans le service provider
$archiveService->registerObservers();
```

## Cas d'utilisation

### Cas 1 : Archivage automatique d'un modèle

**Problème :** Un utilisateur est créé, il faut automatiquement archiver ses données.

**Solution :** L'`ArchivableObserver` est attaché au modèle et appelle `createOrUpdateArchive()`.

```php
// Dans le modèle User
class User extends Model
{
    // L'observateur est attaché automatiquement via le service provider
}

// Lors de la création
$user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
// Une archive est automatiquement créée en arrière-plan
```

---

### Cas 2 : Sauvegarde complète de toutes les données

**Problème :** L'administrateur souhaite sauvegarder toutes les données de l'application.

**Solution :** Exécuter la commande `toth:backup` qui appelle `backupFromModels()`.

```php
// Via CLI
./bin/task toth:backup

// Ou programmatiquement
$archiveService->backupFromModels();
```

---

### Cas 3 : Restauration depuis un fichier de backup

**Problème :** Une archive a été supprimée accidentellement, mais le fichier de backup existe.

**Solution :** Utiliser `backupFromFiles()` pour recréer l'archive depuis le fichier.

```php
$archiveService->backupFromFiles(['users']);
// L'archive est recréée depuis le fichier storage/toth/backups/users/1.php
```

---

### Cas 4 : Annulation des tâches en double

**Problème :** Un modèle est mis à jour plusieurs fois en quelques secondes.

**Solution :** `cancelPendingArchiveTask()` annule la tâche précédente avant d'en créer une nouvelle.

```php
// Première mise à jour
$user->update(['name' => 'John']);
$archiveService->createOrUpdateArchive($user);
// Tâche 1 créée

// Deuxième mise à jour immédiate
$user->update(['name' => 'Johnny']);
$archiveService->createOrUpdateArchive($user);
// Tâche 1 annulée, Tâche 2 créée
// Une seule tâche s'exécute finalement
```

## Flux d'exécution

```
createOrUpdateArchive()
    ↓
cancelPendingArchiveTask()
    ├── Recherche une tâche en attente pour le même modèle
    └── Si trouvée → annulation
    ↓
dispatchArchiveCreationTask()
    ├── Création du payload (model_class, model_id)
    └── Enregistrement de la tâche avec configuration
    ↓
Recherche de l'archive en base
    ↓
Retourne l'archive trouvée
```

## Gestion des erreurs

| Situation | Gestion | Détail |
|-----------|---------|--------|
| Tâche en attente | Annulation | `cancelPendingArchiveTask()` annule la tâche précédente |
| Modèle non trouvé | Exception | `RuntimeException` dans la tâche `UpdateOrCreateArchiveTask` |
| Fichier de backup introuvable | Exception | `RuntimeException` dans `UpdateOrCreateFromFileTask` |
| Modèle déjà existant | Exception | `RuntimeException` dans `RestoreArchiveTask` |

## Intégration

### Avec TothConfig

```php
$archivableModels = $this->config->getArchivables();
$delay = $this->config->getTaskDelaySeconds();
```

### Avec UniqueTaskService

```php
$this->taskService->register(
    new UniqueTaskFqcnVO(UpdateOrCreateArchiveTask::class),
    $payload,
    $this->createTaskConfig('Description')
);
```

### Avec ArchiveRepository

```php
$this->archiveRepository->findBy(
    FindByRecord::from(['filters' => $filters])
)->first();
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `createOrUpdateArchive()` | O(1) | Opération légère, délègue aux tâches |
| `backupFromModels()` | O(n×m) | n = modèles configurés, m = enregistrements |
| `restoreFromFiles()` | O(n×f) | n = dossiers, f = fichiers par dossier |

**Optimisations :**
- Toutes les opérations lourdes sont asynchrones (via `laravel-task`)
- Annulation des tâches en double pour éviter le surcoût
- Utilisation de `chunk()` pour les gros volumes (via Eloquent)

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

use AndyDefer\LaravelToth\Services\ArchiveService;
use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;
use App\Models\User;
use Illuminate\Container\Container;

$app = Container::getInstance();
$archiveService = $app->make(ArchiveServiceInterface::class);

// 1. Archiver un utilisateur
$user = User::find(1);
$archive = $archiveService->createOrUpdateArchive($user);

// 2. Sauvegarder l'archive en fichier
$archiveService->backup($archive);

// 3. Backup de tous les modèles configurables
$archiveService->backupFromModels();

// 4. Restaurer tous les modèles depuis les archives
$archiveService->restoreFromModels();

// 5. Restaurer depuis les fichiers (si les archives DB sont perdues)
$archiveService->restoreFromFiles();
```

## Voir aussi

- `TothConfigInterface` - Interface de configuration
- `ArchiveRepository` - Repository des archives
- `UpdateOrCreateArchiveTask` - Tâche de création d'archive
- `BackupArchiveTask` - Tâche de backup
- `RestoreArchiveTask` - Tâche de restauration
---