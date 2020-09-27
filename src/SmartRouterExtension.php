<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class SmartRouterExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'rewriter' => Expect::string(),
		])->castTo('array');
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$smartRouter = $builder->addDefinition('barajaSmartRouter')
			->setFactory(SmartRouter::class)
			->setAutowired(SmartRouter::class);

		if (isset($this->config['rewriter']) === true) {
			if (\class_exists($this->config['rewriter']) === false) {
				throw new \RuntimeException('Smart router Rewriter class "' . $this->config['rewriter'] . '" does not exist.');
			}
			try {
				if ((new \ReflectionClass($this->config['rewriter']))->implementsInterface(Rewriter::class) === false) {
					throw new \RuntimeException('Smart router Rewriter class "' . $this->config['rewriter'] . '" must implement "' . Rewriter::class . '".');
				}
			} catch (\ReflectionException $e) {
				throw new \RuntimeException('Smart router Rewriter class is broken: ' . $e->getMessage(), $e->getCode(), $e);
			}
			$rewriterService = $builder->getDefinitionByType($this->config['rewriter']);
			if ($rewriterService instanceof ServiceDefinition) {
				$smartRouter->addSetup('?->setRewriter($this->getService(?), false)', [
					'@self', $rewriterService->getName(),
				]);
			} else {
				throw new \RuntimeException('Class "' . $this->config['rewriter'] . '" must be service. Did you registered it to configuration file?');
			}
		}
	}
}
