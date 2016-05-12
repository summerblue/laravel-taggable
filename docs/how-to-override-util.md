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

Where `MyNewUtilClass` is a class you have written. Your new Util class obviously needs to implement the `EstGroupe\Taggable\Contracts\TaggingUtility` interface.