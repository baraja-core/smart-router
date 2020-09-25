<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Nette\Http\UrlScript;

interface AfterMatchEvent
{

	/**
	 * Process logic for current matched route.
	 *
	 * @param UrlScript $url
	 * @param mixed[] $match
	 */
	public function matched(UrlScript $url, array $match): void;
}
