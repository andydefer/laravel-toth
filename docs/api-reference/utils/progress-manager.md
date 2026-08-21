# ProgressManager - Référence Technique

## Description

Utilitaire de gestion des barres de progression dans la console. Fournit une interface propre pour afficher et mettre à jour des barres de progression avec throttling pour éviter un rendu excessif.

## Hiérarchie

```
ProgressManager
```

## Rôle principal

Gérer l'affichage interactif des barres de progression dans le terminal. Le manager utilise `VirtualTerminalService` pour un rendu en temps réel avec mise à jour des zones spécifiques sans rafraîchir tout l'écran.

## Installation

Le manager est automatiquement injecté via le conteneur Laravel.

```php
$progress = $app->make(ProgressManager::class);
```

## API / Méthodes publiques

### `start(string $label, int $total): void`

Démarre une nouvelle barre de progression.

```php
public function start(string $label, int $total): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$label` | `string` | Le libellé à afficher |
| `$total` | `int` | Le nombre total d'éléments à traiter |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$progress->start('📦 Backing up models', 100);
```

---

### `update(int $current, string $detail = ''): void`

Met à jour la barre de progression.

```php
public function update(int $current, string $detail = ''): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$current` | `int` | La progression actuelle |
| `$detail` | `string` | Détail optionnel à afficher |

**Retourne :** `void`

**Exceptions :** Aucune (ignore les appels si inactive)

**Exemple :**
```php
$progress->update(50, '📄 Processing users');
```

---

### `advance(string $detail = ''): void`

Incrémente la progression de un.

```php
public function advance(string $detail = ''): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$detail` | `string` | Détail optionnel à afficher |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$progress->advance('✅ User 42 processed');
```

---

### `finish(string $message): void`

Termine la barre de progression.

```php
public function finish(string $message): void
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message de completion |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$progress->finish('✅ Backup completed');
```

---

### `isActive(): bool`

Vérifie si la barre de progression est active.

```php
public function isActive(): bool
```

**Retourne :** `bool` - `true` si active, `false` sinon

**Exemple :**
```php
if ($progress->isActive()) {
    $progress->update(50);
}
```

---

### `getProgress(): int`

Retourne la progression actuelle.

```php
public function getProgress(): int
```

**Retourne :** `int` - La valeur de progression actuelle

**Exemple :**
```php
$current = $progress->getProgress(); // 50
```

---

### `getTotal(): int`

Retourne le total.

```php
public function getTotal(): int
```

**Retourne :** `int` - La valeur totale

**Exemple :**
```php
$total = $progress->getTotal(); // 100
```

## Cas d'utilisation

### Cas 1 : Sauvegarde de modèles

**Problème :** L'utilisateur souhaite voir la progression d'une sauvegarde de 1000 modèles.

**Solution :** Utiliser `ProgressManager` pour afficher une barre de progression.

```php
$models = User::all();
$total = $models->count();

$progress->start('📦 Backing up users', $total);

foreach ($models as $index => $model) {
    $archiveService->createOrUpdateArchive($model);
    $progress->update($index + 1, "📄 User {$model->id}");
}

$progress->finish('✅ Users backed up');
```

---

### Cas 2 : Restauration depuis des fichiers

**Problème :** L'utilisateur souhaite voir la progression de la restauration de fichiers.

**Solution :** Utiliser `advance()` pour incrémenter automatiquement.

```php
$files = File::files($backupPath);
$total = count($files);

$progress->start('📂 Restoring from files', $total);

foreach ($files as $file) {
    $this->restoreFromFile($file);
    $progress->advance("📄 " . basename($file));
}

$progress->finish('✅ Files restored');
```

---

### Cas 3 : Mode silencieux (mute)

**Problème :** L'utilisateur souhaite exécuter une opération sans barre de progression.

**Solution :** Vérifier la configuration avant d'utiliser `ProgressManager`.

```php
if (!$mute) {
    $progress->start('📦 Processing', $total);
}

// ... traitement ...

if (!$mute) {
    $progress->finish('✅ Completed');
}
```

## Flux d'exécution

```
start()
    ↓
vt->clear()
vt->add('label')
vt->add('progress')
vt->add('detail')
vt->add('count')
vt->render()
    ↓
update() / advance()
    ↓
vt->update('progress')
vt->update('detail')
vt->update('count')
renderWithThrottle()
    ↓
finish()
    ↓
vt->update('progress', 100%)
vt->remove('detail')
vt->remove('count')
vt->update('label', message)
vt->render()
console->newLine()
isActive = false
```

## Gestion des erreurs

| Situation | Comportement | Détail |
|-----------|--------------|--------|
| Appel `update()` après `finish()` | Ignoré | La barre est inactive |
| Appel `advance()` après `finish()` | Ignoré | La barre est inactive |
| Appel `finish()` sans `start()` | Ignoré | La barre n'est pas active |
| `total = 0` | Barre à 0% | Aucune division par zéro |

## Intégration

### Avec ArchiveService

```php
public function backupFromModels(array $tables = []): void
{
    // ...
    if (!$this->mute) {
        $this->progress->start('📦 Backing up models', $totalRecords);
    }
    // ...
    if (!$this->mute) {
        $this->progress->update($processed, "📦 {$tableName}");
    }
    // ...
    if (!$this->mute) {
        $this->progress->finish('✅ Backup completed');
    }
}
```

### Avec VirtualTerminalService

```php
$this->vt->add('label', "{$label}...");
$this->vt->update('progress', $this->buildProgressBar($current, $total));
$this->vt->render();
```

## Performance

| Opération | Complexité | Impact |
|-----------|------------|--------|
| `start()` | O(1) | Initialisation du terminal |
| `update()` | O(1) | Mise à jour des zones |
| `renderWithThrottle()` | O(1) | Rend toutes les 600ms max |
| `buildProgressBar()` | O(n) | n = largeur de la barre (40) |

**Optimisations :**
- Throttle à 600ms pour éviter les surcharges
- Utilisation de `VirtualTerminalService` pour un rendu efficace
- Pas de boucles ni d'allocations lourdes

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

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelToth\Utils\ProgressManager;

$console = new Console();
$progress = new ProgressManager($console);

// Démarrer la barre
$progress->start('📦 Processing items', 50);

// Traiter les éléments
for ($i = 1; $i <= 50; $i++) {
    usleep(100000); // Simuler du travail
    $progress->update($i, "📄 Item {$i}");
}

// Terminer
$progress->finish('✅ All items processed');
```

## Voir aussi

- `VirtualTerminalService` - Service de rendu interactif
- `ArchiveService` - Service utilisant le gestionnaire
- `Console` - Console pour l'affichage
---