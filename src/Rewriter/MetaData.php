<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


final class MetaData
{

	/** @var string|int|null */
	private $id;

	/** @var string|null */
	private $metaTitle;

	/** @var string|null */
	private $metaDescription;

	/** @var string|null */
	private $ogTitle;

	/** @var string|null */
	private $ogDescription;

	/** @var bool */
	private $noIndex = false;

	/** @var bool */
	private $noFollow = false;

	/** @var int */
	private $priority = 1;

	/** @var string|null */
	private $seoScore;


	/**
	 * @return string|int|null
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * @param string|int|null $id
	 * @return self
	 */
	public function setId($id): self
	{
		$this->id = $id;

		return $this;
	}


	public function getMetaTitle(): ?string
	{
		return $this->metaTitle;
	}


	public function setMetaTitle(?string $metaTitle): self
	{
		$this->metaTitle = $metaTitle ?: null;

		return $this;
	}


	public function getMetaDescription(): ?string
	{
		return $this->metaDescription;
	}


	public function setMetaDescription(?string $metaDescription): self
	{
		$this->metaDescription = $metaDescription ?: null;

		return $this;
	}


	public function getOgTitle(): ?string
	{
		return $this->ogTitle;
	}


	public function setOgTitle(?string $ogTitle): self
	{
		$this->ogTitle = $ogTitle ?: null;

		return $this;
	}


	public function getOgDescription(): ?string
	{
		return $this->ogDescription;
	}


	public function setOgDescription(?string $ogDescription): self
	{
		$this->ogDescription = $ogDescription ?: null;

		return $this;
	}


	public function isNoIndex(): bool
	{
		return $this->noIndex;
	}


	public function setNoIndex(bool $noIndex): self
	{
		$this->noIndex = $noIndex;

		return $this;
	}


	public function isNoFollow(): bool
	{
		return $this->noFollow;
	}


	public function setNoFollow(bool $noFollow): self
	{
		$this->noFollow = $noFollow;

		return $this;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	public function setPriority(int $priority): self
	{
		if ($priority < 0) {
			$priority = 1;
		}
		if ($priority > 1024) {
			$priority = 1024;
		}

		$this->priority = $priority;

		return $this;
	}


	public function getSeoScore(): ?string
	{
		return $this->seoScore;
	}


	public function setSeoScore(?string $seoScore): self
	{
		$this->seoScore = $seoScore ?: null;

		return $this;
	}
}
