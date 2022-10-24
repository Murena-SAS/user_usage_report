<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserUsageReport\Command;

use OCA\UserUsageReport\Reports\AllUsers;
use OCA\UserUsageReport\Reports\SingleUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class Generate extends Command {

	/** @var SingleUser */
	protected $single;

	/** @var AllUsers */
	protected $all;

	/** @var IUserManager */
	protected $userManager;

	/**
	 * @param SingleUser $single
	 * @param AllUsers $all
	 * @param IUserManager $userManager
	 */
	public function __construct(SingleUser $single, AllUsers $all, IUserManager $userManager) {
		parent::__construct();

		$this->single = $single;
		$this->all = $all;
		$this->userManager = $userManager;
	}

	protected function configure(): void {
		$this
			->setName('usage-report:generate')
			->setDescription(
				'Prints a CSV entry with some usage information of the user:' . "\n"
				. 'userId,date,assignedQuota,usedQuota,numFiles,numShares,numUploads,numDownloads' . "\n"
				. '"admin","2017-09-18T09:00:01+00:00",5368709120,786432000,1024,23,1400,5678'
			)
			->addArgument(
				'user-id',
				InputArgument::OPTIONAL,
				'User to generate the report for, if none is given the report is generated for all users'
			)
			->addOption(
				'field-separator',
				'',
				InputOption::VALUE_REQUIRED,
				'Separator for the fields in the list (Only used when output format is CSV)',
				','
			)
			->addOption(
				'date-format',
				'',
				InputOption::VALUE_REQUIRED,
				'Date format of the entries (see http://php.net/manual/en/function.date.php for more information)',
				'c'
			)
			->addOption(
				'last-login',
				'',
				InputOption::VALUE_NONE,
				'Should the last login date be included in the report'
			)
			->addOption(
				'display-name',
				'',
				InputOption::VALUE_NONE,
				'Should the display name be included in the report'
			)
			->addOption(
				'output',
				'',
				InputOption::VALUE_REQUIRED,
				'Output format (csv or json)',
				'csv'
			)
			->addOption(
				'output-file',
				'O',
				InputOption::VALUE_REQUIRED,
				'Output file for stdout'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$outputFile = $input->getOption('output-file');

		if ($outputFile) {
			$stream = fopen($outputFile, 'w');
			$streamOutput = new StreamOutput($stream, $output->getVerbosity(), null, $output->getFormatter());
		} else {
			$streamOutput = $output;
		}

		$userId = $input->getArgument('user-id');
		if ($userId) {
			if (!$this->userManager->userExists($userId)) {
				$output->writeln('<error>User with ID "' . $userId . '" could not be found.</error>');
				return 1;
			}
		}

		if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
			$separator = $input->getOption('field-separator');

			$header = [];
			$header['user_id'] = '';
			$data = '"user-id"'. $separator;
			if ($input->getOption('display-name')) {
				$header['display_name'] = '';
				$data .= '"display name"'. $separator;
			}
			$header['date'] = '';
			$data .= '"date as \'' . $input->getOption('date-format') . '\'"'. $separator;
			if ($input->getOption('last-login')) {
				$header['login'] = '';
				$data .= '"last login date as \'' . $input->getOption('date-format') . '\'"'. $separator;
			}
			$header['quota'] = '';
			$data .= '"assigned quota (5 GB)"' . $separator;
			$header['used'] = '';
			$data .= '"used quota (500 MB)"' . $separator;
			$header['files'] = 0;
			$data .= 'number of files' . $separator;
			$header['shares'] = 0;
			$data .= 'number of shares' . $separator;
			$header['uploads'] = 0;
			$data .= 'number of uploads' . $separator;
			$header['downloads'] = 0;
			$data .= 'number of downloads';

			if ($input->getOption('output') === 'csv') {
				$streamOutput->writeln($data);
			} else {
				$streamOutput->writeln(json_encode($header));
			}
		}

		if ($input->getArgument('user-id')) {
			$this->single->printReport($input, $streamOutput, $userId);
		} else {
			$this->all->printReport($input, $output, $streamOutput);
		}

		return 0;
	}
}
