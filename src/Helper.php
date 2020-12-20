<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


final class Helper
{

	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	public static function firstUpper(string $s): string
	{
		return mb_strtoupper(self::substring($s, 0, 1), 'UTF-8') . self::substring($s, 1);
	}


	public static function firstLower(string $s): string
	{
		return mb_strtolower(self::substring($s, 0, 1), 'UTF-8') . self::substring($s, 1);
	}


	public static function formatPresenterNameByUri(string $name): string
	{
		return self::firstUpper(self::formatPresenter(mb_strtolower($name, 'UTF-8')));
	}


	public static function formatActionNameByUri(string $name): string
	{
		return trim(self::formatPresenter($name), '/');
	}


	public static function formatPresenterNameToUri(string $name): string
	{
		return trim((string) preg_replace_callback('/([A-Z])/', static function (array $match): string {
			return '-' . mb_strtolower($match[1], 'UTF-8');
		}, $name), '-');
	}


	public static function getBaseUrl(): ?string
	{
		static $return;

		if ($return === null) {
			if (($currentUrl = self::getCurrentUrl()) !== null) {
				if (preg_match('/^(https?:\/\/.+)\/www\//', $currentUrl, $localUrlParser)) {
					$return = $localUrlParser[0];
				} elseif (preg_match('/^(https?:\/\/[^\/]+)/', $currentUrl, $publicUrlParser)) {
					$return = $publicUrlParser[1];
				}
			}
			if ($return !== null) {
				$return = rtrim($return, '/');
			}
		}

		return $return;
	}


	/**
	 * Return current absolute URL.
	 * Return null, if current URL does not exist (for example in CLI mode).
	 */
	public static function getCurrentUrl(): ?string
	{
		if (isset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST']) === false) {
			return null;
		}

		return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
			. '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}


	private static function substring(string $s, int $start, int $length = null): string
	{
		if (function_exists('mb_substr')) {
			return mb_substr($s, $start, $length, 'UTF-8'); // MB is much faster
		}

		$lengthProcess = static function (string $s): int {
			return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_decode($s));
		};

		if ($length === null) {
			$length = $lengthProcess($s);
		} elseif ($start < 0 && $length < 0) {
			$start += $lengthProcess($s); // unifies iconv_substr behavior with mb_substr
		}

		return (string) iconv_substr($s, $start, $length, 'UTF-8');
	}


	/**
	 * Convert URI case to Presenter name case. The first character will not be enlarged automatically.
	 *
	 * For example: "article-manager" => "articleManager".
	 */
	private static function formatPresenter(string $haystack): string
	{
		return (string) preg_replace_callback('/-([a-z])/', static function (array $match): string {
			return mb_strtoupper($match[1], 'UTF-8');
		}, $haystack);
	}
}
