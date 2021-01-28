<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Baraja\Localization\LocalizationStatus;
use Nette\Http\Url;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

final class ConstructUrlRequest
{
	private Rewriter $rewriter;

	private LocalizationStatus $status;

	/** @var string[] */
	private array $params;

	private Url $url;

	private string $route;

	private ?string $environment;

	/**
	 * For local environment only. For example in case of local address is:
	 * http://localhost/baraja/project/www/product
	 * Value will be: "/baraja/project/www"
	 */
	private ?string $scriptPath;

	private ?string $locale;

	private bool $needLocaleParameter = false;

	private bool $lazy = false;

	private RouterPanel $panel;


	/**
	 * @param string[] $params
	 */
	public function __construct(array $params, Rewriter $rewriter, LocalizationStatus $status, ?string $scriptPath, RouterPanel $panel)
	{
		if (array_key_exists('environment', $params) === true) {
			$this->environment = $params['environment'] ?? null;
			unset($params['environment']);
		} else {
			$this->environment = 'production';
		}
		if (array_key_exists('locale', $params) === true) {
			$this->locale = $params['locale'] ?? null;
			unset($params['locale']);
		} else {
			$this->locale = $status->getDefaultLocale();
		}
		if (array_key_exists('lazy', $params) === true) {
			$this->lazy = (bool) ($params['lazy'] ?? false);
			unset($params['lazy']);
		}

		$this->rewriter = $rewriter;
		$this->status = $status;
		$this->route = $params['presenter'] . ':' . $params['action'];
		$this->scriptPath = $scriptPath === null ? null : trim($scriptPath, '/');
		$this->panel = $panel;
		unset($params['presenter'], $params['action']);

		$this->params = $params;
		$this->url = new Url($this->environment === 'localhost' ? 'http://localhost/' . $this->scriptPath : \Baraja\Url\Url::get()->getBaseUrl());
	}


	/**
	 * Produce SEO-friendly URL by DG article.
	 *
	 * @param mixed[] $parameters
	 * @see https://phpfashion.com/jsou-tyto-url-stejne
	 */
	public function createUrl(?string $scheme, string $domain, ?string $path, ?array $parameters = []): ?string
	{
		if (($scheme = $scheme ?? 'http') && $scheme !== 'http' && $scheme !== 'https') {
			return null;
		}
		if ($path !== null && ($path[0] ?? '') === '#') {
			$this->panel->addErrorMessage(ltrim($path, '#'));

			return '#invalid-url';
		}
		if (($path = trim($path ?? '', '/')) !== '') {
			if (strpos($path, '?') !== false) {
				$path = explode('?', $path)[0] ?? '';
			}

			$path = Strings::webalize($path, '-./');
		}
		if (($parameters = $parameters ?? []) !== []) {
			ksort($parameters, SORT_STRING);
			$params = str_replace(['%5B', '%5D'], ['[', ']'], http_build_query($parameters));
		} else {
			$params = '';
		}

		return mb_strtolower($scheme . '://' . $domain . ($path !== '' ? '/' . $path : ''), 'UTF-8')
			. (strncmp($domain, 'localhost', 9) === 0 && $path === '' ? '/' : '')
			. ($params !== '' ? '?' . $params : '');
	}


	public function construct(): ?string
	{
		if (($module = explode(':', $this->route)[0]) === '' || ($module !== 'Front' && $module !== 'Admin')) {
			return null;
		}
		if (($domain = $this->processDomain()) === 'localhost') {
			$domain .= '/' . $this->scriptPath;
			$scheme = 'http';
		} else {
			$scheme = $this->status->getDomainToScheme()[$domain] ?? 'http';
		}
		if (\in_array($this->locale, $this->status->getAvailableLocales(), true) === false) {
			return null;
		}

		return ($path = $this->processPath()) !== null
			? $this->createUrl($scheme, $domain, $path, $this->getParams())
			: null;
	}


	/**
	 * Rewrite environment and locale to specific domain by configuration.
	 *
	 * @return string
	 */
	private function processDomain(): string
	{
		$conf = $this->status->getDomainByEnvironment();

		// 1. Is environment empty or exist environment in configuration?
		if ($this->environment === null || isset($conf[$this->environment][$this->locale]) === false) {
			$domain = strncmp($domainByUrl = $this->url->getDomain(3), 'www.', 4) === 0
				? (string) preg_replace('/^www\./', '', $domainByUrl)
				: $domainByUrl;
		} elseif ($this->locale !== null && isset($conf[$this->environment][$this->locale]) === true) {
			// 2. If yes, exist specific domain for given locale?
			$domain = $conf[$this->environment][$this->locale];
		} else {
			// 3. If specific locale does not exist, use default domain for environment.
			$this->needLocaleParameter = true;
			$domain = $conf[$this->environment][$this->status->getDefaultLocale()] ?? $this->url->getDomain(3);
		}

		return (($this->status->getDomainToUseWww()[$domain] ?? false) && $this->environment !== 'localhost' ? 'www.' : '') . $domain;
	}


	private function processPath(): ?string
	{
		[$module, $presenter, $action] = explode(':', $this->route);

		// 1. In case of lazy link
		if ($this->lazy === true) {
			$formatPresenter = static function (string $haystack): string {
				return (string) preg_replace_callback('/([a-z])([A-Z])/', function (array $match): string {
					return mb_strtolower($match[1] . '-' . $match[2], 'UTF-8');
				}, $haystack);
			};

			$finalPresenter = $formatPresenter(Helper::firstLower($presenter));
			$finalAction = $formatPresenter(Helper::firstLower($action));

			return ($finalPresenter !== 'Homepage' && $finalAction !== 'default' ? $finalPresenter : '')
				. ($finalAction !== 'default' ? '/' . $finalAction : '');
		}

		// 2. Find best path by rewriter
		$params = $this->params;
		$params['presenter'] = $presenter;
		$params['action'] = $action;
		$params['locale'] = $this->locale;

		if ($module === 'Admin') {
			$return = $this->createAdminRegularPath($presenter, $action);
		} elseif ($params['presenter'] === 'Homepage' && $params['action'] === 'default') {
			$return = '/';
			if ($this->environment === 'localhost' && $this->locale !== $this->status->getDefaultLocale()) {
				$this->needLocaleParameter = true;
			}
		} elseif (($rewrite = $this->rewriter->rewriteByParameters($params)) === null) {
			$return = null;
		} else {
			if ($rewrite->getLocale() !== $this->locale) {
				$exceptionMessage = 'Route "' . $rewrite->getSlug() . '" is not available in given locale "' . $this->locale . '", '
					. 'but only in "' . $rewrite->getLocale() . '".';

				if (class_exists('\Tracy\Debugger') === true) {
					Debugger::log(new \RuntimeException($exceptionMessage), ILogger::EXCEPTION);
				}

				return '#' . $exceptionMessage;
			}

			$this->needLocaleParameter = false;
			foreach ($rewrite->getParameters() as $usedParameterKey => $usedParameterValue) {
				if (isset($this->params[$usedParameterKey]) && $this->params[$usedParameterKey] === $usedParameterValue) {
					unset($this->params[$usedParameterKey]);
				}
			}

			$return = $rewrite->getSlug();
		}

		return $return;
	}


	/**
	 * @return string[]
	 */
	private function getParams(): array
	{
		if ($this->locale !== null) {
			if ($this->needLocaleParameter === true) {
				$this->params['locale'] = $this->locale;
			} else {
				unset($this->params['locale']);
			}
		}
		if ($this->environment === 'localhost' && $this->locale === $this->status->getDefaultLocale()) {
			unset($this->params['locale']);
		}

		return $this->params;
	}


	private function createAdminRegularPath(string $presenter, string $action): string
	{
		if ($presenter === 'Homepage') {
			return $action === 'default' ? 'admin' : 'admin/homepage/' . Helper::formatPresenterNameToUri($action);
		}

		return 'admin'
			. '/' . Helper::formatPresenterNameToUri(Helper::firstLower($presenter))
			. ($action === 'default' ? '' : '/' . Helper::formatPresenterNameToUri($action));
	}
}
