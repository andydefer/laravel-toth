<?php

// tests/Integration/Services/Visitors/ModelDiscoveryVisitorTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Services\Visitors;

use AndyDefer\LaravelToth\Services\Visitors\ModelDiscoveryVisitor;
use AndyDefer\LaravelToth\Tests\Fixtures\CodeSnippets\ModelSnippets;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\Product;
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
        $content = ModelSnippets::SIMPLE_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_discovers_model_with_alias(): void
    {
        $content = ModelSnippets::MODEL_WITH_ALIAS;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_discovers_model_with_trait(): void
    {
        $content = ModelSnippets::MODEL_WITH_TRAIT;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\Product', $models);
    }

    public function test_visitor_ignores_abstract_classes(): void
    {
        $content = ModelSnippets::ABSTRACT_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertNotContains('App\\Models\\AbstractModel', $models);
        $this->assertContains('App\\Models\\ConcreteModel', $models);
    }

    public function test_visitor_discovers_multiple_models(): void
    {
        $content = ModelSnippets::MULTIPLE_MODELS;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('AndyDefer\\LaravelToth\\Tests\\Fixtures\\Models\\TestUser', $models);
        $this->assertContains('AndyDefer\\LaravelToth\\Tests\\Fixtures\\Models\\TestProduct', $models);
        $this->assertCount(2, $models);
    }

    public function test_visitor_ignores_non_model_classes(): void
    {
        $content = ModelSnippets::NON_MODEL_CLASSES;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertEmpty($models);
    }

    public function test_visitor_ignores_interface_and_trait(): void
    {
        $content = ModelSnippets::INTERFACE_AND_TRAIT;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertNotContains('App\\Models\\ModelInterface', $models);
        $this->assertNotContains('App\\Models\\ModelTrait', $models);
        $this->assertContains('App\\Models\\ConcreteModel', $models);
    }

    public function test_visitor_handles_nested_namespace(): void
    {
        $content = ModelSnippets::NESTED_NAMESPACE;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\Users\\AdminUser', $models);
        $this->assertContains('App\\Models\\Users\\RegularUser', $models);
    }

    public function test_visitor_discovers_model_with_fillable(): void
    {
        $content = ModelSnippets::MODEL_WITH_FILLABLE;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\Post', $models);
    }

    public function test_visitor_discovers_model_with_casts(): void
    {
        $content = ModelSnippets::MODEL_WITH_CASTS;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\Order', $models);
    }

    public function test_visitor_discovers_model_extending_laravel_user(): void
    {
        $content = ModelSnippets::MODEL_EXTENDING_LARAVEL_USER;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_discovers_model_with_custom_base_class(): void
    {
        // Lire le fichier réel
        $productPath = __DIR__.'/../../../Fixtures/Models/Product.php';
        $content = file_get_contents($productPath);

        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains(Product::class, $models);
    }

    public function test_visitor_discovers_model_extending_native_user(): void
    {
        $content = ModelSnippets::MODEL_EXTENDING_NATIVE_USER;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_discovers_user_model_with_all_features(): void
    {
        $content = ModelSnippets::USER_MODEL_WITH_ALL_FEATURES;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertContains('App\\Models\\User', $models);
    }

    public function test_visitor_ignores_class_with_model_in_name_but_not_model(): void
    {
        $content = ModelSnippets::CLASS_WITH_MODEL_IN_NAME_BUT_NOT_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new ModelDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $models = $visitor->getModels();

        $this->assertEmpty($models);
    }
}
