# ModelDiscoveryVisitor - Référence Technique

## Description

Visitor AST (Abstract Syntax Tree) qui parcourt les fichiers PHP pour identifier les classes qui étendent `Illuminate\Database\Eloquent\Model`. Collecte leurs noms complets (FQCN) et gère les alias de namespace.

## Hiérarchie

```
PhpParser\NodeVisitorAbstract
    └── ModelDiscoveryVisitor
```

## Rôle principal

Analyser l'arbre syntaxique des fichiers PHP pour détecter les modèles Eloquent. Le visitor parcourt les nœuds de l'AST et collecte toutes les classes concrètes qui étendent `Model`, en tenant compte des alias de namespace et en ignorant les classes abstraites.

## Installation

Le visitor est utilisé par le `DiscoveryService` et n'est pas destiné à être utilisé directement en dehors du package.

```php
$visitor = new ModelDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);
$models = $visitor->getModels();
```

## API / Méthodes publiques

### `enterNode(Node $node): ?int`

Point d'entrée du visitor pour chaque nœud de l'AST.

```php
public function enterNode(Node $node): ?int
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$node` | `Node` | Le nœud AST à traiter |

**Retourne :** `?int` - `null` pour continuer la traversée

**Exceptions :** Aucune

**Exemple :**
```php
// Le visitor est appelé automatiquement par le traverser
$visitor = new ModelDiscoveryVisitor();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);
```

---

### `getModels(): array`

Retourne la liste des modèles Eloquent découverts.

```php
public function getModels(): array
```

**Retourne :** `array<int, string>` - Liste des FQCN des modèles découverts

**Exceptions :** Aucune

**Exemple :**
```php
$models = $visitor->getModels();
// ['App\\Models\\User', 'App\\Models\\Product']
```

## Cas d'utilisation

### Cas 1 : Découverte des modèles dans un fichier simple

**Problème :** Un fichier PHP contient une classe qui étend `Model`.

**Solution :** Le visitor détecte la classe et l'ajoute à la liste.

```php
// Fichier analysé
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
}

// Résultat
$visitor->getModels(); // ['App\\Models\\User']
```

---

### Cas 2 : Gestion des alias de namespace

**Problème :** Un fichier utilise un alias pour le `Model`.

**Solution :** Le visitor résout l'alias grâce à la table `aliases`.

```php
// Fichier analysé
namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    protected $table = 'users';
}

// Résultat
$visitor->getModels(); // ['App\\Models\\User']
```

---

### Cas 3 : Ignorance des classes abstraites

**Problème :** Une classe abstraite étend `Model`.

**Solution :** Le visitor ignore les classes abstraites.

```php
// Fichier analysé
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractModel extends Model
{
    protected $table = 'abstract_models';
}

class User extends AbstractModel
{
    protected $table = 'users';
}

// Résultat
$visitor->getModels(); // ['App\\Models\\User']
// AbstractModel est ignoré
```

## Flux d'exécution

```
enterNode(Node $node)
    ↓
├── Si $node est Namespace_ → currentNamespace = nom du namespace
├── Si $node est Use_ → registerAliases()
│       └── Pour chaque use → ajouter à $aliases[alias] = FQCN
└── Si $node est Class_ → processClassNode()
        ├── Récupérer le nom de la classe
        ├── Si classe est abstraite → ignorer
        ├── Si $node->extends !== null
        │       └── isEloquentModelParent($parentName)
        │               ├── Si parentName === Model::class → true
        │               ├── Si parentName est un alias vers Model → true
        │               └── Si dernier segment === 'Model' → true
        ├── Si est un modèle Eloquent ET currentNamespace !== null
        │       └── Ajouter currentNamespace\className à $models
```

## Gestion des erreurs

| Situation | Gestion | Détail |
|-----------|---------|--------|
| Nœud non reconnu | Ignoré | `enterNode()` retourne `null` |
| AST mal formé | Ignoré | Les erreurs sont gérées par le service appelant |

## Intégration

### Avec DiscoveryService

```php
// Dans DiscoveryService::extractModelsFromFile()
$visitor = new ModelDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

return $visitor->getModels();
```

### Avec PhpParser

```php
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($content);

$visitor = new ModelDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `enterNode()` | O(1) par nœud | Dépend de la taille du fichier |
| `registerAliases()` | O(u) | u = nombre de use statements |
| `processClassNode()` | O(1) | Négligeable |
| `isEloquentModelParent()` | O(a) | a = nombre d'aliases enregistrés |

**Optimisations :**
- Les classes abstraites sont ignorées rapidement
- Les alias sont résolus une seule fois par fichier

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PhpParser 4.x | ✅ Complet |
| PhpParser 5.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelToth\Services\Visitors\ModelDiscoveryVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

$content = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';
}

class Product extends Model
{
    protected $table = 'products';
}
PHP;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($content);

$visitor = new ModelDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

$models = $visitor->getModels();
// ['App\\Models\\User', 'App\\Models\\Product']
```

## Voir aussi

- `DiscoveryService` - Service utilisant ce visitor
- `PhpParser` - Bibliothèque d'analyse AST
- `NodeVisitorAbstract` - Classe parente du visitor