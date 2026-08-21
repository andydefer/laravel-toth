# TothBackupDirective - Référence Technique

## Description

Commande CLI qui déclenche la création de sauvegardes pour les modèles configurables. Permet de filtrer par tables, de choisir la source (base de données ou fichiers de stockage) et de désactiver les barres de progression.

## Hiérarchie

```
AbstractDirective
    └── TothBackupDirective
```

**Interfaces implémentées :** `DirectiveInterface`

## Rôle principal

Orchestrer le processus de sauvegarde en fonction des paramètres de l'utilisateur. La directive interroge la configuration, résout les tables à sauvegarder, applique le mode `mute` si demandé, et délègue au `ArchiveService` l'exécution des sauvegardes depuis la base de données ou les fichiers de stockage.

## Installation

Cette directive est automatiquement enregistrée via le `LaravelTothServiceProvider`.

```bash
composer require andydefer/laravel-toth
```

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la commande CLI au format Laravel Directive.

```php
public function getSignature(): string
{
    return 'toth:backup 
                {tables*}#"Tables to backup (e.g., users, posts)" 
                {--only-files}#"Backup only from storage files" 
                {--only-db}#"Backup only from database"
                {--mute}#"Disable progress bars"';
}
```

**Retourne :** `string` - La signature de la commande

**Exemple :**
```bash
./bin/task toth:backup [users,posts] --only-db --mute
```

---

### `getDescription(): string`

Retourne la description de la commande affichée dans l'aide.

```php
public function getDescription(): string
{
    return 'Create backups for archivable models. Specify tables or use flags to filter.';
}
```

**Retourne :** `string` - La description de la commande

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la commande.

```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['backup', 'bkp']);
}
```

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```bash
./bin/task backup [users,posts] --mute
./bin/task bkp [users,posts] --only-files
```

---

### `execute(): ExitCode`

Point d'entrée de la commande. Orchestre le processus de sauvegarde.

| Étape | Action |
|-------|--------|
| 1 | Récupère les tables depuis l'entrée utilisateur |
| 2 | Vérifie les flags mutuellement exclusifs |
| 3 | Applique le mode `mute` au service |
| 4 | Délègue au `ArchiveService` la sauvegarde appropriée |

**Retourne :** `ExitCode` - `SUCCESS` ou `INVALID_ARGUMENT`

**Exceptions :** Aucune (les erreurs sont gérées en interne)

**Exemple :**
```php
$directive = new TothBackupDirective();
$exitCode = $directive->execute();
```

## Cas d'utilisation

### Cas 1 : Sauvegarde de toutes les tables configurées

**Problème :** L'utilisateur souhaite sauvegarder tous les modèles définis dans la configuration.

**Solution :** Exécuter la commande sans arguments ni flags.

```bash
./bin/task toth:backup
```

**Comportement :**
1. Récupère tous les modèles de la configuration `toth.archivables`
2. Sauvegarde en base de données ET en fichiers de stockage
3. Affiche la barre de progression (par défaut)

---

### Cas 2 : Sauvegarde sans barre de progression

**Problème :** L'utilisateur souhaite une sauvegarde silencieuse pour une exécution en cron.

**Solution :** Utiliser le flag `--mute`.

```bash
./bin/task toth:backup --mute
```

**Comportement :**
1. Toutes les barres de progression sont désactivées
2. Les sauvegardes sont exécutées en arrière-plan
3. Idéal pour les environnements de production ou les cron jobs

---

### Cas 3 : Sauvegarde de tables spécifiques depuis les fichiers

**Problème :** L'utilisateur souhaite restaurer uniquement les tables `users` et `posts` depuis les fichiers de backup.

**Solution :** Spécifier les tables et utiliser le flag `--only-files`.

```bash
./bin/task toth:backup [users,posts] --only-files
```

**Comportement :**
1. Filtre uniquement les tables `users` et `posts`
2. Sauvegarde UNIQUEMENT depuis les fichiers de stockage
3. Les barres de progression sont affichées

---

### Cas 4 : Utilisation des alias

**Problème :** L'utilisateur préfère une commande plus courte.

**Solution :** Utiliser l'alias `backup` ou `bkp`.

```bash
./bin/task backup [users,posts] --mute
./bin/task bkp [users,posts] --only-files
```

---

### Cas 5 : Sauvegarde complète en mode silencieux

**Problème :** L'utilisateur souhaite une sauvegarde complète sans aucune sortie.

**Solution :** Combiner `--mute` avec les autres flags.

```bash
./bin/task toth:backup [users,posts] --only-db --mute
```

**Comportement :**
1. Sauvegarde UNIQUEMENT depuis la base de données
2. Aucune barre de progression n'est affichée
3. Parfait pour les scripts automatisés

## Flux d'exécution

```
Utilisateur → toth:backup [tables] [flags]
    ↓
getTablesFromInput()
    ├── Si tables spécifiées → utilisation
    └── Sinon → lecture de la configuration
    ↓
Vérification des flags mutuellement exclusifs
    ├── --only-files ET --only-db → Erreur
    └── Sinon → continuer
    ↓
Récupération du ArchiveService
    ↓
Application du mode mute
    ├── --mute présent → setMute(true)
    └── Sinon → setMute(false)
    ↓
Résolution du mode de sauvegarde
    ├── --only-files → backupFromFiles()
    ├── --only-db → backupFromModels()
    └── Aucun flag → backupFromModels() + backupFromFiles()
    ↓
Affichage du message de succès
    ↓
Return ExitCode::SUCCESS
```

## Gestion des erreurs

| Situation | Code retour | Message |
|-----------|-------------|---------|
| Flags mutuellement exclusifs | `ExitCode::INVALID_ARGUMENT` | `❌ You cannot use --only-files and --only-db at the same time` |
| Aucun modèle configuré | `ExitCode::SUCCESS` | Avertissement silencieux (aucune sauvegarde effectuée) |
| Classe de modèle non trouvée | `ExitCode::SUCCESS` | Ignorée silencieusement (skip du modèle) |

## Intégration

### Avec ArchiveService

La directive utilise `ArchiveService` pour exécuter les opérations de sauvegarde.

```php
$archiveService = $this->getApplication()->make(ArchiveServiceInterface::class);
$archiveService->setMute($mute);
$archiveService->backupFromModels($tables);
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
| Sauvegarde | O(n) | Délégue au `ArchiveService` |
| Mode mute | O(1) | Négligeable (désactive l'affichage) |

**Optimisations :**
- Les tâches de sauvegarde sont asynchrones (via `laravel-task`)
- Aucune opération bloquante dans la directive
- Le mode `mute` réduit la surcharge d'affichage

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

use AndyDefer\LaravelToth\Directives\TothBackupDirective;
use AndyDefer\LaravelToth\Services\ArchiveService;
use AndyDefer\Directive\Enums\ExitCode;
use Illuminate\Container\Container;

// Création du container et enregistrement des services
$app = Container::getInstance();
$app->singleton(ArchiveService::class, function () {
    return new ArchiveService(
        config: $app->make(TothConfigInterface::class),
        taskService: $app->make(UniqueTaskServiceInterface::class),
        archiveRepository: $app->make(ArchiveRepository::class),
        progress: $app->make(ProgressManager::class),
    );
});

// Exécution de la directive avec mode mute
$directive = new TothBackupDirective();
$exitCode = $directive->execute();

if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Backup process completed successfully\n";
} else {
    echo "❌ Backup process failed\n";
}
```

## Voir aussi

- `TothRestoreDirective` - Commande de restauration
- `ArchiveService` - Service d'archivage et de sauvegarde
- `TothConfigInterface` - Interface de configuration
- `ProgressManager` - Gestionnaire de barres de progression