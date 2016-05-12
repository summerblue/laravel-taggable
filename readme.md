Laravel Taggable
============

## Introduction

> 中文文档和讨论请见这里：

Tag support for Laravel Eloquent models using Taggable Trait.

This project extends [rtconner/laravel-tagging](https://github.com/rtconner/laravel-tagging) , add the following feature specially for Chinese
User:

* Tag name unique, and using `tag_id` for query data.
* Add [etrepat/baum](https://github.com/etrepat/baum) support complicated tag tree;
* Chinese Pinyin slug support using [overtrue/pinyin](https://github.com/overtrue/pinyin);
* Full test coverage。

> Notice: This projcet only tested and intended only support 5.1 LTS.

:heart:  This project is maintained by [@Summer](https://github.com/summerblue), member of [The EST Group](http://estgroupe.com).

## Baum Nested Sets

Integarated [etrepat/baum](https://github.com/etrepat/baum), what is Nested Sets?

> A nested set is a smart way to implement an ordered tree that allows for fast, non-recursive queries. For example, you can fetch all descendants of a node in a single query, no matter how deep the tree.

```php

$root = Tag::create(['name' => 'Root']);

// Create Child Tag
$child1 = $root->children()->create(['name' => 'Child1']);

$child = Tag::create(['name' => 'Child2']);
$child->makeChildOf($root);

// Batch create Tag Tree
$tagTree = [
	'name' => 'RootTag',
	'children' => [
		['name' => 'L1Child1',
			'children' => [
				['name' => 'L2Child1'],
				['name' => 'L2Child1'],
				['name' => 'L2Child1'],
			]
		],
		['name' => 'L1Child2'],
		['name' => 'L1Child3'],
	]
];

Tag::buildTree($tagTree);
```

Please refer the Official Project for more advance usage - [etrepat/baum](https://github.com/etrepat/baum)

## Tag name rules

* Any special charactor and empty space will be replace by `-`;
* Automatically smart slug generation, generate Chinese Pinyin slug, fore example: `标签` -> `biao-qian`, will add random value when there is a conflict.

> Tag name normalizer：`$normalize_string = EstGroupe\Taggable\Util::tagName($name)`。

```
Tag::create(['标签名']);
// name: 标签名
// slug: biao-qian-ming

Tag::create(['表签名']);
// name: 表签名
// slug: biao-qian-ming-3243 （3243 is random string）

Tag::create(['标签 名']);
// name: 标签-名
// slug: biao-qian-ming

Tag::create(['标签!名']);
// name: 标签-名
// slug: biao-qian-ming
```

## Installation：

### Composer install package

```shell
composer require estgroupe/laravel-taggable "~5.1.*"
```

### Config and Migration

Change `providers` array at `config/app.php`:

```php
'providers' => array(
	\EstGroupe\Taggable\Providers\TaggingServiceProvider::class,
);
```
```bash
php artisan vendor:publish --provider="EstGroupe\Taggable\Providers\TaggingServiceProvider"
php artisan migrate
```

> Please take a close look at file: `config/tagging.php`

### Create your own Tag.php

It's optional but suggested to use your own `Tag` Model:

```php
<?php namespace App\Models;

use EstGroupe\Taggable\Model\Tag as TaggableTag;

class Tag extends TaggableTag
{
	// Model code go here
}
```

Change `config/tagging.php` file：

```
	'tag_model'=>'\App\Models\Tag',
```

### Adding Taggable Trait

```php
<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use EstGroupe\Taggable\Taggable;

class Article extends \Illuminate\Database\Eloquent\Model {
	use Taggable;
}
```

### `is_tagged` Label

`Taggable` can keep track of the model `Tagged Status`:

```php
// `no`
$article->is_tagged

// `yes`
$article->tag('Tag1');
$article->is_tagged;

// `no`
$article->unTag();
$article->is_tagged

// This is fast
$taggedArticles = Article::where('is_tagged', 'yes')->get()
```

First modify `config/tagging.php`：

```php
'is_tagged_label_enable' => true,
```

Add `is_tagged` filed to you model Migration file:

```php
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateArticlesTable extends Migration {

	public function up()
	{
		Schema::create('weibo_statuses', function(Blueprint $table) {
            $table->increments('id');
            ...
			// Add this line
			$table->enum('is_tagged', array('yes', 'no'))->default('no');
			...
            $table->timestamps();
        });
	}
}
```

## `Suggesting` tags

Suggesting is a small little feature you could use if you wanted to have "suggested" tags that stand out.

There is not much to it. You simply set the 'suggest' field in the database to true

```php
$tag = EstGroupe\Taggable\Model\Tag::where('slug', '=', 'blog')->first();
$tag->suggest = true;
$tag->save();
```

And then you can fetch a list of suggested tags when you need it.

```php
$suggestedTags = EstGroupe\Taggable\Model\Tag::suggested()->get();
```

### Rewrite Util class?
How do I override the Util class?
============

You'll need to create your own service provider. It should look something like this.

```php
namespace My\Project\Providers;

use EstGroupe\Taggable\Providers\TaggingServiceProvider as ServiceProvider;
use EstGroupe\Taggable\Contracts\TaggingUtility;

class TaggingServiceProvider extends ServiceProvider {

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton(TaggingUtility::class, function () {
			return new MyNewUtilClass;
		});
	}

}
```

> Notice: Where `MyNewUtilClass` is a class you have written. Your new Util class obviously needs to implement the `EstGroupe\Taggable\Contracts\TaggingUtility` interface.

## Usage

```php
$article = Article::with('tags')->first(); // eager load

// Get all the article tagged tags
foreach($article->tags as $tag) {
	echo $tag->name . ' with url slug of ' . $tag->slug;
}

// Tag some tag/tags
$article->tag('Gardening'); // attach the tag
$article->tag('Gardening, Floral'); // attach the tag
$article->tag(['Gardening', 'Floral']); // attach the tag
$article->tag('Gardening', 'Floral'); // attach the tag

// Using tag_id batch tag
$article->tagWithTagIds([1,2,3]);

// Remove tags
$article->untag('Cooking');  // remove Cooking tag
$article->untag(); 				// remove all tags

// Retag
$article->retag(['Fruit', 'Fish']); // delete current tags and save new tags
$article->retag('Fruit', 'Fish');
$article->retag('Fruit, Fish');

$tagged = $article->tagged; // return Collection of rows tagged to article
$tags = $article->tags; // return Collection the actual tags (is slower than using tagged)

// Get array of related tag names
$article->tagNames();

// Fetch articles with any tag listed
Article::withAnyTag('Gardening, Cooking')->get();
Article::withAnyTag(['Gardening','Cooking'])->get(); // different syntax, same result as above
Article::withAnyTag('Gardening','Cooking')->get(); // different syntax, same result as above

// Only fetch articles with all the tags
Article::withAllTags('Gardening, Cooking')->get();
Article::withAllTags(['Gardening', 'Cooking'])->get();
Article::withAllTags('Gardening', 'Cooking')->get();

// Return all tags used more than twice
EstGroupe\Taggable\Model\Tag::where('count', '>', 2)->get();

// Return collection of all existing tags on any articles
Article::existingTags();
```

`EstGroupe\Taggable\Model\Tag` has the following functions:

```php

// By tag slug
Tag::byTagSlug('biao-qian-ming')->first();

// By tag name
Tag::byTagName('tag1')->first();

// Using names
Tag::byTagNames(['tag1', 'tag12', 'tag13'])->first();

// Using Tag ids array
Tag::byTagIds([1,2，3])->first();

// Using name to get tag ids array
$ids = Tag::idsByNames(['标签名', '标签2', '标签3'])->all();
// [1,2,3]

```

## Tagging events

`Taggable` trait offer you two events:

```php
EstGroupe\Taggable\Events\TagAdded;

EstGroupe\Taggable\Events\TagRemoved;
```

You can listen to it as you want：

```php
\Event::listen(EstGroupe\Taggable\Events\TagAdded::class, function($article){
	\Log::debug($article->title . ' was tagged');
});
```

## Unit testing

Common usage are tested at `tests/CommonUsageTest.php` file.

Running test:

```
composer install
vendor/bin/phpunit --verbose
```

## Thanks

 - Special Thanks to: Robert Conner - http://smartersoftware.net
 - [overtrue/pinyin](https://github.com/overtrue/pinyin)
 - [etrepat/baum](https://github.com/etrepat/baum)
 - Made with love by The EST Group - http://estgroupe.com/


