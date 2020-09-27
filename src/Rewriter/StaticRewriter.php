<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


final class StaticRewriter implements Rewriter
{
	/** @var string[][] */
	private static $config = [
		'' => [
			'presenter' => 'Front:Homepage',
			'action' => 'default',
			'locale' => 'en',
		],
	];


	/**
	 * @param string[][]|null $config
	 */
	public function __construct(array $config = null)
	{
		if ($config !== null) {
			$this->setConfig($config);
		}
	}


	/**
	 * @return string[]|null
	 */
	public function rewriteByPath(string $path): ?array
	{
		return self::$config[$path] ?? null;
	}


	/**
	 * @param string[] $parameters
	 * @return RewriterParametersMatch|null
	 */
	public function rewriteByParameters(array $parameters): ?RewriterParametersMatch
	{
		$candidateScore = 0;
		$presenter = 'Front:' . ($parameters['presenter'] ?? 'Homepage');
		$action = $parameters['action'] ?? 'default';
		$best = [
			'slug' => null,
			'locale' => null,
			'params' => [],
		];

		foreach (self::$config as $slug => $params) {
			if ($params['presenter'] === $presenter && $params['action'] === $action) {
				$returnParameters = [];
				$score = 1;

				if (($params['locale'] ?? null) === $parameters['locale'] ?? null) {
					$score += 2;
				}
				if (isset($parameters['id'], $params['id']) && $parameters['id'] === $params['id']) {
					$score += 10;
					$returnParameters['id'] = $params['id'];
				}
				if ($candidateScore < $score) {
					$candidateScore = $score;
					$best = [
						'slug' => $slug,
						'locale' => $params['locale'] ?? $parameters['locale'] ?? null,
						'params' => $returnParameters,
					];
				}
			}
		}

		return $candidateScore > 0
			? new RewriterParametersMatch($best['slug'], $best['locale'], $best['params'])
			: null;
	}


	/**
	 * @param string[][] $config
	 */
	public function setConfig(array $config): void
	{
		self::$config = $config;
	}


	public function clearCache(): void
	{
		// ignore
	}


	public function getMetaData(string $path, string $locale): MetaData
	{
		return new MetaData;
	}
}
