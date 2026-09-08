<?php

declare(strict_types=1);

/**
 * Shows the discovered listener surface: every event, its listeners, their
 * order, and whether they run sync or through the async backend — the
 * routing:list convention for events. Reads discovery, so what it prints is
 * what boot would load.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Cli;

use PHPdot\Console\Command;
use PHPdot\Event\Contract\ListenerDiscoveryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'event:list', description: 'Show the discovered event listeners.')]
final class ListCommand extends Command
{
    /**
     * Nothing here touches a pool, so a Swoole scheduler would only add latency.
     */
    protected bool $coroutine = false;

    public function __construct(
        private readonly ListenerDiscoveryInterface $discovery,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('event', null, InputOption::VALUE_REQUIRED, 'Only listeners for an event whose class name contains this');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = $input->getOption('event');
        $filter = is_string($filter) && $filter !== '' ? $filter : null;

        $entries = $this->discovery->discover();

        if ($filter !== null) {
            $entries = array_values(array_filter(
                $entries,
                static fn($entry): bool => str_contains($entry->eventClass, $filter),
            ));
        }

        if ($entries === []) {
            $this->warning($output, $filter === null ? 'No listeners are declared.' : "No listener matches '{$filter}'.");

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'Event' => $this->shortName($entry->eventClass),
                'Listener' => $this->shortName($entry->handlerClass),
                'Order' => (string) $entry->order,
                'Mode' => $entry->async ? 'async' : 'sync',
                'Priority' => $entry->async ? (string) $entry->priority : "\u{2014}",
                'Enabled' => $entry->enabled ? 'yes' : 'no',
            ];
        }

        $this->table($output, $rows);
        $this->info($output, sprintf('%d listener%s declared.', count($rows), count($rows) === 1 ? '' : 's'));

        return Command::SUCCESS;
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
