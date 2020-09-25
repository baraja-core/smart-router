<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Baraja\Localization\LocalizationStatus;
use Nette\Http\UrlScript;

final class MatchRequest
{
	private const PART_URL_PARAMETER = 'partUrlParameter';

	private const PART_PATH = 'partPath';

	private const PART_DOMAIN = 'partDomain';

	/** @var Rewriter */
	private $rewriter;

	/** @var LocalizationStatus */
	private $status;

	/** @var string */
	private $domain;

	/** @var string */
	private $path;

	/** @var mixed[] */
	private $parameters;

	/** @var string|null */
	private $environment;

	/** @var string[]|null[] */
	private $locale = [
		self::PART_URL_PARAMETER => null,
		self::PART_PATH => null,
		self::PART_DOMAIN => null,
	];

	/** @var RouterPanel */
	private $panel;


	public function __construct(UrlScript $url, Rewriter $rewriter, LocalizationStatus $status, RouterPanel $panel)
	{
		$this->rewriter = $rewriter;
		$this->status = $status;
		$this->path = mb_strtolower(trim($url->getPathInfo(), '/'), 'UTF-8');
		$this->parameters = $url->getQueryParameters();
		$this->panel = $panel;
		$this->domain = strncmp($domain = $url->getDomain(3), 'www.', 4) === 0
			? (string) preg_replace('/^www\./', '', $domain)
			: $domain;
	}


	/**
	 * @return mixed[]|null
	 */
	public function match(): ?array
	{
		$matched = true;
		if (($route = $this->processRoute()) !== null) {
			[$module, $presenter, $action] = explode(':', $route);
			$this->parameters['presenter'] = $module . ':' . $presenter;
			$this->parameters['action'] = $action;
		} else {
			$matched = false;
		}

		$this->parameters['locale'] = $this->processLocale();
		$this->parameters['environment'] = $this->environment;

		if ($matched === true) {
			$this->panel->setMatchRequest($this);
			$return = [];
			foreach ($this->parameters as $parameterKey => $parameterValue) {
				if ($parameterValue !== null) {
					$return[$parameterKey] = is_scalar($parameterValue) ? (string) $parameterValue : $parameterValue;
				}
			}

			return $return;
		}

		return null;
	}


	/**
	 * Find best match of route [Module:Presenter:action].
	 * In case of rewrite add more parameters to request.
	 *
	 * Try map:
	 *    1. Homepage and empty URL
	 *    2. Admin section
	 *    3. Rewrite route and add path-typed locale
	 */
	private function processRoute(): ?string
	{
		if ($this->path === '') { // 1.
			$return = 'Front:Homepage:default';
		} elseif (($adminRewriter = $this->processAdminRoute($this->path)) !== null) { // 2.
			if (isset($adminRewriter['locale'])) {
				$this->locale[self::PART_PATH] = $adminRewriter['locale'];
			}

			$return = $adminRewriter['route'];
		} elseif (($rewriter = $this->rewriter->rewriteByPath($this->path)) !== null) { // 3.
			if (isset($rewriter['locale'])) {
				$this->locale[self::PART_PATH] = $rewriter['locale'];
			}

			$route = $rewriter['presenter'] . ':' . $rewriter['action'];
			unset($rewriter['presenter'], $rewriter['action'], $rewriter['locale']);
			foreach ($rewriter as $rewriteKey => $rewriteValue) {
				$this->parameters[$rewriteKey] = $rewriteValue;
			}

			$return = $route;
		} else {
			$return = null;
		}

		return $return;
	}


	private function processLocale(): ?string
	{
		// 1. Find by ?locale=en
		if (isset($this->parameters['locale'])) {
			$this->locale[self::PART_URL_PARAMETER] = $this->parameters['locale'];
		}

		// 2. Find by domain and set context environment
		$this->environment = $this->status->getDomainToEnvironment()[$this->domain] ?? null;
		$this->locale[self::PART_DOMAIN] = $this->status->getDomainToLocale()[$this->domain] ?? $this->status->getDefaultLocale();

		if ($this->environment === null && ($this->domain === 'localhost' || $this->domain === '127.0.0.1')) {
			$this->environment = 'localhost';
		}

		// 3. Match by best priority
		foreach ([self::PART_URL_PARAMETER, self::PART_PATH, self::PART_DOMAIN] as $priorityKey) {
			if (isset($this->locale[$priorityKey]) === true) {
				return $this->locale[$priorityKey];
			}
		}

		return null;
	}


	/**
	 * @return string[]|null
	 */
	private function processAdminRoute(string $path): ?array
	{
		if (preg_match('/^admin(?:\/+(?<locale>cs|en))?\/*(?<path>.*?)\/*$/', $path, $parser)) {
			$locale = $parser['locale'] ?: null;
			[$presenter, $action, $more] = explode('/', $parser['path'] . '///');

			if ($more !== '') { // format: xxx/yyy/zzz
				return null;
			}
			if ($action !== '') { // format: xxx/yyy
				return [
					'route' => 'Admin:' . Helper::formatPresenterNameByUri($presenter) . ':' . Helper::formatActionNameByUri($action),
					'locale' => $locale,
				];
			}
			if ($presenter !== '') { // format: xxx
				return [
					'route' => 'Admin:' . Helper::formatPresenterNameByUri($presenter) . ':default',
					'locale' => $locale,
				];
			}

			return [
				'route' => 'Admin:Homepage:default',
				'locale' => $locale,
			];
		}

		return null;
	}
}
