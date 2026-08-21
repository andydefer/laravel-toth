<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Services\Visitors;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that discovers Eloquent model classes in PHP files.
 *
 * Traverses the abstract syntax tree to identify classes that extend
 * Illuminate\Database\Eloquent\Model and collects their fully qualified
 * class names. Handles namespace aliases and ignores abstract classes.
 */
final class ModelDiscoveryVisitor extends NodeVisitorAbstract
{
    /** @var array<int, string> List of discovered model FQCNs */
    private array $models = [];

    private ?string $currentNamespace = null;

    /** @var array<string, string> Aliases mapping (alias => FQCN) */
    private array $aliases = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name?->toString();

            return null;
        }

        if ($node instanceof Use_) {
            $this->registerAliases($node);

            return null;
        }

        if ($node instanceof Class_) {
            $this->processClassNode($node);

            return null;
        }

        return null;
    }

    /**
     * Registers namespace aliases from a Use_ node.
     */
    private function registerAliases(Use_ $node): void
    {
        foreach ($node->uses as $use) {
            $alias = $use->alias !== null
                ? $use->alias->toString()
                : $use->name->getLast();

            $this->aliases[$alias] = $use->name->toString();
        }
    }

    /**
     * Processes a Class_ node and collects it if it's an Eloquent model.
     */
    private function processClassNode(Class_ $node): void
    {
        $className = $node->name->toString();

        if ($node->isAbstract()) {
            return;
        }

        $isEloquentModel = false;

        if ($node->extends !== null) {
            $parentName = $node->extends->toString();
            $isEloquentModel = $this->isEloquentModelParent($parentName);
        }

        if ($isEloquentModel && $this->currentNamespace !== null) {
            $this->models[] = $this->currentNamespace.'\\'.$className;
        }
    }

    /**
     * Determines whether a parent class name refers to Eloquent Model.
     */
    private function isEloquentModelParent(string $parentName): bool
    {
        $resolvedParentName = $parentName;

        foreach ($this->aliases as $alias => $fqcn) {
            if ($parentName === $alias) {
                $resolvedParentName = $fqcn;
                break;
            }
        }

        if ($resolvedParentName === Model::class) {
            return true;
        }

        if (class_exists($resolvedParentName)) {
            $parents = class_parents($resolvedParentName);

            if ($parents !== false && in_array(Model::class, $parents, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the list of discovered model FQCNs.
     *
     * @return array<int, string>
     */
    public function getModels(): array
    {
        return $this->models;
    }
}
