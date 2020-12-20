<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


interface Rewriter
{
	/**
	 * If match return array in format (minimal configuration):
	 * [
	 *    'presenter' => 'Front:Homepage',
	 *    'action' => 'default',
	 *    'locale' => 'en',
	 * ] + other parameters
	 *
	 * @return string[]|null
	 */
	public function rewriteByPath(string $path): ?array;

	/**
	 * @param string[]|null[] $parameters
	 */
	public function rewriteByParameters(array $parameters): ?RewriterParametersMatch;

	public function clearCache(): void;

	public function getMetaData(string $path, string $locale): MetaData;
}
