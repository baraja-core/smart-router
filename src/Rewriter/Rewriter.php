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
	 * @param string $path
	 * @return string[]|null
	 */
	public function rewriteByPath(string $path): ?array;

	/**
	 * @param string[] $parameters
	 * @return RewriterParametersMatch|null
	 */
	public function rewriteByParameters(array $parameters): ?RewriterParametersMatch;
}
