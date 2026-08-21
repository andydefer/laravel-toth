# TothDiscoveryDirective - Référence Technique

## Description

Commande CLI qui scanne les répertoires spécifiés pour découvrir automatiquement les modèles Eloquent et les ajoute à la configuration `toth.archivables`.

## Hiérarchie

```
AbstractDirective
    └── TothDiscoveryDirective
```

**Interfaces implémentées :** `DirectiveInterface`

## Rôle principal

Automatiser la configuration du package en découvrant tous les modèles Eloquent de l'application. La directive analyse les fichiers PHP via l'AST pour identifier les classes qui étendent `Model`, puis met à jour le fichier `config/toth.php` avec la liste des modèles trouvés.

## Installation

Cette directive est automatiquement enregistrée via le `LaravelTothServiceProvider`.

```bash
composer require andydefer/laravel-toth
```

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la commande CLI.

```php
public function getSignature(): string
{
    return 'toth:discover {sources*}#"Directories to scan (e.g., %%tests.Models, app.Models)"';
}
```

**Retourne :** `string` - La signature de la commande

**Exemple :**
```bash
./bin/task toth:discover [app.Models, app.Domain]
```

---

### `getDescription(): string`

Retourne la description de la commande.

```php
public function getDescription(): string
{
    return 'Discover and register Eloquent models as archivable in the configuration.';
}
```

**Retourne :** `string` - La description de la commande

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la commande.

```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['discover', 'scan']);
}
```

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```bash
./bin/task discover [app.Models]
./bin/task scan [app.Models]
```

---

### `execute(): ExitCode`

Point d'entrée de la commande. Orchestre le processus de découverte et d'enregistrement.

| Étape | Action |
|-------|--------|
| 1 | Récupère les sources depuis l'entrée utilisateur |
| 2 | Appelle le `DiscoveryService` pour scanner les dossiers |
| 3 | Affiche les modèles trouvés |
| 4 | Fusionne avec les modèles existants dans la config |
| 5 | Met à jour le fichier `config/toth.php` |

**Retourne :** `ExitCode` - `SUCCESS` ou `INVALID_ARGUMENT`

**Exceptions :** Aucune (les erreurs sont gérées en interne)

**Exemple :**
```php
$directive = new TothDiscoveryDirective();
$exitCode = $directive->execute();
```

## Cas d'utilisation

### Cas 1 : Découverte automatique des modèles de l'application

**Problème :** L'utilisateur souhaite configurer tous les modèles de son application sans les écrire manuellement.

**Solution :** Exécuter la commande sans arguments.

```bash
./bin/task toth:discover
```

**Comportement :**
1. Utilise `app.Models` par défaut
2. Scanne le dossier `app/Models`
3. Ajoute tous les modèles trouvés à la configuration

---

### Cas 2 : Découverte dans plusieurs dossiers

**Problème :** Les modèles sont répartis dans plusieurs dossiers (`app/Models`, `app/Domain`, `modules/Admin/Models`).

**Solution :** Spécifier plusieurs sources.

```bash
./bin/task toth:discover [app.Models, app.Domain, modules.Admin.Models]
```

---

### Cas 3 : Découverte depuis les tests

**Problème :** L'utilisateur souhaite ajouter les modèles de test à la configuration.

**Solution :** Utiliser la notation `%` pour remonter d'un niveau.

```bash
./bin/task toth:discover [%%tests.Fixtures.Models]
```

---

### Cas 4 : Utilisation des alias

**Problème :** L'utilisateur préfère une commande plus courte.

**Solution :** Utiliser l'alias `discover` ou `scan`.

```bash
./bin/task discover [app.Models]
./bin/task scan [app.Models]
```

## Flux d'exécution

```
Utilisateur → toth:discover [sources]
    ↓
getSourcesFromInput()
    ├── Si sources spécifiées → utilisation
    └── Sinon → 'app.Models'
    ↓
make(DiscoveryServiceInterface)
    ↓
discoverModels($sources)
    ├── Scanner les dossiers
    ├── Analyser les fichiers PHP
    └── Retourner les FQCN trouvés
    ↓
Si aucun modèle trouvé
    └── Afficher un avertissement → ExitCode::SUCCESS
    ↓
Afficher les modèles trouvés
    ↓
Récupérer la configuration actuelle
    ↓
Fusionner et trier les modèles
    ↓
saveConfiguration()
    ├── Lire config_path('toth.php')
    ├── Formater les modèles avec ::class
    ├── Remplacer la clé 'archivables'
    └── Écrire le fichier
    ↓
ExitCode::SUCCESS
```

## Gestion des erreurs

| Situation | Code retour | Message |
|-----------|-------------|---------|
| Aucun modèle trouvé | `ExitCode::SUCCESS` | `No Eloquent models found in the specified directories.` |
| Fichier de config introuvable | `ExitCode::SUCCESS` | `Configuration file not found. Please publish the config first.` |
| Échec de mise à jour du fichier | `ExitCode::SUCCESS` | `❌ Failed to update configuration file.` |

## Intégration

### Avec DiscoveryService

```php
$discoveryService = $this->getApplication()->make(DiscoveryServiceInterface::class);
$models = $discoveryService->discoverModels($sources);
```

### Avec la configuration Laravel

```php
$config = $this->getApplication()->make('config');
$config->set('toth.archivables', $merged);
```

### Avec ConsoleWriter

```php
$this->getConsole()->alertWarning('No Eloquent models found');
$this->info('✅ Configuration file updated successfully.');
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `discoverModels()` | O(n × m) | Dépend du nombre de fichiers et dossiers |
| `file_get_contents()` | O(k) | k = taille du fichier de config |
| `preg_replace()` | O(k) | dépend de la taille du fichier |

**Optimisations :**
- La découverte est effectuée une seule fois par exécution
- Les résultats sont fusionnés avec la config existante (pas de perte)

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

use AndyDefer\LaravelToth\Directives\TothDiscoveryDirective;
use AndyDefer\LaravelToth\Services\DiscoveryService;
use AndyDefer\Directive\Enums\ExitCode;
use Illuminate\Container\Container;

$app = Container::getInstance();

// Exécution de la directive
$directive = new TothDiscoveryDirective();

// Simuler l'exécution en ligne de commande
$exitCode = $directive->execute();

if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Models discovered and registered successfully\n";
} else {
    echo "❌ Discovery process failed\n";
}
```

## Voir aussi

- `DiscoveryService` - Service de découverte des modèles
- `DiscoveryServiceInterface` - Interface du service
- `TothBackupDirective` - Directive de sauvegarde
- `TothRestoreDirective` - Directive de restauration