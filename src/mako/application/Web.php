<?php

/**
 * @copyright  Frederic G. Østby
 * @license    http://www.makoframework.com/license
 */

namespace mako\application;

use mako\application\Application;
use mako\http\routing\Dispatcher;
use mako\http\routing\Router;

/**
 * Web application.
 *
 * @author  Frederic G. Østby
 */

class Web extends Application
{
	/**
	 * {@inheritdoc}
	 */

	public function run()
	{
		ob_start();

		// Dispatch the request

		$request = $this->container->get('request');

		// Override the application language?

		if(($language = $request->language()) !== null)
		{
			$this->setLanguage($language);

			if($this->container->has('i18n'))
			{
				$this->container->get('i18n')->setLanguage($this->language);
			}
		}

		// Load filters and routes

		list($filters, $routes) = $this->loadRouting();

		// Route the request

		list($route, $parameters) = (new Router($routes))->route($request);
		
		// Dispatch the request and send the response

		(new Dispatcher($request, $this->container->get('response'), $filters, $route, $parameters, $this->container))->dispatch()->send();
	}
}