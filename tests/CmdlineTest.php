<?php
/*
 * @author Sebastian Knapp
 * @version 2.5
 *
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
use Xinc\Server\Cmd;

/**
 * Test Class for the Xinc Server Commandline
 */
class CmdlineTest extends BaseTest
{
    public function testDefaults()
    {
        $cmd = new Cmd();
        $xinc = $cmd->setupXinc();
        $conf = $xinc->getConfig();
        $this->assertFalse($conf->has('projectfile'));
        $this->assertFalse($conf->has('configfile'));
        $this->assertFalse($conf->has('project-file'));
        $this->assertFalse($conf->has('config-file'));

        $this->assertFalse($conf->get('once'));
        $this->assertEquals('./', $conf->get('workingdir'));

        $this->assertEquals('./etc/xinc/', $conf->get('configdir'));
        $this->assertEquals('./etc/xinc/projects/', $conf->get('projectdir'));
        $this->assertEquals('./status/', $conf->get('statusdir'));
        $this->assertEquals('./xinc.log', $conf->get('logfile'));
        $this->assertEquals('./status/.xinc.pid', $conf->get('pidfile'));
        $this->assertEquals(2, $conf->get('verbose'));
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
