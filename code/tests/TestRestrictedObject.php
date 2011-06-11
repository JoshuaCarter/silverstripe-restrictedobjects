<?php

/**
 * Description of TestRestrictedObject
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TestRestrictedObject extends SapphireTest {

	protected $extraDataObjects = array(
		'PrivateObject',
	);
	
	public function setUpOnce() {
		parent::setUpOnce();
		$this->requireDefaultRecordsFrom[] = 'AccessRole';
		
		// needs to be done this way to work around SS bug
//		include_once dirname(dirname(__FILE__)).'/extensions/Restrictable.php';
//		Object::add_extension('PrivateObject', 'Restrictable');
	}
	
	public function setUp() {
		parent::setUp();
		singleton('Restrictable')->getCache()->clean('all');
	}
	
	public function testGrant() {
		$user = $this->logInWithPermission('ADMIN');
		
		$item = new PrivateObject();
		$item->Title = 'Test item';
		$item->write();
		
		$item->grant('Manager', Member::currentUser());
		
		$authorities = $item->getAuthorities();

		$this->assertTrue($authorities != null && $authorities->Count() > 0);
	}
	
	public function testCheckPerm() {
		$this->logInWithPermission('OTHERUSER');
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		$user = $this->logInWithPermission('ADMIN');
		$user = $this->cache_generatedMembers['ADMIN'];
		
		$item = new PrivateObject();
		$item->Title = 'testCan item ';
		$item->write();

		$item->grant('Manager', Member::currentUser());
		$item->grant('Manager', $otherUser);

		$can = $item->checkPerm('View');
		$this->assertTrue($can);

		// triggers the cached lookup
		$can = $item->checkPerm('View');
		$this->assertTrue($can);
		
		// try inherited items
		$otherItem = new PrivateObject();
		$otherItem->Title = 'Private child object';
		$otherItem->ParentID = $item->ID;
		$otherItem->write();
		
		$this->logInWithPermission('OTHERUSER');
		
		$can = $otherItem->checkPerm('View');
		
		$this->assertTrue($can);
		
		$this->assertTrue($otherItem->checkPerm('Write'));
		
		$this->assertTrue($otherItem->checkPerm('Publish'));
		
		$this->assertFalse($otherItem->checkPerm('Configure'));

		$otherItem->deny('Write', Member::currentUser());
		$this->assertFalse($otherItem->checkPerm('Write'));
		
		// now deny in the item we're inheriting from 
		$item->deny('UnPublish', Member::currentUser());
		$this->assertFalse($otherItem->checkPerm('UnPublish'));
		
		// but can still edit at that level
		$this->assertTrue($otherItem->checkPerm('Publish'));
	}
	
	function testOwnership() {
		$this->logInWithPermission('OTHERUSER');
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		$user = $this->logInWithPermission('NONADMIN');
		$user = $this->cache_generatedMembers['NONADMIN'];
		
		$item = new PrivateObject();
		$item->Title = 'testCan item ';
		$item->write();

		$this->assertTrue($item->OwnerID == $user->ID);
		
		$user = $this->logInWithPermission('OTHERUSER');
		
		$item->OwnerID = $user->ID;
		try {
			$item->write();
			$this->assertTrue(false);
		} catch (PHPUnit_Framework_ExpectationFailedException $fe) {
			throw $fe;
		} catch (Exception $e) {
			$this->assertTrue(true);
		}
	}	
}

class PrivateObject extends DataObject implements TestOnly {

	public static $db = array(
		'Title' => 'Varchar',
	);

	public static $has_one = array(
		'Parent'			=> 'PrivateObject',
	);
	
	public static $extensions = array(
		'Restrictable',
	);
}

class PrivateChildObject extends DataObject implements TestOnly {
	public static $has_one = array(
		'Parent'			=> 'PrivateObject',
	);
}