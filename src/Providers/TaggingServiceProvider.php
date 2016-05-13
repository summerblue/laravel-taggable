<?php

namespace EstGroupe\Taggable\Providers;

use Illuminate\Support\ServiceProvider;
use EstGroupe\Taggable\Contracts\TaggingUtility;
use EstGroupe\Taggable\Util;

/**
 * Copyright (C) 2014 Robert Conner
 */
class TaggingServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application events.
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__.'/../../config/taggable.php' => config_path('taggable.php')
		], 'config');

		$this->publishes([
			__DIR__.'/../../migrations/' => database_path('migrations')
		], 'migrations');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton(TaggingUtility::class, function () {
			return new Util;
		});
	}

	/**
	 * (non-PHPdoc)
	 * @see \Illuminate\Support\ServiceProvider::provides()
	 */
	public function provides()
	{
		return [TaggingUtility::class];
	}
}
