# DiscoveryService - Référence Technique

## Description

Service responsable de la découverte des modèles Eloquent dans le système de fichiers. Analyse les fichiers PHP via l'AST (Abstract Syntax Tree) pour identifier les classes qui étendent `Illuminate\Database\Eloquent\Model`.

## Hiérarchie

```
DiscoveryServiceInterface
    └── DiscoveryService
```

## Rôle principal

Scanner récursivement les répertoires spécifiés, parser les fichiers PHP, et collecter les noms complets (FQCN) de toutes les classes qui sont des modèles Eloquent. Utilise l'analyse AST pour une détection fiable, même avec des alias de namespace complexes.

## Installation

Le service est automatiquement enregistré via le `LaravelTothServiceProvider`.

```php
$discoveryService = $app->make(DiscoveryServiceInterface::class);
```

## API / Méthodes publiques

### `discoverModels(array $sources): array`

Scanne les répertoires spécifiés et retourne la liste des modèles Eloquent découverts.

```php
public function discoverModels(array $sources): array
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sources` | `array<string>` | Liste des sources à scanner (notation pointée ou % pour remonter) |

**Retourne :** `array<int, string>` - Liste des FQCN des modèles découverts

**Exceptions :** Aucune (les erreurs de parsing sont ignorées silencieusement)

**Exemple :**
```php
$models = $discoveryService->discoverModels([
    'app.Models',
    '%%tests.Fixtures.Models',
]);
// Retourne : ['App\\Models\\User', 'App\\Models\\Post', ...]
```

## Cas d'utilisation

### Cas 1 : Découverte des modèles dans l'application

**Problème :** L'utilisateur souhaite configurer automatiquement tous les modèles de son application.

**Solution :** Scanner le dossier `app/Models` avec la notation pointée.

```php
$models = $discoveryService->discoverModels(['app.Models']);
// Retourne tous les modèles Eloquent trouvés
```

---

### Cas 2 : Découverte depuis un dossier de test

**Problème :** L'utilisateur souhaite scanner un dossier spécifique pour les tests.

**Solution :** Utiliser la notation `%` pour remonter d'un niveau.

```php
$models = $discoveryService->discoverModels(['%%tests.Fixtures.Models']);
// Remonte de deux niveaux depuis la racine du projet
```

---

### Cas 3 : Découverte dans plusieurs dossiers

**Problème :** Les modèles sont répartis dans plusieurs dossiers.

**Solution :** Passer plusieurs sources en une seule fois.

```php
$models = $discoveryService->discoverModels([
    'app.Models',
    'app.Domain.Entities',
    'modules.Admin.Models',
]);
```

## Flux d'exécution

```
discoverModels(array $sources)
    ↓
Pour chaque source → resolvePath()
    ├── Si source commence par % → resolveRelativePath()
    │       ├── Compter le nombre de %
    │       ├── Convertir la notation pointée en chemin
    │       └── Ajouter le préfixe ".." approprié
    └── Sinon → Convertir la notation pointée en chemin
    ↓
scanDirectory()
    ├── Vérifier que le dossier existe
    └── scanDirectoryRecursive()
        ├── Parcourir les fichiers .php
        ├── extractModelsFromFile()
        │   ├── Parser le fichier avec PhpParser
        │   ├── Traverser l'AST avec ModelDiscoveryVisitor
        │   └── Retourner les FQCN trouvés
        └── Parcourir les sous-dossiers (max depth: 4)
    ↓
array_unique($models)
    ↓
Retourner la liste des modèles
```

## Gestion des erreurs

| Situation | Gestion | Détail |
|-----------|---------|--------|
| Fichier PHP invalide | Ignoré | Erreur de parsing ignorée silencieusement |
| Dossier inexistant | Ignoré | Le service continue avec les autres sources |
| Fichier non lisible | Ignoré | L'exception est capturée et ignorée |
| AST null | Ignoré | Aucun modèle n'est extrait du fichier |

## Intégration

### Avec DiscoveryServiceInterface

```php
use AndyDefer\LaravelToth\Contracts\Services\DiscoveryServiceInterface;

$discoveryService = $app->make(DiscoveryServiceInterface::class);
```

### Avec ModelDiscoveryVisitor

```php
$visitor = new ModelDiscoveryVisitor();
$traverser->addVisitor($visitor);
$models = $visitor->getModels();
```

### Avec FileSystemInterface

```php
$files = $this->fileSystem->glob($directory . '/*.php');
$content = $this->fileSystem->get($file);
```

### Avec TothDiscoveryDirective

```php
// Dans TothDiscoveryDirective
$models = $discoveryService->discoverModels($sources);
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `resolvePath()` | O(1) | Négligeable |
| `scanDirectoryRecursive()` | O(n × m) | n = nombre de dossiers, m = nombre de fichiers |
| `extractModelsFromFile()` | O(k) | k = taille du fichier PHP |
| `array_unique()` | O(p) | p = nombre de modèles trouvés |

**Optimisations :**
- Profondeur de scan limitée à 4 niveaux (évite les scans inutiles)
- Les fichiers non PHP sont ignorés
- Les erreurs de parsing ne stoppent pas le scan

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

use AndyDefer\LaravelToth\Services\DiscoveryService;
use AndyDefer\PhpServices\FileSystem;
use PhpParser\ParserFactory;

$fileSystem = new FileSystem();
$parser = (new ParserFactory())->createForNewestSupportedVersion();

$discoveryService = new DiscoveryService($fileSystem, $parser);

// Découverte depuis plusieurs sources
$sources = [
    'app.Models',
    '%%tests.Fixtures.Models',
];

$models = $discoveryService->discoverModels($sources);

foreach ($models as $model) {
    echo "Discovered: {$model}\n";
}
// Résultat :
// Discovered: App\Models\User
// Discovered: App\Models\Product
// Discovered: App\Models\Order
```

## Voir aussi

- `DiscoveryServiceInterface` - Interface du service
- `ModelDiscoveryVisitor` - Visitor AST pour la découverte
- `TothDiscoveryDirective` - Directive CLI utilisant ce service
- `Paths` - Helper de résolution des chemins
