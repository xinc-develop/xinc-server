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

use Xinc\Core\Test\BaseTest;

class XincdTest extends BaseTest
{
    public function getXincd()
    {
        return dirname(__DIR__) . '/Executes/xincd';
    }

    public function testFile()
    {
        $this->assertTrue(is_executable($this->getXincd()));
    }

    public function testVersion()
    {
        exec($this->getXincd() . " -V",$out,$retval);
        $this->assertEquals(0,$retval);
        $this->assertEquals('Xinc version 2.5.4',$out[0]);
    }

    public function testHelp()
    {
        exec($this->getXincd() . " -h",$out,$retval);
        $this->assertEquals(0,$retval);
        $expect = [
                   'Options:',
                   '  -V, --version           print the version of Xinc',
                   '  -h, --help              print this help message',
                   '  -w, --working-dir <arg> the working directory',
                   '  -o, --once              run once and exit',
                   '  -v, --verbose [<arg>]   the level of information to log',
                   '  -p, --project-dir <arg> directory with project configurations',
                   '  -s, --status-dir <arg>  internal (writable) status directory',
                   '  -l, --log-file <arg>    the main log file',
                   '  -i, --pid-file <arg>    place to store the process id',
                   '  -c, --config-file <arg> the config file to use',
                   '  -d, --config-dir <arg>  the directory with main configuration(s)'
                   ];
        foreach($expect as $line) {
            $this->assertTrue(in_array($line,$out));
        }
    }
}