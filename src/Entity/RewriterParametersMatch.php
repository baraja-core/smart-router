<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


final class RewriterParametersMatch
{
	/** @var string */
	private $slug;

	/** @var string|null */
	private $locale;

	/** @var string[] */
	private $parameters;


	/**
	 * @param string[] $parameters
	 */
	public function __construct(string $slug, ?string $locale, ?array $parameters = null)
	{
		$this->slug = $slug;
		$this->locale = $locale;
		$this->parameters = $parameters ?? [];
	}


	public function getSlug(): string
	{
		return $this->slug;
	}


	public function getLocale(): ?string
	{
		return $this->locale;
	}


	/**
	 * @return string[]
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}
}
