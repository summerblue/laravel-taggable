<?php

use Illuminate\Database\Eloquent\Model as Eloquent;
use EstGroupe\Taggable\Taggable;
use Illuminate\Database\Capsule\Manager as DB;

class CommonUsageTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Eloquent::unguard();
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--realpath' => realpath(__DIR__.'/../migrations'),
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('taggable.tags_table_name', 'tags');
        $app['config']->set('taggable.taggables_table_name', 'taggables');

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        \Schema::create('books', function ($table) {
            $table->increments('id');
            $table->string('name');
            // is_tagged_label_enable == true
            $table->enum('is_tagged', array('yes', 'no'))->default('no');
            $table->timestamps();
        });
    }

    public function tearDown()
    {
        \Schema::drop('books');
    }

    public function test_tag_call()
    {
        $stub = Stub::create(['name'=>123]);

        $stub->tag('test123');
        $stub->tag('456');
        $stub->tag('third');
        $this->assertSame(['Test123', '456', 'Third'], $stub->tagNames());
    }

    public function test_untag_call()
    {
        $stub = Stub::create(['name'=>'Stub']);

        $stub->tag('one');
        $stub->tag('two');
        $stub->tag('three');

        $stub->untag('two');

        $this->assertArraysEqual(['Three', 'One'], $stub->tagNames());

        $stub->untag('ONE');
        $this->assertArraysEqual(['Three'], $stub->tagNames());
    }

    public function test_retag()
    {
        $stub = Stub::create(['name'=>123]);

        $stub->tag('first');
        $stub->tag('second');

        $stub->retag('foo, bar, another');
        $this->assertEquals(['Foo', 'Bar', 'Another'], $stub->tagNames());
    }

    public function test_unique()
    {
        $stub = Stub::create(['name'=>123]);

        $stub->tag('first');
        $stub->tag('first');
        $stub->tag('second');
        $stub->tag('bar');

        $stub->retag('first, foo, bar, another');
        $this->assertEquals(['First', 'Foo', 'Bar', 'Another'], $stub->tagNames());
    }

    public function test_tag_names_attribute()
    {
        $stub = Stub::create(['name'=>123, 'tag_names'=>'foo, bar']);

        $stub->save();

        $this->assertEquals(['Foo', 'Bar'], $stub->tagNames());
    }

    // Test Counter
    public function test_counter()
    {
        $stub1 = Stub::create(['name'=>123]);
        $stub2 = Stub::create(['name'=>123]);
        $stub3 = Stub::create(['name'=>123]);
        $stub4 = Stub::create(['name'=>123]);

        $stub1->tag('foo');

        $stub2->retag('foo', 'bar');

        $stub3->tag('foo');
        $stub3->untag('foo');
        $stub3->retag('foo');

        $tag = $stub1->tags()->first();
        $stub4->tagWithTagIds([$tag->id]);

        $this->assertEquals(4, $stub1->tags()->first()->count);
    }

    // Test is_tagged
    public function test_is_tagged_label()
    {
        config(['taggable.is_tagged_label_enable' => true]);

        $stub = Stub::create(['name'=>123]);

        $stub->tag('foo');
        $this->assertEquals('yes', $stub->is_tagged);

        $stub->untag('foo');
        $this->assertEquals('no', $stub->is_tagged);

        $stub->retag('foo');
        $this->assertEquals('yes', $stub->is_tagged);

        $stub->untag();    // remove all tags
        $this->assertEquals('no', $stub->is_tagged);
    }

    // Test existingTags
    public function test_existing_tags()
    {
        $stub1 = Stub::create(['name'=>123]);
        $stub2 = Stub::create(['name'=>123]);

        $stub1->tag('foo');
        $stub2->retag('bar');

        $tag_names = Stub::existingTags()->lists('name')->all();
        $this->assertEquals(['Foo', 'Bar'], $tag_names);
    }

    // Test Name with special character
    public function test_normalize_name()
    {
        $stub = Stub::create(['name'=>123]);
        $stub->tag('标签名');
        $this->assertEquals('标签名', $stub->tags()->first()->name);
        $stub->untag();

        $stub->tag('标签 名');
        $this->assertEquals('标签-名', $stub->tags()->first()->name);
        $stub->untag();

        $stub->tag('标签$名');
        $this->assertEquals('标签-名', $stub->tags()->first()->name);
        $stub->untag();

        $stub->tag('标签，名');
        $this->assertEquals('标签-名', $stub->tags()->first()->name);
        $stub->untag();

        $stub->tag('标签-名');
        $this->assertEquals('标签-名', $stub->tags()->first()->name);
        $stub->untag();

        $stub->tag('标签?名');
        $this->assertEquals('标签-名', $stub->tags()->first()->name);
        $stub->untag();

        $stub->tag('标签!名');
        $this->assertEquals('标签-名', $stub->tags()->first()->name);
        $stub->untag();
    }

    // Test Slug phoneticize conflict
    public function test_smart_slug()
    {
        $stub = Stub::create(['name'=>123]);

        $stub->tag('标签名');
        $this->assertEquals('biao-qian-ming', $stub->tags()->first()->slug);
        $stub->untag();

        $stub->tag('表签名');
        $this->assertNotEquals('biao-qian-ming', $stub->tags()->first()->slug);
    }

    // Test scopeWithAllTags scopeWithAnyTag
    public function test_scope_with_tags()
    {
        $stub1 = Stub::create(['name'=>'stub1']);
        $stub2 = Stub::create(['name'=>'stub2']);

        $stub1->tag('tag1', 'tag2');
        $stub2->tag('tag2', 'tag3');

        $result = Stub::withAllTags('tag1', 'tag2');

        $this->assertEquals(1, $result->count());
        $this->assertEquals('stub1', $result->first()->name);

        $result2 = Stub::withAnyTag('tag100', 'tag2');
        $this->assertEquals(2, $result2->count());
        $this->assertEquals(['stub1', 'stub2'], $result2->lists('name')->all());
    }
}

class Stub extends Eloquent
{
    use Taggable;

    protected $connection = 'testbench';

    public $table = 'books';
}
