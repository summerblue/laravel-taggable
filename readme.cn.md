Laravel Taggable
============

## 功能说明

使用最简便的方式，为你的数据模型提供强大「打标签」功能。

本项目修改于 [rtconner/laravel-tagging](https://github.com/rtconner/laravel-tagging) 项目，增加了一下功能：

* 标签名唯一；
* 增加 [etrepat/baum](https://github.com/etrepat/baum) 依赖，让标签支持无限级别标签嵌套；
* 中文 slug 拼音自动生成支持，感谢超哥的 [overtrue/pinyin](https://github.com/overtrue/pinyin)；
* 提供完整的测试用例，保证代码质量。

> 注意： 本项目只支持 5.1 LTS

:heart: 此项目由 [The EST Group](http://estgroupe.com) 团队的 [@Summer](https://github.com/summerblue) 维护。

## 无限级别标签嵌套

集成 [etrepat/baum](https://github.com/etrepat/baum) 让标签具备从属关系。

```php

$root = Tag::create(['name' => 'Root']);

// 创建子标签
$child1 = $root->children()->create(['name' => 'Child1']);

$child = Tag::create(['name' => 'Child2']);
$child->makeChildOf($root);

// 批量构建树
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

更多关联操作请查看：[etrepat/baum](https://github.com/etrepat/baum) 。

## 标签名称规则说明

* 标签名里的特殊符号和空格会被 `-` 替代；
* 智能标签 slug 生成，会生成 name 对应的中文拼音 slug ，如：`标签` -> `biao-qian`，拼音一样的时候会被加上随机值；

> 标签名清理使用：`$normalize_string = EstGroupe\Taggable\Util::tagName($name)`。

```
Tag::create(['标签名']);
// name: 标签名
// slug: biao-qian-ming

Tag::create(['表签名']);
// name: 表签名
// slug: biao-qian-ming-3243 （后面 3243 为随机，解决拼音冲突）

Tag::create(['标签 名']);
// name: 标签-名
// slug: biao-qian-ming

Tag::create(['标签!名']);
// name: 标签-名
// slug: biao-qian-ming
```

## 安装说明：

### 安装

```shell
composer require estgroupe/laravel-taggable "~5.1.*"
```

### 安装和执行迁移

在 `config/app.php` 的 `providers` 数组中加入：
```php
'providers' => array(
	\EstGroupe\Taggable\Providers\TaggingServiceProvider::class,
);
```
```bash
php artisan vendor:publish --provider="EstGroupe\Taggable\Providers\TaggingServiceProvider"
php artisan migrate
```

> 请仔细阅读 `config/tagging.php` 文件。

### 创建 Tag.php

不是必须的，不过建议你创建自己项目专属的 Tag.php 文件。

```php
<?php namespace App\Models;

use EstGroupe\Taggable\Model\Tag as TaggableTag;

class Tag extends TaggableTag
{
	// Model code go here
}
```

修改 `config/tagging.php` 文件中：

```
	'tag_model'=>'\App\Models\Tag',
```

### 加入 Taggable Trait

```php
<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use EstGroupe\Taggable\Taggable;

class Article extends \Illuminate\Database\Eloquent\Model {
	use Taggable;
}
```

### 「标签状态」标示

`Taggable` 能跟踪模型是否打过标签的状态：

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

首先你需要修改 `config/tagging.php` 文件中：

```php
'is_tagged_label_enable' => true,
```

然后在你的模型的数据库创建脚本里加上：

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

### 「推荐标签」标示

方便你实现「推荐标签」功能，只需要把 `suggest` 字段标示为 `true`：

```php
$tag = EstGroupe\Taggable\Model\Tag::where('slug', '=', 'blog')->first();
$tag->suggest = true;
$tag->save();
```

即可以用以下方法读取：

```php
$suggestedTags = EstGroupe\Taggable\Model\Tag::suggested()->get();
```

### 重写 Util 类?

大部分的通用操作都发生在 Util 类，你想获取更多的定制权力，请创建自己的 Util 类，并注册服务提供者：

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

然后在

> 注意 `MyNewUtilClass` 必须实现 `EstGroupe\Taggable\Contracts\TaggingUtility` 接口。

## 使用范例

```php
$article = Article::with('tags')->first(); // eager load

// 获取所有标签
foreach($article->tags as $tag) {
	echo $tag->name . ' with url slug of ' . $tag->slug;
}

// 打标签
$article->tag('Gardening'); // attach the tag
$article->tag('Gardening, Floral'); // attach the tag
$article->tag(['Gardening', 'Floral']); // attach the tag
$article->tag('Gardening', 'Floral'); // attach the tag

// 批量通过 tag ids 打标签
$article->tagWithTagIds([1,2,3]);

// 去掉标签
$article->untag('Cooking'); // remove Cooking tag
$article->untag(); // remove all tags

// 重打标签
$article->retag(['Fruit', 'Fish']); // delete current tags and save new tags
$article->retag('Fruit', 'Fish');
$article->retag('Fruit, Fish');

$tagged = $article->tagged; // return Collection of rows tagged to article
$tags = $article->tags; // return Collection the actual tags (is slower than using tagged)

// 获取绑定的标签名称数组
$article->tagNames(); // get array of related tag names

// 获取打了「任意」标签的 Article 对象
Article::withAnyTag('Gardening, Cooking')->get(); // fetch articles with any tag listed
Article::withAnyTag(['Gardening','Cooking'])->get(); // different syntax, same result as above
Article::withAnyTag('Gardening','Cooking')->get(); // different syntax, same result as above

// 获取打了「全包含」标签的 Article 对象
Article::withAllTags('Gardening, Cooking')->get(); // only fetch articles with all the tags
Article::withAllTags(['Gardening', 'Cooking'])->get();
Article::withAllTags('Gardening', 'Cooking')->get();

EstGroupe\Taggable\Model\Tag::where('count', '>', 2)->get(); // return all tags used more than twice

Article::existingTags(); // return collection of all existing tags on any articles
```

如果你 [创建了 Tag.php](#)，即可使用以下标签读取功能：

```php

// 通过 slug 获取标签
Tag::byTagSlug('biao-qian-ming')->first();

// 通过名字获取标签
Tag::byTagName('标签名')->first();

// 通过名字数组获取标签数组
Tag::byTagNames(['标签名', '标签2', '标签3'])->first();

// 通过 Tag ids 数组获取标签数组
Tag::byTagIds([1,2，3])->first();

// 通过名字数组获取 ID 数组
$ids = Tag::idsByNames(['标签名', '标签2', '标签3'])->all();
// [1,2,3]

```

## 标签事件

`Taggable` trait 提供以下两个事件：

```php
EstGroupe\Taggable\Events\TagAdded;

EstGroupe\Taggable\Events\TagRemoved;
```

监听标签事件：

```php
\Event::listen(EstGroupe\Taggable\Events\TagAdded::class, function($article){
	\Log::debug($article->title . ' was tagged');
});
```

## 单元测试

基本用例测试请见： `tests/CommonUsageTest.php`。

运行测试：

```
composer install
vendor/bin/phpunit --verbose
```

## Thanks

 - Special Thanks to: Robert Conner - http://smartersoftware.net
 - [overtrue/pinyin](https://github.com/overtrue/pinyin)
 - [etrepat/baum](https://github.com/etrepat/baum)
 - Made with love by The EST Group - http://estgroupe.com/


