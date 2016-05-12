Suggesting
============

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