# TothRestoreDirective - Référence Technique

## Description

Commande CLI qui déclenche la restauration de données à partir des archives. Permet de filtrer par tables et de choisir la source (base de données ou fichiers de stockage).

## Hiérarchie

```
AbstractDirective
    └── TothRestoreDirective
```

**Interfaces implémentées :** `DirectiveInterface`

## Rôle principal

Orchestrer le processus de restauration en fonction des paramètres de l'utilisateur. La directive interroge la configuration, résout les tables à restaurer, et délègue au `ArchiveService` l'exécution des restaurations depuis la base de données ou les fichiers de stockage.

## Installation

Cette directive est automatiquement enregistrée lorsque le package est installé via le `LaravelTothServiceProvider`.

```bash
composer require andydefer/laravel-toth
```

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la commande CLI au format Laravel Directive.

```php
public function getSignature(): string
{
    return 'toth:restore 
                {tables*}#"Tables to restore (e.g., users, posts)" 
                {--only-files}#"Restore only from storage files" 
                {--only-db}#"Restore only from database"';
}
```

**Retourne :** `string` - La signature de la commande

**Exemple :**
```bash
./bin/task toth:restore [users,posts] --only-db
```

---

### `getDescription(): string`

Retourne la description de la commande affichée dans l'aide.

```php
public function getDescription(): string
{
    return 'Restore data from archives. Specify tables or use flags to filter.';
}
```

**Retourne :** `string` - La description de la commande

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la commande.

```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['restore', 'rst']);
}
```

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```bash
./bin/task restore [users,posts]
./bin/task rst [users,posts]
```

---

### `execute(): ExitCode`

Point d'entrée de la commande. Orchestre le processus de restauration.

| Étape | Action |
|-------|--------|
| 1 | Récupère les tables depuis l'entrée utilisateur |
| 2 | Vérifie les flags mutuellement exclusifs |
| 3 | Délègue au `ArchiveService` la restauration appropriée |

**Retourne :** `ExitCode` - `SUCCESS` ou `INVALID_ARGUMENT`

**Exceptions :** Aucune (les erreurs sont gérées en interne)

**Exemple :**
```php
$directive = new TothRestoreDirective();
$exitCode = $directive->execute();
```

## Cas d'utilisation

### Cas 1 : Restauration de toutes les tables configurées

**Problème :** L'utilisateur souhaite restaurer tous les modèles définis dans la configuration.

**Solution :** Exécuter la commande sans arguments ni flags.

```bash
./bin/task toth:restore
```

**Comportement :**
1. Récupère tous les modèles de la configuration `toth.archivables`
2. Restaure depuis la base de données ET les fichiers de stockage

---

### Cas 2 : Restauration uniquement depuis la base de données

**Problème :** L'utilisateur souhaite restaurer uniquement depuis les archives en base de données.

**Solution :** Utiliser le flag `--only-db`.

```bash
./bin/task toth:restore --only-db
```

**Comportement :**
1. Récupère tous les modèles de la configuration
2. Restaure UNIQUEMENT depuis la base de données

---

### Cas 3 : Restauration de tables spécifiques depuis les fichiers

**Problème :** L'utilisateur souhaite restaurer uniquement les tables `users` et `posts` depuis les fichiers de backup.

**Solution :** Spécifier les tables et utiliser le flag `--only-files`.

```bash
./bin/task toth:restore [users,posts] --only-files
```

**Comportement :**
1. Filtre uniquement les tables `users` et `posts`
2. Restaure UNIQUEMENT depuis les fichiers de stockage

---

### Cas 4 : Utilisation des alias

**Problème :** L'utilisateur préfère une commande plus courte.

**Solution :** Utiliser l'alias `restore` ou `rst`.

```bash
./bin/task restore [users,posts]
./bin/task rst [users,posts]
```

## Flux d'exécution

```
Utilisateur → toth:restore [tables] [flags]
    ↓
getTablesFromInput()
    ├── Si tables spécifiées → utilisation
    └── Sinon → lecture de la configuration
    ↓
Vérification des flags mutuellement exclusifs
    ├── --only-files ET --only-db → Erreur
    └── Sinon → continuer
    ↓
Résolution du mode de restauration
    ├── --only-files → restoreFromFiles()
    ├── --only-db → restoreFromModels()
    └── Aucun flag → restoreFromModels() + restoreFromFiles()
    ↓
Affichage du message de succès
    ↓
Return ExitCode::SUCCESS
```

## Gestion des erreurs

| Situation | Code retour | Message |
|-----------|-------------|---------|
| Flags mutuellement exclusifs | `ExitCode::INVALID_ARGUMENT` | `❌ You cannot use --only-files and --only-db at the same time` |
| Aucun modèle configuré | `ExitCode::SUCCESS` | Avertissement silencieux (aucune restauration effectuée) |
| Classe de modèle non trouvée | `ExitCode::SUCCESS` | Ignorée silencieusement (skip du modèle) |
| Aucune archive trouvée | `ExitCode::SUCCESS` | La restauration continue, les tâches échoueront individuellement |

## Intégration

### Avec ArchiveService

La directive utilise `ArchiveService` pour exécuter les opérations de restauration.

```php
$archiveService = $this->getApplication()->make(ArchiveService::class);
$archiveService->restoreFromModels($tables);
```

### Avec TothConfig

La directive récupère la configuration via `TothConfigInterface`.

```php
$config = $this->getApplication()->make(TothConfigInterface::class);
$archivables = $config->getArchivables();
```

### Avec ConsoleWriter

La directive utilise `ConsoleWriter` pour afficher les messages d'erreur formatés.

```php
$this->getConsole()->raw(KeyValue::renderWithValueColor(
    MapCollection::from([...]),
    'red'
));
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| Récupération des tables | O(n) | Dépend du nombre de modèles configurés |
| Résolution des flags | O(1) | Négligeable |
| Restauration | O(n) | Délégue au `ArchiveService` |

**Optimisations :**
- Les tâches de restauration sont asynchrones (via `laravel-task`)
- Aucune opération bloquante dans la directive

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

use AndyDefer\LaravelToth\Directives\TothRestoreDirective;
use AndyDefer\LaravelToth\Services\ArchiveService;
use AndyDefer\Directive\Enums\ExitCode;
use Illuminate\Container\Container;

// Création du container et enregistrement des services
$app = Container::getInstance();
$app->singleton(ArchiveService::class, function () {
    return new ArchiveService(
        config: $app->make(TothConfigInterface::class),
        taskService: $app->make(UniqueTaskServiceInterface::class),
        archiveRepository: $app->make(ArchiveRepository::class)
    );
});

// Exécution de la directive
$directive = new TothRestoreDirective();
$exitCode = $directive->execute();

if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Restore process completed successfully\n";
} else {
    echo "❌ Restore process failed\n";
}
```

## Voir aussi

- `TothBackupDirective` - Commande de sauvegarde
- `ArchiveService` - Service d'archivage et de restauration
- `TothConfigInterface` - Interface de configuration
- `RestoreArchiveTask` - Tâche de restauration
