<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - The command's name will change to `app:shop-id:change`
 */
#[AsCommand(
    name: 'app:url-change:resolve',
    description: 'Change the shop ID by choosing a resolution strategy',
    aliases: ['app:shop-id:change'],
)]
#[Package('framework')]
class ChangeShopIdCommand extends Command
{
    public function __construct(private readonly Resolver $shopIdChangeResolver)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('strategy', InputArgument::OPTIONAL, 'The strategy that should be applied');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $availableStrategies = $this->shopIdChangeResolver->getAvailableStrategies();
        $strategy = $input->getArgument('strategy');

        if ($strategy === null || !\array_key_exists($strategy, $availableStrategies)) {
            if ($strategy !== null) {
                $io->note(\sprintf('Strategy with name: "%s" not found.', $strategy));
            }

            $strategy = $io->choice(
                'Choose what strategy should be applied, to resolve the shop ID change?',
                $availableStrategies
            );
        }

        $this->shopIdChangeResolver->resolve($strategy, Context::createCLIContext());

        $io->success('Strategy "' . $strategy . '" was applied successfully');

        return self::SUCCESS;
    }
}
