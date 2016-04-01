<?php

use Doctrine\Common\Annotations\{AnnotationReader, AnnotationRegistry};

use Schale\Schema\Type\StringPrimitive;
use Schale\Schema\Type\NumberPrimitive;
use Schale\Schema\TypeRegistry;
use Schale\Schema\Engine;
use Schale\Schema\FqcnLoader;
use Schale\Interfaces\Schema\SchemaTypeInterface;
use Schale\AnnotationLoader;

class ModelInstantiationTest extends PHPUnit_Framework_TestCase
{
    public $stringPrimitive;
    public $numberPrimitive;
    public $typeRegistry;
    public $annotationReader;
    public $schemaEngine;
    public $modelFqcns;

    public function setUp()
    {
        $this->stringPrimitive = new StringPrimitive();
        $this->numberPrimitive = new NumberPrimitive();

        $this->typeRegistry = new TypeRegistry(
            $this->stringPrimitive,
            $this->numberPrimitive);

        $this->annotationReader = new AnnotationReader();

        $this->schemaEngine = new Engine(
            $this->typeRegistry,
            $this->annotationReader);

        $annotationLoader = new AnnotationLoader();
        AnnotationRegistry::registerLoader([$annotationLoader, 'load']);

        $fqcnLoader = new FqcnLoader();
        $this->modelFqcns = $fqcnLoader->getFqcnsForPath(
            __DIR__ . '/../support/Mock/Model/');

        $this->schemaEngine->loadSchemaForModels($this->modelFqcns);
    }

    public function loadDataFromJsonFile(string $filename)
    {
        $json_string = file_get_contents(
            __DIR__ . '/data/' . $filename);
        $json_data = json_decode($json_string);

        if ($json_data === null) {
            throw new \Exception('Error parsing JSON from the filesystem');
        }

        return $json_data;
    }

    /**
     * Test we can load JSON representing a single TagModel object
     */
    public function testLoadingTagObjectJson()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'sample_tag_object.json');
        $rootModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\TagModel';

        $modelInstance = $this
            ->schemaEngine
            ->createModelInstanceFromData($rootModelFqcn, $jsonData);

        // Created object should be a TagModel instance
        $this->assertInstanceOf($rootModelFqcn, $modelInstance);

        $this->assertEquals(6001, $modelInstance->getId());
        $this->assertEquals("beach", $modelInstance->getName());
    }

    /**
     * Test we can load JSON representing a ResponseModel, following our
     * current "reduced" schema.
     *
     * The reduced schema includes the basic structure of a response,
     * including a payload with a list of modules. However, the included
     * "article module" doesn't have the full set of properties (eg
     * author, chapters, images) seen in the sample data from Tourism
     * Media
     */
    public function testLoadingReducedJson()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'reduced_sample_article_response_001.json');
        $responseModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\ResponseModel';

        $modelInstance = $this
            ->schemaEngine
            ->createModelInstanceFromData($responseModelFqcn, $jsonData);

        // Created object should be a ResponseModel instance
        $this->assertInstanceOf($responseModelFqcn, $modelInstance);

        $this->assertEquals(200, $modelInstance->getStatus());
        $this->assertEquals("OK", $modelInstance->getMessage());

        // Response should have a payload of PayloadModel instance)
        $payload = $modelInstance->getPayload();
        $this->assertInstanceOf('Schale\\Test\\Support\\Mock\\Model\\PayloadModel', $payload);

        // Payload should have a modules array, with exactly 1 item
        $modules = $payload->getModules();
        $this->assertEquals(1, count($modules));

        // The item should be an article module (ArticleModel instance)
        $article = $modules[0];
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel', $article);
        // Article's ID and region ID should have certain values
        $this->assertEquals(1001, $article->getId());
        $this->assertEquals("2001", $article->getRegionId());

        // Article's tags list should have a single tag
        $tags = $article->getTags();
        $this->assertEquals(1, count($tags));
        // The single tag should have ID "6001" and name "beach"
        $this->assertEquals(6001, $tags[0]->getId());
        $this->assertEquals("beach", $tags[0]->getName());
    }

    /**
     * Tests we can handle an empty typed array.
     *
     * We test this by loading JSON representing an article with no
     * tags.
     *
     */
    public function testEmptyTypedArray()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'article_module_with_empty_tags.json');
        $articleModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel';

        $article = $this
            ->schemaEngine
            ->createModelInstanceFromData($articleModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel', $article);

        // Article's ID and region ID should have certain values
        $this->assertEquals(1004, $article->getId());
        $this->assertEquals("2007", $article->getRegionId());

        // Article's tags list should be empty
        $tags = $article->getTags();
        $this->assertEquals(0, count($tags));
    }

    /**
     * Tests we can handle a typed array with multiple items.
     *
     * We test this by loading JSON representing an article with
     * multiple tags.
     *
     */
    public function testTypedArrayWithMultipleItems()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'article_module_with_multiple_tags.json');
        $articleModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel';

        $article = $this
            ->schemaEngine
            ->createModelInstanceFromData($articleModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel', $article);

        // Article's ID and region ID should have certain values
        $this->assertEquals(1003, $article->getId());
        $this->assertEquals("2005", $article->getRegionId());

        // Article's tags list should have 3 tags
        $tags = $article->getTags();
        $this->assertEquals(3, count($tags));
        // The first tag should have ID 6001 and name "beach"
        $this->assertEquals(6001, $tags[0]->getId());
        $this->assertEquals("beach", $tags[0]->getName());
        // The second tag should have ID 6002 and name "summer"
        $this->assertEquals(6002, $tags[1]->getId());
        $this->assertEquals("summer", $tags[1]->getName());
        // The second tag should have ID 6002 and name "summer"
        $this->assertEquals(6017, $tags[2]->getId());
        $this->assertEquals("colorful", $tags[2]->getName());
    }

    /**
     * Test we can handle an empty mixed object array.
     *
     * We test this by loading JSON representing a payload with no
     * modules.
     */
    public function testEmptyMixedObjectArray()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'payload_with_no_modules.json');
        $payloadModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\PayloadModel';

        $payload = $this
            ->schemaEngine
            ->createModelInstanceFromData($payloadModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\PayloadModel', $payload);

        // Payload's "modules" list should be empty
        $modules = $payload->getModules();
        $this->assertEquals(0, count($modules));
    }

    /**
     * Test we can handle a mixed object array with multiple items.
     *
     * We test this by loading JSON representing a payload with
     * multiple items in its "modules" property.
     *
     * One of these items is an article module, the other two are tag
     * objects. No, this structure doesn't make much sense in our real
     * app, but it gives us what we need to test our schema / model
     * hydrator system.
     */
    public function testMixedObjectArrayWithMultipleItems()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'payload_with_multiple_modules.json');
        $payloadModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\PayloadModel';

        $payload = $this
            ->schemaEngine
            ->createModelInstanceFromData($payloadModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\PayloadModel', $payload);

        // Payload's "modules" list should contain 3 objects
        $modules = $payload->getModules();
        $this->assertEquals(3, count($modules));

        // First object should be an article module
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel',
            $modules[0]);
        $this->assertEquals(1004, $modules[0]->getId());
        // Second should be a tag object
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\TagModel',
            $modules[1]);
        $this->assertEquals(6004, $modules[1]->getId());
        // Third should be a tag object
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\TagModel',
            $modules[2]);
        $this->assertEquals(6006, $modules[2]->getId());
    }

    /**
     * Test we can handle an object with no properties.
     *
     * We test this by loading a payload with a single item in its
     * "modules" array: an object of the specially created EmptyModel
     * type.
     */
    public function testObjectWithNoProperties()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'payload_with_empty_model_object.json');
        $payloadModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\PayloadModel';

        $payload = $this
            ->schemaEngine
            ->createModelInstanceFromData($payloadModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\PayloadModel', $payload);

        // Payload's "modules" list should contain 1 object
        $modules = $payload->getModules();
        $this->assertEquals(1, count($modules));

        // The single object should be an EmptyModel instance
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\EmptyModel',
            $modules[0]);
    }

    /**
     *
     * @expectedException Schale\Exception\Schema\RequiredPropertyMissingException
     */
    public function testObjectWithRequiredPropertyNotGiven()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'article_module_with_no_id.json');
        $articleModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel';

        $article = $this
            ->schemaEngine
            ->createModelInstanceFromData($articleModelFqcn, $jsonData);
    }

    /**
     *
     * @expectedException Schale\Exception\Schema\RequiredPropertyWasNullException
     */
    public function testObjectWithRequiredPropertySetToNull()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'article_module_with_id_set_to_null.json');
        $articleModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel';

        $article = $this
            ->schemaEngine
            ->createModelInstanceFromData($articleModelFqcn, $jsonData);
    }

    /**
     *
     */
    public function testObjectWithOptionalPropertyNotGiven()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'article_module_with_no_regionId.json');
        $articleModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel';

        $article = $this
            ->schemaEngine
            ->createModelInstanceFromData($articleModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel', $article);

        // Article's ID should have a value
        $this->assertEquals("1003", $article->getId());

        // Article's region ID should be null
        $this->assertNull($article->getRegionId());

        // Article's tags list should have a value
        $tags = $article->getTags();
        $this->assertEquals(3, count($tags));
    }

    /**
     *
     */
    public function testObjectWithOptionalPropertySetToNull()
    {
        $jsonData = $this->loadDataFromJsonFile(
            'article_module_with_regionId_set_to_null.json');
        $articleModelFqcn = 'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel';

        $article = $this
            ->schemaEngine
            ->createModelInstanceFromData($articleModelFqcn, $jsonData);
        $this->assertInstanceOf(
            'Schale\\Test\\Support\\Mock\\Model\\Module\\ArticleModel', $article);

        // Article's ID should have a value
        $this->assertEquals(1003, $article->getId());

        // Article's region ID should be null
        $this->assertNull($article->getRegionId());

        // Article's tags list should have a value
        $tags = $article->getTags();
        $this->assertEquals(3, count($tags));
    }
}
