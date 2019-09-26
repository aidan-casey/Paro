<?php

namespace AidanCasey\Paro\Providers;

use Illuminate\Support\ServiceProvider;

class ParoServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		// First get the location of our config file
		$path = realpath(__DIR__ . '/../../config/config.php')
		
		// Now setup our publishing and merging
		$this->publishes([$path => config_path('paro.php')], 'config');
		$this->mergeConfigFrom($path, 'paro');
	}
}
