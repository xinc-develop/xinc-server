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

class CmdlineTest extends BaseTest
{  
    public function testDefaults()
    {
		$cmd = new \Xinc\Server\Cmd();
		$xinc = $cmd->setupXinc();
		
		$this->assertNull($xinc->options['project-file']); 
        $this->assertFalse($xinc->options['once']); 
        $this->assertEquals('.', $xinc->options['working-dir']);
        $this->assertEquals('./projects/',$xinc->options['project-dir']);
        $this->assertEquals('./status/',$xinc->options['status-dir']);
        $this->assertEquals('./xinc.log',$xinc->options['log-file']);
        $this->assertEquals('./.xinc.pid',$xinc->options['pid-file']);
        $this->assertEquals(2,$xinc->options['verbose']);

//		print_r($xinc);
    }

}
