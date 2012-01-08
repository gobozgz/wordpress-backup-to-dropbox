<?php
/**
 * A test for the File_List class
 *
 * @copyright Copyright (C) 2011 Michael De Wildt. All rights reserved.
 * @author Michael De Wildt (http://www.mikeyd.com.au/)
 * @license This program is free software; you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation; either version 2 of the License, or
 *          (at your option) any later version.
 *
 *          This program is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *
 *          You should have received a copy of the GNU General Public License
 *          along with this program; if not, write to the Free Software
 *          Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA.
 */
define( 'BACKUP_TO_DROPBOX_VERSION', 'UnitTest' );
define('ABSPATH', dirname(__FILE__) . '/');

require_once '../Classes/class-file-list.php';
require_once 'Mocks/mock-wp-functions.php';
require_once 'Mocks/class-mock-dropbox-facade.php';
require_once 'Mocks/class-mock-wpdb.php';

/**
 * Test class for File_List.
 * Generated by PHPUnit on 2011-05-11 at 20:38:59.
 */
class File_List_Test extends PHPUnit_Framework_TestCase {
	/**
	 * @var File_List
	 */
	protected $object;

	/**
	 * Sets up this test with a File_List a Mock_WpDB. The initial options will
	 * be set as well.
	 * @return void
	 */
	protected function setUp() {
		$this->object = new File_List( new Mock_WpDb() );
	}

	protected function tearDown() {
		$dir = dirname( __FILE__ );

		if ( file_exists( "$dir/Out/Level1/file.txt" ) )
			unlink( "$dir/Out/Level1/file.txt" );

		if ( file_exists( "$dir/Out/Level1/Level2/file.txt" ) )
			unlink( "$dir/Out/Level1/Level2/file.txt" );

		if ( file_exists( "$dir/Out/Level1/file2.txt" ) )
			unlink( "$dir/Out/Level1/file2.txt" );

		if ( file_exists( "$dir/Out/Level1/Level2/" ) )
			rmdir( "$dir/Out/Level1/Level2/" );

		if ( file_exists( "$dir/Out/Level1/" ) )
			rmdir( "$dir/Out/Level1/" );
	}

	/* Sets the file list to pick up any new files that may exist on the system
	 * @param bool $init
	 * @return void
	 */
	public static function get_file_list() {
		$new_file_list = array();
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ABSPATH ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $files as $file ) {
			$file = realpath( $file );
			if ( File_List::in_ignore_list( $file ) ) {
				continue;
			}
			if ( is_dir( $file) ) {
				$file .= '/';
			}
			$new_file_list[] = array( $file, File_List::INCLUDED ) ;
		}
		asort( $new_file_list );
		return array_values( $new_file_list );
	}

	/**
	 * Creates a file
	 * @param $name
	 * @return void
	 */
	private function create_file( $name ) {
		$fh = fopen( $name, 'a' );
		fwrite( $fh, 'WRITE' );
		fclose( $fh );
	}

	/**
	 * @return void
	 */
	public function testSetFileListFile() {
		$dir = dirname( __FILE__ );

		$list = array(
			array( "$dir/Out/", File_List::PARTIAL ),
			array( "$dir/class-wp-backup-test.php", File_List::EXCLUDED )
		);

		$json = json_encode( $list );
		$this->object->set_file_list( $json );
		$this->object->save();

		list( $partial, $excluded ) = get_option( 'backup-to-dropbox-file-list' );
		$this->assertEquals( 1, count( $partial ) );
		$this->assertEquals( 1, count( $excluded ) );

		$this->assertEquals( "$dir/Out/", $partial[0] );
		$this->assertEquals( "$dir/class-wp-backup-test.php", $excluded[0] );
	}

	/**
	 * The include state of a directory could be
	 *
	 * 	STATE_OFF = 0;
	 *  STATE_ON = 1;
	 *  STATE_PARTIAL = 2;
	 *
	 *  A file will just be off or on so in the case of a dir we need to see if all or none of its children are included.
	 * @return void
	 */
	public function testGet_exclude_state() {
		$dir = dirname( __FILE__ );

		$list = $this->get_file_list();
		$this->assertEquals( 8, count( $list ) );

		$list[7][1] = File_List::EXCLUDED; //class-wp-backup-test.php
		$list[4][1] = File_List::PARTIAL; //out/

		$json = json_encode( $list );
		$this->object->set_file_list( $json );
		$this->object->save();

		list ( $partial, $excluded ) = get_option( 'backup-to-dropbox-file-list' );
		$this->assertEquals( 1, count( $partial ) );
		$this->assertEquals( 1, count( $excluded ) );

		$this->assertEquals( File_List::INCLUDED, $this->object->get_file_state( "$dir/Mocks/mock-wp-functions.php" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/class-wp-backup-test.php" ) );
		$this->assertEquals( File_List::PARTIAL, $this->object->get_file_state( "$dir/Out/" ) );

		mkdir( "$dir/Out/Level1/" );
		$this->create_file( "$dir/Out/Level1/file.txt" );
		mkdir( "$dir/Out/Level1/Level2/" );
		$this->create_file( "$dir/Out/Level1/Level2/file.txt" );

		$list = $this->get_file_list();
		$this->assertEquals( 12, count( $list ) );

		//If just a directory is set to INCLUDE or EXCLUDE then all of its sub files need to be updated accordingly
		$list[4][1] = File_List::EXCLUDED; //out/
		$json = json_encode( $list );
		$this->object->set_file_list( $json );
		$this->object->save();

		list ( $partial, $excluded ) = get_option( 'backup-to-dropbox-file-list' );
		$this->assertEquals( 0, count( $partial ) );
		$this->assertEquals( 1, count( $excluded ) );

		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/expected.sql" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/file.txt" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/Level2/" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/Level2/file.txt" ) );

		$this->create_file( "$dir/Out/Level1/file2.txt" );

		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/file2.txt" ) );
		$this->object->save();

		$list = $this->get_file_list();
		$this->assertEquals( 13, count( $list ) );

		//If just a directory is set to INCLUDE or EXCLUDE then all of its sub files need to be updated accordingly
		$list[4][1] = File_List::PARTIAL; //0ut/
		$list[5][1] = File_List::PARTIAL; //0ut/Level1/
		$list[6][1] = File_List::EXCLUDED; //0ut/Level1/Level2

		//var_dump($list);

		$json = json_encode( $list );
		$this->object->set_file_list( $json );
		$this->object->save();

		list ( $partial, $excluded ) = get_option( 'backup-to-dropbox-file-list' );
		$this->assertEquals( 2, count( $partial ) );
		$this->assertEquals( 1, count( $excluded ) );

		$this->assertEquals( File_List::PARTIAL, $this->object->get_file_state( "$dir/Out/" ) );
		$this->assertEquals( File_List::INCLUDED, $this->object->get_file_state( "$dir/Out/expected.sql" ) );
		$this->assertEquals( File_List::PARTIAL, $this->object->get_file_state( "$dir/Out/Level1/" ) );
		$this->assertEquals( File_List::INCLUDED, $this->object->get_file_state( "$dir/Out/Level1/file.txt" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/Level2/" ) );
		$this->assertEquals( File_List::EXCLUDED, $this->object->get_file_state( "$dir/Out/Level1/Level2/file.txt" ) );
	}

	/**
	 * @return void
	 */
	public function testGet_exclude_state2() {
		$dir = dirname( __FILE__ );
		$list = $this->get_file_list();

		$json = json_encode( $list );
		$this->object->set_file_list( $json );

		mkdir( "$dir/Out/Level1/" );
		$this->create_file( "$dir/Out/Level1/file2.txt" );

		$this->assertEquals( File_List::INCLUDED, $this->object->get_file_state( "$dir/Out/Level1/file2.txt" ) );

		$list = $this->get_file_list();
		$list[5][1] = File_List::PARTIAL; //Out/Level1/
		$json = json_encode( $list );
		$this->object->set_file_list( $json );

		$this->create_file( "$dir/Out/Level1/file.txt" );

		$this->assertEquals( File_List::INCLUDED, $this->object->get_file_state( "$dir/Out/Level1/file.txt" ) );

	}
}

?>
