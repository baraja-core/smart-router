<?php

declare(strict_types=1);

namespace Baraja\SmartRouter;


use Nette\DI\CompilerExtension;

final class SmartRouterExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('barajaSmartRouter')
			->setFactory(SmartRouter::class)
			->setAutowired(SmartRouter::class);
	}
}
