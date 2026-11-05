# Symfony Console - Command Line Interface

The garlic-hub uses the symfony console component for cli commands. For those who are not familiar with the symfony console component, here is a brief overview.
You will find more details in the [Symfony Console documentation](https://symfony.com/doc/current/components/console.html).

## Usage

Run the console application with the following command:

```bash
php bin/console.php [command] [options]
php bin/console.php list
```

In ddev: `ddev exec php bin/console.php [command]`.

## Available commands

| Command                                   | Purpose                                                                                     |
|-------------------------------------------|---------------------------------------------------------------------------------------------|
| `db:migrate [version]`                    | Runs the migrations up to the newest or the given version.                                  |
| `db:migrate --rollback [version]` (`-r`)  | Reverts the migrations down to the given version.                                           |
| `schedule:run`                            | Runs the scheduled tasks which are due. Ends at once while the scheduler is off in the settings. |

The Docker image starts `schedule:run` by cron every 5 minutes (`entrypoint.sh`). Without Docker add the cron line
yourself, when you switch the scheduler on.

## Symfony Console Command Structure
Each command is a PHP class in `src/Commands` that extends `Symfony\Component\Console\Command\Command`.
`config/bootstrap.php` registers every class of this directory, the DI container builds it, so the constructor
gets its dependencies. Name and description come from the attribute `#[AsCommand]`.

### Basic Structure of a Command

```php
namespace App\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'my:command',
	description: 'Executes the custom command.'
)]
class MyCommand extends Command
{
	protected function configure(): void
	{
		$this->addOption('option', null, InputOption::VALUE_OPTIONAL, 'An example option for the command');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$option = $input->getOption('option');
		$output->writeln('Executing the command with option: ' . $option);

		return Command::SUCCESS;
	}
}
```

## Help Command (--help)

Symfony Console provides a `--help` option for every command:

```bash
php bin/console.php db:migrate --help
```

It shows the name, the description, the arguments and options and the usage.
