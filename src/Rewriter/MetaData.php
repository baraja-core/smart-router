<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


final class MetaData
{

	/** @var string|int|null */
	private $id;

	private ?string $metaTitle = null;

	private ?string $metaDescription = null;

	private ?string $ogTitle = null;

	private ?string $ogDescription = null;

	private bool $noIndex = false;

	private bool $noFollow = false;

	private int $priority = 1;

	private ?string $seoScore = null;


	/**
	 * @return string|int|null
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * @param string|int|null $id
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
