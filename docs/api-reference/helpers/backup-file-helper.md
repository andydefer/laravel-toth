# BackupFileHelper - Référence Technique

## Description

Helper dédié à la création de fichiers de backup à partir des données d'une archive. Génère des fichiers PHP qui retournent un tableau de données lorsqu'ils sont inclus.

## Hiérarchie

```
BackupFileHelper
```

**Interfaces implémentées :** Aucune (classe utilitaire autonome)

## Rôle principal

Centraliser la logique de création des fichiers de backup. Garantit que les fichiers sont stockés dans une structure organisée (`{backupPath}/{tableName}/{rowId}.php`) et que leur contenu est formaté correctement pour une restauration ultérieure.

## Installation

Le helper est automatiquement injecté par le conteneur Laravel via le service provider.

```php
$helper = $app->make(BackupFileHelper::class);
```

## API / Méthodes publiques

### `createBackupFile(Archive $archive): string`

Crée un fichier PHP de backup pour une archive donnée.

```php
public function createBackupFile(Archive $archive): string
{
    $filePath = $this->buildFilePath($archive);
    $this->ensureDirectoryExists($filePath);
    File::put($filePath, $this->generateFileContent($archive));
    return $filePath;
}
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$archive` | `Archive` | L'archive à sauvegarder |

**Retourne :** `string` - Le chemin absolu du fichier de backup créé

**Exceptions :** Aucune (les exceptions sont propagées depuis `File::makeDirectory` et `File::put`)

**Exemple :**
```php
$archive = Archive::find(1);
$helper = app(BackupFileHelper::class);
$filePath = $helper->createBackupFile($archive);
// Résultat : /storage/toth/backups/users/1.php
```

## Cas d'utilisation

### Cas 1 : Création d'un backup lors de la création d'une archive

**Problème :** Lorsqu'une nouvelle archive est créée, un fichier de backup doit être généré.

**Solution :** Appeler `createBackupFile()` après la création de l'archive.

```php
$archive = $archiveRepository->create(ArchiveRecord::from([
    'table_name' => 'users',
    'row_id' => 1,
    'model_class' => User::class,
    'data' => $user->toArray(),
    'last_save_at' => now()->toIso8601String(),
]));

$backupHelper = app(BackupFileHelper::class);
$backupHelper->createBackupFile($archive);
```

---

### Cas 2 : Mise à jour d'une archive avec mise à jour du backup

**Problème :** Lorsqu'une archive est mise à jour, le fichier de backup doit être régénéré.

**Solution :** Appeler `createBackupFile()` après la mise à jour de l'archive (le fichier est écrasé).

```php
$archive->update([
    'data' => $updatedData,
    'last_save_at' => now()->toIso8601String(),
]);

$backupHelper = app(BackupFileHelper::class);
$backupHelper->createBackupFile($archive);
// Le fichier est écrasé avec les nouvelles données
```

## Structure des fichiers

### Organisation des dossiers

```
{backupFolderPath}/
    └── {tableName}/
        └── {rowId}.php
```

**Exemple :**
```
storage/toth/backups/
    └── users/
        └── 1.php
```

### Contenu d'un fichier de backup

```php
<?php

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

## Flux d'exécution

```
createBackupFile(Archive $archive)
    ↓
buildFilePath()
    ├── backupPath → config
    ├── table_name → archive
    └── row_id → archive
    ↓
ensureDirectoryExists()
    ├── Si dossier existe → continuer
    └── Sinon → créer avec mkdir(0755, true)
    ↓
generateFileContent()
    ├── Extraire data de l'archive
    ├── var_export(data, true)
    └── Encapsuler dans <?php return ...;
    ↓
File::put(filePath, content)
    ↓
Retourner filePath
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Échec de création du dossier | `IOException` | Propagé par `File::makeDirectory` |
| Échec d'écriture du fichier | `IOException` | Propagé par `File::put` |
| Données d'archive vides | Aucune | Le fichier contiendra `[]` |

## Intégration

### Avec les Tasks

Le helper est utilisé par `UpdateOrCreateArchiveTask` et `BackupArchiveTask` pour générer les fichiers de backup.

```php
// Dans UpdateOrCreateArchiveTask
$archive = $archiveRepository->updateOrCreate(...);
$backupHelper = $this->context->getLaravelApp()->make(BackupFileHelper::class);
$backupHelper->createBackupFile($archive);
```

### Avec BackupFileHelper dans le Service Provider

```php
$this->app->singleton(BackupFileHelper::class, function ($app) {
    return new BackupFileHelper($app->make(TothConfigInterface::class));
});
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `buildFilePath()` | O(1) | Négligeable |
| `ensureDirectoryExists()` | O(1) | Une vérification par fichier |
| `generateFileContent()` | O(n) | Dépend de la taille des données |
| `File::put()` | O(n) | Écriture disque |

**Optimisations :**
- Le dossier est créé une seule fois à la première écriture
- Les données sont exportées avec `var_export` (optimisé par PHP)
- Pas de cache nécessaire (opération directe)

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

use AndyDefer\LaravelToth\Helpers\BackupFileHelper;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use Illuminate\Container\Container;

$app = Container::getInstance();
$helper = $app->make(BackupFileHelper::class);
$repository = $app->make(ArchiveRepository::class);

// Créer une archive
$archive = $repository->create(ArchiveRecord::from([
    'table_name' => 'users',
    'row_id' => 1,
    'model_class' => User::class,
    'data' => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
    'last_save_at' => now()->toIso8601String(),
]));

// Générer le fichier de backup
$filePath = $helper->createBackupFile($archive);
echo "Backup created at: {$filePath}\n";

// Vérifier le contenu
$data = require $filePath;
print_r($data);
// ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']
```

## Voir aussi

- `BackupArchiveTask` - Tâche de création de backup
- `UpdateOrCreateArchiveTask` - Tâche de création/mise à jour d'archive
- `Archive` - Modèle d'archive
- `TothConfigInterface` - Interface de configuration
