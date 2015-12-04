<?php
/**
 * Xinc - Continuous Integration.
 * The Xinc Server Commandline
 *
 * PHP version 5
 *
 * @category  Development
 * @package   Xinc.Server
 * @author    David Ellis  <username@example.org>
 * @author    Gavin Foster <username@example.org>
 * @author    Jamie Talbot <username@example.org>
 * @author    Alexander Opitz <opitz.alexander@gmail.com>
 * @author    Sebastian Knapp <news@young-workers.de>
 * @copyright 2007 David Ellis, One Degree Square
 * @copyright 2015 Xinc Development Team <https://github.com/xinc-develop>
 * @license   http://www.gnu.org/copyleft/lgpl.html GNU/LGPL, see license.php
 *            This file is part of Xinc.
 *            Xinc is free software; you can redistribute it and/or modify
 *            it under the terms of the GNU Lesser General Public License as
 *            published by the Free Software Foundation; either version 2.1 of
 *            the License, or (at your option) any later version.
 *
 *            Xinc is distributed in the hope that it will be useful,
 *            but WITHOUT ANY WARRANTY; without even the implied warranty of
 *            MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *            GNU Lesser General Public License for more details.
 *
 *            You should have received a copy of the GNU Lesser General Public
 *            License along with Xinc, write to the Free Software Foundation,
 *            Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * @link      http://code.google.com/p/xinc/
 */

namespace Xinc\Server;

class Cmd
{
	public function setupXinc()
	{
		$xinc = new Xinc();
	    $xinc->getConfig()->setOptions($this->parseCliOptions());
        return $xinc;
    }
    
    /**
     * prints help message, describing different parameters to run xinc
     *
     */
    public function showHelp()
    {
        echo "Usage: {$argv[0]} [switches] [project-file-1 [project-file-2 ...]]\n\n";

        echo "  -c --config-file=<file>   The config file to use.\n" .
             "  -d --config-dir=<dir>     The config dir to use.\n" .
             "  -p --project-dir=<dir>    The project directory.\n" .
             "  -w --working-dir=<dir>    The working directory.\n" .
             "  -l --log-file=<file>      The log file to use.\n" . 
             "  -v --verbose=<level>      The level of information to log (default 2).\n" . 
             "  -s --status-dir=<dir>     The status directory to use.\n" . 
             "  -o --once                 Run once and exit.\n" .
             "  --pid-file=<file>         The directory to put the PID file" .
             "  --version                 Prints the version of Xinc.\n" .
             "  -h --help                 Prints this help message.\n";
    }
    
    /**
     * Handle command line arguments.
     *
     * @return void
     */
    protected function parseCliOptions()
    {
		$ds = DIRECTORY_SEPARATOR;
        $workingDir = dirname($_SERVER['argv'][0]) . $ds;
        $configDir = "{$workingDir}etc{$ds}xinc{$ds}";

        $opts = getopt(
            'c:d:f:p:w:l:v:s:i:oh',
            array(
                'config-file:',
                'config-dir:',
                'project-dir:',
                'working-dir:',
                'status-dir:',
                'log-file:',
                'pid-file:',
                'verbose:',
                'once',
                'version',
                'help',
                '::',
            )
        );

        if (isset($opts['version'])) {
            $this->printVersion();
            exit();
        }

        if (isset($opts['help'])) {
            $this->showHelp();
            exit();
        }

        $options = $this->mergeOpts(
            $opts,
            array(
                'c' => 'config-file',
                'd' => 'config-dir',
                'w' => 'working-dir',
                'p' => 'project-dir',
                's' => 'status-dir',
                'f' => 'project-file',
                'l' => 'log-file',
                'v' => 'verbose',
                'o' => 'once',
                'i' => 'pid-file'
            ),
            array (
                'working-dir' => $workingDir,
                'config-dir'  => $configDir,
                'project-dir' => "$configDir" . Xinc::DEFAULT_PROJECT_DIR . $ds,
                'status-dir'  => "$workingDir" . Xinc::DEFAULT_STATUS_DIR . $ds,
                'log-file'    => "$workingDir" . 'xinc.log',
                'verbose'     => \Xinc\Core\Logger::DEFAULT_LOG_LEVEL,
                'pid-file'    => "$workingDir" . ".xinc.pid"
            )
        );
        return $options;
    }
    
    /**
     * Merges the default config and the short/long arguments given by 
     * mapping together. It doesn't respect options which aren't in the 
     * mapping.
     *
     * @param array $opts The options after php getopt function call.
     * @param array $mapping Mapping from short to long argument names.
     * @param array $default The default values for some arguments.
     *
     * @return array Mapping of the long arguments to the given values.
     */
    protected function mergeOpts($opts, $mapping, $default)
    {
        $merge = $default;

        foreach ($mapping as $keyShort => $keyLong) {
            if (isset($opts[$keyShort])) {
                $merge[$keyLong] = $opts[$keyShort];
            }
            if (isset($opts[$keyLong])) {
                $merge[$keyLong] = $opts[$keyLong];
            }
        }

        return $merge;
    }

    /**
     * Static main function to run Xinc is not cruise control.
     */
    public static function execute()
    {
		$self = new self;
        try {
            $xinc = $self->setupXinc();
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit(1);
        }
        try {
            $xinc->initialize();
            exit($xinc->run());
        }
        catch (\Exception $e) {
			$xinc->log('error',$e->getMessage());
			exit(1);
		}
    }
    
    /**
     * Prints the version of xinc
     *
     */
    public static function printVersion()
    {
        echo "Xinc version " . (new Xinc)->getVersion() . "\n";
    }

}
