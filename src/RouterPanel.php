<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Nette\Http\IRequest;
use Tracy\Dumper;
use Tracy\IBarPanel;

final class RouterPanel implements IBarPanel
{
	private IRequest $httpRequest;

	private ?MatchRequest $matchRequest = null;

	/** @var string[] */
	private array $errorMessages = [];


	public function __construct(IRequest $httpRequest)
	{
		$this->httpRequest = $httpRequest;
	}


	public function addErrorMessage(string $message): void
	{
		$this->errorMessages[] = $message;
	}


	public function setMatchRequest(MatchRequest $matchRequest): void
	{
		$this->matchRequest = $matchRequest;
	}


	public function getTab(): string
	{
		return '<span title="Smart router">'
			. '<svg viewBox="0 0 2048 2048">'
			. '<path fill="#d86b01" d="'
			. 'm1559.7 1024c0 17-6 32-19 45l-670 694.48c-13 13-28 19-45 19s-32-6-45-19-19-28-19-45v-306.48h-438.52c-17 '
			. '0-32-6-45-19s-19-28-19-45v-642c0-17 6-32 19-45s28-19 45-19h438.52v-309.41c0-17 6-32 19-45s28-19 45-19 '
			. '32 6 45 19l670 691.41c13 13 19 28 19 45z"/>'
			. '<path d="m1914.7 1505c0 79-31 147-87 204-56 56-124 85-203 85h-320c-9 0-16-3-22-9-14-23-21-90 3-110 5-4 '
			. '12-6 21-6h320c44 0 82-16 113-47s47-69 47-113v-962c0-44-16-82-47-113s-69-47-113-47h-312c-11 '
			. '0-21-3-30-9-15-25-21-90 3-110 5-4 12-6 21-6h320c79 0 147 28 204 85 56 56 82 124 82 204-9 272 9 649 0 '
			. '954z" fill-opacity=".5" fill="#d86b01"/>'
			. '</svg><span class="tracy-label">'
			. 'Smart router'
			. '</span></span>';
	}


	public function getPanel(): string
	{
		$return = '<h1>Smart router</h1>'
			. '<div class="tracy-inner">'
			. '<div class="tracy-inner-container">'
			. '<table style="width:100%;margin-top:8px">';

		if (($_GET ?? []) !== []) {
			$return .= '<tr><th>GET</th></tr>'
				. '<tr><td>' . $this->renderHttpData($_GET ?? []) . '</td></tr>';
		}
		if ($this->httpRequest->getMethod() === 'POST') {
			$return .= '<tr><th>POST</th></tr>'
				. '<tr><td>' . $this->renderHttpData($_POST ?? []) . '</td></tr>';
		}
		if (($_FILES ?? []) !== []) {
			$return .= '<tr><th>FILE</th></tr>'
				. '<tr><td>' . $this->renderHttpData($_FILES ?? []) . '</td></tr>';
		}

		$return .= '</table>'
			. '<p><code>'
			. htmlspecialchars($this->httpRequest->getMethod(), ENT_IGNORE, 'UTF-8')
			. '</code>&nbsp;<code>'
			. htmlspecialchars($this->httpRequest->getUrl()->getBaseUrl(), ENT_IGNORE, 'UTF-8')
			. '<wbr><span style="background:#eee; white-space:nowrap">'
			. str_replace(
				['&amp;', '?'],
				['<wbr>&amp;', '<wbr>?'],
				htmlspecialchars($this->httpRequest->getUrl()->getRelativeUrl(), ENT_IGNORE, 'UTF-8')
			)
			. '</span></code></p>';

		if ($this->errorMessages !== []) {
			$return .= '<table style="width:100%">';
			$return .= '<tr><th>' . \count($this->errorMessages) . ' errors:</th></tr>';
			foreach ($this->errorMessages as $errorMessage) {
				$return .= '<tr><td>'
					. preg_replace('/"([^"]+)"/', '"<span style="color:#4263d7">$1</span>"', $errorMessage)
					. '</td></tr>';
			}
			$return .= '</table>';
		}
		if ($this->matchRequest !== null) {
			$return .= Dumper::toHtml($this->matchRequest);
		}

		$return .= '</div></div>';

		return $return;
	}


	/**
	 * @param mixed[] $data
	 */
	private function renderHttpData(array $data): string
	{
		$return = '';
		foreach ($data as $key => $value) {
			$return .= '<tr><th>' . htmlspecialchars((string) $key) . '</th><td>' . Dumper::toHtml($value) . '</td></tr>';
		}

		return '<table style="width:100%">' . $return . '</table>';
	}
}
