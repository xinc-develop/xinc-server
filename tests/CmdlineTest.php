<?php
/**
 * Test Class for the Xinc Build Properties
 *
 * @package Xinc.Project
 * @author Arno Schneider
 * @version 2.0
 * @copyright 2007 Arno Schneider, Barcelona
 * @license  http://www.gnu.org/copyleft/lgpl.html GNU/LGPL, see license.php
 *    This file is part of Xinc.
 *    Xinc is free software; you can redistribute it and/or modify
 *    it under the terms of the GNU Lesser General Public License as published
 *    by the Free Software Foundation; either version 2.1 of the License, or
 *    (at your option) any later version.
 *
 *    Xinc is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU Lesser General Public License for more details.
 *
 *    You should have received a copy of the GNU Lesser General Public License
 *    along with Xinc, write to the Free Software
 *    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


use Xinc\Core\Models\Project;
use Xinc\Core\Project\Status as ProjectStatus;

use Xinc\Core\Test\BaseTest;
use \Xinc\Server\Cmd;

class CmdlineTest extends BaseTest
{
    public function testDefaults()
    {
    	$cmd = new Cmd();
	    $xinc = $cmd->setupXinc();
	    $options = $xinc->getConfig()->getOptions();
    	$this->assertArrayNotHasKey('projectfile',$options);
        $this->assertArrayNotHasKey('configfile',$options);
	    $this->assertArrayNotHasKey('project-file',$options);
        $this->assertArrayNotHasKey('config-file',$options);
        $this->assertFalse($options['once']);
        $this->assertEquals('./', $options['workingdir']);

        $this->assertEquals('./etc/xinc/',$options['configdir']);
        $this->assertEquals('./etc/xinc/projects/',$options['projectdir']);
        $this->assertEquals('./status/',$options['statusdir']);
        $this->assertEquals('./xinc.log',$options['logfile']);
        $this->assertEquals('./.xinc.pid',$options['pidfile']);
        $this->assertEquals(2,$options['verbose']);
    }

    public function testConfigFile()
    {
	 $args = ['-c','test-config.xml','--working-dir','.'];
	 $xinc = (new Cmd)->setupXinc($args);
    	 $options = $xinc->getConfig()->getOptions();

	    $this->assertEquals('test-config.xml',$options['config-file']);
	    $this->assertEquals('test-config.xml',$options['configfile']);
	}
}
