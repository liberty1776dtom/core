<?php
/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\Unit\BackgroundJob;

use OCA\DAV\BackgroundJob\CleanProperties;
use OCP\IDBConnection;
use OCP\ILogger;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Class CleanPropertiesTest
 *
 * @group DB
 * @package OCA\DAV\Tests\Unit\BackgroundJob
 */
class CleanPropertiesTest extends TestCase {
	use UserTrait;
	/** @var IDBConnection | \PHPUnit_Framework_MockObject_MockObject */
	private $connection;
	/** @var ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var CleanProperties */
	private $cleanProperties;

	public function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->logger = \OC::$server->getLogger();
		$this->cleanProperties = new CleanProperties($this->connection, $this->logger);
		$this->createUser('user1');
	}

	public function testDeleteOrphanEntries() {
		$userFolder = \OC::$server->getUserFolder('user1');
		$userFolder->newFile('a.txt');
		$userFolder->newFile('b.txt');
		$userFolder->newFile('c.txt');

		$fileIds[] = $userFolder->get('a.txt')->getId();
		$fileIds[] = $userFolder->get('b.txt')->getId();
		$fileIds[] = $userFolder->get('c.txt')->getId();

		foreach ($fileIds as $fileId) {
			$qb = $this->connection->getQueryBuilder();
			$qb->insert('properties')
				->values([
					'propertyname' => $qb->createNamedParameter('foo'),
					'propertyvalue' => $qb->createNamedParameter('bar'),
					'fileid' => $qb->createNamedParameter($fileId)
				]);
			$qb->execute();
		}

		$userFolder->get('a.txt')->delete();
		$userFolder->get('c.txt')->delete();

		$this->invokePrivate($this->cleanProperties, 'run', ['']);
		$qb = $this->connection->getQueryBuilder();
		$qb->select('fileid')
			->from('properties');
		$result = $qb->execute()->fetchAll();

		/**
		 * Only one result should be there.
		 * And the fileid should match with the file which is not deleted.
		 */
		$this->assertCount(1, $result);
		$this->assertEquals($fileIds[1], $result[0]['fileid']);
	}
}
