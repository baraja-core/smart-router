<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Baraja\Localization\Domain;
use Baraja\Localization\Localization;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Http\IRequest;
use Nette\Http\UrlScript;
use Nette\Routing\Router;
use Tracy\Debugger;

final class SmartRouter implements Router
{
	private const CACHE_EXPIRATION = '30 minutes';

	private ?Rewriter $rewriter = null;

	private Localization $localization;

	private Cache $cache;

	private RouterPanel $panel;

	/** @var AfterMatchEvent[] */
	private array $afterMatchEvents = [];


	public function __construct(IStorage $storage, Localization $localization, IRequest $request)
	{
		$this->cache = new Cache($storage, 'smart-router');
		$this->localization = $localization;
		Debugger::getBar()->addPanel($this->panel = new RouterPanel($request));
	}


	/**
	 * Maps HTTP request to a Request object.
	 *
	 * @return mixed[]|null
	 */
	public function match(IRequest $httpRequest): ?array
	{
		if (PHP_SAPI === 'cli' || !is_array($_SERVER['argv'] ?? [])) {
			return null;
		}

		$url = $httpRequest->getUrl();
		if ($url->getPathInfo() === 'nette.micro' && $url->getQueryParameter('callback') !== null) {
			throw new \RuntimeException('Critical security warning: This request is possible bug CVE-2020-15227. It must be terminated.');
		}

		$cacheKey = 'm:' . $url->getAbsoluteUrl();
		$this->checkDomainFormat(str_replace('www.', '', $url->getDomain()));

		// 1. Load current request in cache
		if (($cache = $this->cache->load($cacheKey)) !== null) {
			return $cache === '#NO-ROUTE#' ? null : $this->returnRequest($url, $cache);
		}

		// 2. Match current request by inner logic
		if (($match = (new MatchRequest($url, $this->getRewriter(), $this->localization->getStatus(), $this->panel))->match()) !== null) {
			$match['locale'] = $match['locale'] ?? $this->localization->getDefaultLocale();
			if (\in_array($match['locale'], $this->localization->getAvailableLocales(), true) === false) {
				$match['locale'] = $this->localization->getDefaultLocale();
			}
		}

		// 3. Save matched data to effective key-value cache
		$this->cache->save($cacheKey, $match ?? '#NO-ROUTE#',
			(static function (?array $match): array {
				$return = [Cache::EXPIRE => self::CACHE_EXPIRATION];

				if ($match !== null) {
					$return[Cache::TAGS] = ['route/' . $match['presenter'] . ':' . $match['action']];
				}

				return $return;
			})($match)
		);

		return $match === null ? null : $this->returnRequest($url, $match);
	}


	/**
	 * Constructs absolute URL from Request object.
	 *
	 * @param mixed[] $params
	 */
	public function constructUrl(array $params, UrlScript $refUrl): ?string
	{
		if (PHP_SAPI === 'cli' || !is_array($_SERVER['argv'] ?? [])) {
			return null;
		}

		$params['locale'] = $params['locale'] ?? $this->localization->getLocale();
		$params['environment'] = $params['environment'] ?? $this->getEnvironment(str_replace('www.', '', $refUrl->getDomain(4)));
		$cacheKey = 'c:' . json_encode($params);

		// 1. Load current request in cache
		if (($cache = $this->cache->load($cacheKey)) !== null) {
			return $cache === '#NO-ROUTE#' ? null : $cache;
		}

		// 2. Construct given parameters to URL by inner logic
		$construct = (new ConstructUrlRequest($params, $this->getRewriter(), $this->localization->getStatus(), $refUrl->getScriptPath(), $this->panel))->construct();

		// 3. Save matched data to effective key-value cache
		if ($construct !== '#invalid-url') {
			$this->cache->save($cacheKey, $construct ?? '#NO-ROUTE#', [
				Cache::EXPIRE => self::CACHE_EXPIRATION,
				Cache::TAGS => ['route/' . $params['presenter'] . ':' . $params['action']],
			]);
		}

		return $construct;
	}


	public function addAfterMatchEvent(AfterMatchEvent $afterMatchEvent): void
	{
		$this->afterMatchEvents[] = $afterMatchEvent;
	}


	public function clearCache(string $section = 'all'): void
	{
		if (\in_array($section, $possibilities = ['all', 'router', 'rewriter', 'events'], true) === false) {
			throw new \InvalidArgumentException('Section "' . $section . '" is invalid. Did you mean "' . implode('", "', $possibilities) . '"?');
		}
		if ($section === 'router' || $section === 'all') {
			$this->cache->clean([Cache::ALL => true]);
		}
		if ($this->rewriter !== null && ($section === 'rewriter' || $section === 'all')) {
			$this->rewriter->clearCache();
		}
		if ($section === 'events' || $section === 'all') {
			foreach ($this->afterMatchEvents as $event) {
				$event->cleanCache();
			}
		}
	}


	public function getRewriter(): Rewriter
	{
		if ($this->rewriter === null) {
			throw new \RuntimeException('Router Rewriter does not exist. Did you install baraja-core/doctrine-router for example?');
		}

		return $this->rewriter;
	}


	/**
	 * @internal
	 */
	public function setRewriter(Rewriter $rewriter, bool $checkOverwrite = true): void
	{
		if ($checkOverwrite === true && $this->rewriter !== null && $this->rewriter !== $rewriter) {
			throw new \LogicException('Router Rewriter already exist (service "' . \get_class($this->rewriter) . '"). Did you install multiple implementations?');
		}

		$this->rewriter = $rewriter;
	}


	/**
	 * @param mixed[] $match
	 * @return mixed[]
	 */
	private function returnRequest(UrlScript $url, array $match): array
	{
		unset($match['environment']);
		if (($locale = $match['locale'] ?? null) !== null) {
			$this->localization->setLocale((string) $locale);
		}
		foreach ($this->afterMatchEvents as $event) {
			$event->matched($url, $match);
		}

		return $match;
	}


	private function checkDomainFormat(string $domain): void
	{
		if ($domain === 'localhost') {
			return;
		}
		if (preg_match('/^([^\.]+)\.([^\.]+)$/', $domain, $domainParts)) {
			if ($domainParts[2] === 'loc' || $domainParts[2] === 'local') {
				throw new \RuntimeException('Current local domain "' . $domain . '" is deprecated. Did you mean "' . $domainParts[1] . '.l" or "localhost"?');
			}
		} elseif (strpos($domain, '.') === false) {
			throw new \RuntimeException(
				'Current local domain "' . $domain . '" is invalid, because SmartRouter does not support short domain syntax. '
				. 'Did you mean "' . $domain . '.l" or "localhost"?'
			);
		}
	}


	private function getEnvironment(string $domain): string
	{
		if (isset(($envs = $this->localization->getStatus()->getDomainToEnvironment())[$domain]) === false) { // Rewrite given domain to environment.
			$environment = Domain::ENVIRONMENT_PRODUCTION;
			trigger_error('Environment for domain "' . $domain . '" does not exist. Did you register domain to Localization?');
		} else {
			$environment = $envs[$domain];
		}

		return $environment;
	}
}
