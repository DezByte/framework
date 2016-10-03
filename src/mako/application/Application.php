<?php

/**
 * @copyright  Frederic G. Østby
 * @license    http://www.makoframework.com/license
 */

namespace mako\application;

use LogicException;
use RuntimeException;

use mako\application\Package;
use mako\autoloading\AliasLoader;
use mako\config\Config;
use mako\config\loaders\Loader;
use mako\http\routing\Middleware;
use mako\http\routing\Routes;
use mako\file\FileSystem;
use mako\syringe\Container;

/**
 * Application.
 *
 * @author  Frederic G. Østby
 */
abstract class Application
{
	/**
	 * Singleton instance of self.
	 *
	 * @var \mako\application\Application
	 */
	protected static $instance;

	/**
	 * IoC container instance.
	 *
	 * @var \mako\syringe\Container;
	 */
	protected $container;

	/**
	 * Config instance.
	 *
	 * @var \mako\config\Config
	 */
	protected $config;

	/**
	 * Application charset.
	 *
	 * @var string
	 */
	protected $charset;

	/**
	 * Application language.
	 *
	 * @var string
	 */
	protected $language;

	/**
	 * Application path.
	 *
	 * @var string
	 */
	protected $applicationPath;

	/**
	 * Booted packages.
	 *
	 * @var array
	 */
	protected $packages = [];

	/**
	 * Constructor.
	 *
	 * @access  public
	 * @param   string  $applicationPath  Application path
	 */
	public function __construct(string $applicationPath)
	{
		$this->applicationPath = $applicationPath;

		$this->boot();
	}

	/**
	 * Starts the application and returns a singleton instance of the application.
	 *
	 * @access  public
	 * @param   string                         $applicationPath  Application path
	 * @return  \mako\application\Application
	 */
	public static function start(string $applicationPath)
	{
		if(!empty(static::$instance))
		{
			throw new LogicException(vsprintf("%s(): The application has already been started.", [__METHOD__]));
		}

		return static::$instance = new static($applicationPath);
	}

	/**
	 * Returns a singleton instance of the application.
	 *
	 * @access  public
	 * @return  \mako\application\Application
	 */
	public static function instance()
	{
		if(empty(static::$instance))
		{
			throw new LogicException(vsprintf("%s(): The application has not been started yet.", [__METHOD__]));
		}

		return static::$instance;
	}

	/**
	 * Returns the IoC container instance.
	 *
	 * @access  public
	 * @return  \mako\syringe\Container
	 */
	public function getContainer(): Container
	{
		return $this->container;
	}

	/**
	 * Returns the config instance.
	 *
	 * @access  public
	 * @return  \mako\config\Config
	 */
	public function getConfig(): Config
	{
		return $this->config;
	}

	/**
	 * Returns the application charset.
	 *
	 * @access  public
	 * @return  string
	 */
	public function getCharset(): string
	{
		return $this->charset;
	}

	/**
	 * Returns the application language.
	 *
	 * @access  public
	 * @return  string
	 */
	public function getLanguage(): string
	{
		return $this->language;
	}

	/**
	 * Sets the application language settings.
	 *
	 * @access  public
	 * @param   array  $language  Application language settings
	 */
	public function setLanguage(array $language)
	{
		$this->language = $language['strings'];

		foreach($language['locale'] as $category => $locale)
		{
			setlocale($category, $locale);
		}
	}

	/**
	 * Gets the application path.
	 *
	 * @access  public
	 * @return  string
	 */
	public function getPath(): string
	{
		return $this->applicationPath;
	}

	/**
	 * Returns all the application packages.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getPackages(): array
	{
		return $this->packages;
	}

	/**
	 * Returns a package by its name.
	 *
	 * @access  public
	 * @param   string                     $package  Package name
	 * @return  \mako\application\Package
	 */
	public function getPackage(string $package): Package
	{
		if(!isset($this->packages[$package]))
		{
			throw new RuntimeException(vsprintf("%s(): Unknown package [ %s ].", [__METHOD__, $package]));
		}

		return $this->packages[$package];
	}

	/**
	 * Returns the application namespace.
	 *
	 * @access  public
	 * @param   bool    $prefix  Prefix the namespace with a slash?
	 * @return  string
	 */
	public function getNamespace(bool $prefix = false)
	{
		$namespace = basename(rtrim($this->applicationPath, '\\'));

		if($prefix)
		{
			$namespace = '\\' . $namespace;
		}

		return $namespace;
	}

	/**
	 * Is the application running in the CLI?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function isCommandLine(): bool
	{
		return PHP_SAPI === 'cli';
	}

	/**
	 * Returns the Mako environment. NULL is returned if no environment is specified.
	 *
	 * @return  string|null
	 */
	public function getEnvironment()
	{
		return getenv('MAKO_ENV') ?: null;
	}

	/**
	 * Configure.
	 *
	 * @access  protected
	 */
	protected function configure()
	{
		$config = $this->config->get('application');

		// Set internal charset

		$this->charset = $config['charset'];

		mb_language('uni');

		mb_regex_encoding($this->charset);

		mb_internal_encoding($this->charset);

		// Set default timezone

		date_default_timezone_set($config['timezone']);

		// Set locale information

		$this->setLanguage($config['default_language']);
	}

	/**
	 * Registers services in the IoC container.
	 *
	 * @access  protected
	 * @param   string     $type  Service type
	 */
	protected function serviceRegistrar(string $type)
	{
		foreach($this->config->get('application.services.' . $type) as $service)
		{
			(new $service($this->container))->register();
		}
	}

	/**
	 * Registers command line services.
	 *
	 * @access  protected
	 */
	protected function registerCLIServices()
	{
		$this->serviceRegistrar('cli');
	}

	/**
	 * Registers web services.
	 *
	 * @access  protected
	 */
	protected function registerWebServices()
	{
		$this->serviceRegistrar('web');
	}

	/**
	 * Register services in the IoC container.
	 *
	 * @access  protected
	 */
	protected function registerServices()
	{
		// Register core services

		$this->serviceRegistrar('core');

		// Register environment specific services

		if($this->isCommandLine())
		{
			$this->registerCLIServices();
		}
		else
		{
			$this->registerWebServices();
		}
	}

	/**
	 * Registers class aliases.
	 *
	 * @access  protected
	 */
	protected function registerClassAliases()
	{
		$aliases = $this->config->get('application.class_aliases');

		if(!empty($aliases))
		{
			$aliasLoader = new AliasLoader($aliases);

			spl_autoload_register([$aliasLoader, 'load']);
		}
	}

	/**
	 * Loads the application bootstrap file.
	 *
	 * @access  protected
	 */
	protected function bootstrap()
	{
		$bootstrap = function($app, $container)
		{
			include $this->applicationPath . '/bootstrap.php';
		};

		$bootstrap($this, $this->container);
	}

	/**
	 * Boots packages.
	 *
	 * @access  protected
	 * @param   string     $type  Package type
	 */
	protected function packageBooter(string $type)
	{
		foreach($this->config->get('application.packages.' . $type) as $package)
		{
			$package = new $package($this->container);

			$package->boot();

			$this->packages[$package->getName()] = $package;
		}
	}

	/**
	 * Boots command line packages.
	 *
	 * @access  protected
	 */
	protected function bootCliPackages()
	{
		$this->packageBooter('cli');
	}

	/**
	 * Boots web packages.
	 *
	 * @access  protected
	 */
	protected function bootWebPackages()
	{
		$this->packageBooter('web');
	}

	/**
	 * Boot packages.
	 *
	 * @access  protected
	 */
	protected function bootPackages()
	{
		$this->packageBooter('core');

		// Register environment specific services

		if($this->isCommandLine())
		{
			$this->bootCliPackages();
		}
		else
		{
			$this->bootWebPackages();
		}
	}

	/**
	 * Builds a configuration instance.
	 *
	 * @return \mako\config\Config
	 */
	protected function configFactory(): Config
	{
		return new Config(new Loader($this->container->get('fileSystem'), $this->applicationPath . '/config'), $this->getEnvironment());
	}

	/**
	 * Sets up the framework core.
	 *
	 * @access  protected
	 */
	protected function initialize()
	{
		// Create IoC container instance and register it in itself so that it can be injected

		$this->container = new Container();

		$this->container->registerInstance([Container::class, 'container'], $this->container);

		// Register self so that the application instance can be injected

		$this->container->registerInstance([Application::class, 'app'], $this);

		// Register file system instance

		$this->container->registerInstance([FileSystem::class, 'fileSystem'], $fileSystem = new FileSystem());

		// Register config instance

		$this->config = $this->configFactory();

		$this->container->registerInstance([Config::class, 'config'], $this->config);
	}

	/**
	 * Boots the application.
	 *
	 * @access  protected
	 */
	protected function boot()
	{
		// Set up the framework core

		$this->initialize();

		// Configure

		$this->configure();

		// Register services in the IoC injection container

		$this->registerServices();

		// Register class aliases

		$this->registerClassAliases();

		// Load the application bootstrap file

		$this->bootstrap();

		// Boot packages

		$this->bootPackages();
	}

	/**
	 * Loads middleware.
	 *
	 * @access  protected
	 * @return  \mako\http\routing\Middleware
	 */
	protected function loadMiddleware(): Middleware
	{
		$loader = function($app, $container, $middleware)
		{
			include $this->applicationPath . '/routing/middleware.php';

			return $middleware;
		};

		return $loader($this, $this->container, new Middleware);
	}

	/**
	 * Loads routes.
	 *
	 * @access  protected
	 * @return  \mako\http\routing\Routes
	 */
	protected function loadRoutes(): Routes
	{
		$loader = function($app, $container, $routes)
		{
			include $this->applicationPath . '/routing/routes.php';

			return $routes;
		};

		return $loader($this, $this->container, $this->container->get('routes'));
	}

	/**
	 * Loads middleware and routes.
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function loadRouting(): array
	{
		return [$this->loadMiddleware(), $this->loadRoutes()];
	}

	/**
	 * Runs the application.
	 *
	 * @access  public
	 */

	abstract public function run();
}