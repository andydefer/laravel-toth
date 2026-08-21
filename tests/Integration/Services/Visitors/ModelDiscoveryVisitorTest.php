<?php

// tests/Integration/Services/Visitors/ModelDiscoveryVisitorTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Services\Visitors;

use AndyDefer\LaravelToth\Services\Visitors\ModelDiscoveryVisitor;
use AndyDefer\LaravelToth\Tests\Fixtures\CodeSnippets\ModelSnippets;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

final class ModelDiscoveryVisitorTest extends IntegrationTestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = $this->app->make(Parser::class);
    }

    public function test_visitor_discovers_simple_model(): void
    {
        // Arrange
        $content = ModelSnippets::SIMPLE_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_discovers_model_with_alias(): void
    {
        // Arrange
        $content = ModelSnippets::MODEL_WITH_ALIAS;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_discovers_model_with_trait(): void
    {
        // Arrange
        $content = ModelSnippets::MODEL_WITH_TRAIT;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('App\\Models\\Product', $models);
    }

    public function test_visitor_ignores_abstract_classes(): void
    {
        // Arrange
        $content = ModelSnippets::ABSTRACT_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertNotContains('App\\Models\\AbstractModel', $models);
        $this->assertContains('App\\Models\\ConcreteModel', $models);
    }

    public function test_visitor_discovers_multiple_models(): void
    {
        // Arrange
        $content = ModelSnippets::MULTIPLE_MODELS;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('AndyDefer\\LaravelToth\\Tests\\Fixtures\\Models\\TestUser', $models);
        $this->assertContains('AndyDefer\\LaravelToth\\Tests\\Fixtures\\Models\\TestProduct', $models);
        $this->assertCount(2, $models);
    }

    public function test_visitor_ignores_non_model_classes(): void
    {
        // Arrange
        $content = ModelSnippets::NON_MODEL_CLASSES;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertEmpty($models);
    }

    public function test_visitor_ignores_interface_and_trait(): void
    {
        // Arrange
        $content = ModelSnippets::INTERFACE_AND_TRAIT;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertNotContains('App\\Models\\ModelInterface', $models);
        $this->assertNotContains('App\\Models\\ModelTrait', $models);
        $this->assertContains('App\\Models\\ConcreteModel', $models);
    }

    public function test_visitor_handles_nested_namespace(): void
    {
        // Arrange
        $content = ModelSnippets::NESTED_NAMESPACE;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('App\\Models\\Users\\AdminUser', $models);
        $this->assertContains('App\\Models\\Users\\RegularUser', $models);
    }

    public function test_visitor_discovers_model_with_fillable(): void
    {
        // Arrange
        $content = ModelSnippets::MODEL_WITH_FILLABLE;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('App\\Models\\Post', $models);
    }

    public function test_visitor_discovers_model_with_casts(): void
    {
        // Arrange
        $content = ModelSnippets::MODEL_WITH_CASTS;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        // Act
        $traverser->traverse($ast);
        $models = $visitor->getModels();

        // Assert
        $this->assertContains('App\\Models\\Order', $models);
    }
}
