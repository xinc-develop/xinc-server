<?php
/**
 * Xinc - Continuous Integration.
 *
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
 *
 * @link      http://code.google.com/p/xinc/
 */
namespace Xinc\Server;

use Xinc\Getopt\Getopt;
use Xinc\Getopt\Option;

/**
 * The Xinc Server Commandline.
 */
class Cmd
{
  private $xinc;

  public function __construct()
  {
    $this->xinc = new Xinc();
  }

  public function getXinc()
  {
    return $this->xinc();
  }

  /**
   * @param mixed $args array or string with commandline options
   */
    public function setupXinc($args = null)
    {
        $this->xinc->initialize();
        $options = $this->parseCliOptions($args);
        $this->xinc->getConfig()->setOptions($options);
        return $this->xinc;
    }

    /**
     * prints help message, describing different parameters to run xinc.
     */
    public function showHelp()
    {
        $getopt = $this->setupGetopt();
        $getopt->setBanner("Usage: %s [switches] [project-file-1 [project-file-2 ...]]\n\n");
        echo $getopt->getHelpText();
        /*
        echo "  -c --config-file=<file>   The config file to use.\n".
             "  -d --config-dir=<dir>     The config dir to use.\n".
             "  -p --project-dir=<dir>    The project directory.\n".
             "  -w --working-dir=<dir>    The working directory.\n".
             "  -l --log-file=<file>      The log file to use.\n".
             "  -v --verbose=<level>      The level of information to log (default 2).\n".
             "  -s --status-dir=<dir>     The status directory to use.\n".
             "  -o --once                 Run once and exit.\n".
             '  --pid-file=<file>         The directory to put the PID file'.
             "  --version                 Prints the version of Xinc.\n".
             "  -h --help                 Prints this help message.\n";
        */
    }

    public function setupGetopt()
    {
      $getopt = new Getopt();
      $getopt->addOptions($this->getCommandlineOptions());
      $getopt->addOptions($this->xinc->getCommandlineOptions());
      $loader = $this->xinc->getConfigLoader();
      $getopt->addOptions($loader->getCommandlineOptions());
      // add more defaults
      $options = $getopt->getOptionObjects();
      $base = $options['working-dir']->getDefaultValue();
      $options['config-dir']->setDefaultValue("{$base}etc/xinc/");
      $options['project-dir']->setDefaultValue(
          $options['config-dir']->getDefaultValue() . Xinc::DEFAULT_PROJECT_DIR . "/"
      );
      return $getopt;
    }

    public function getCommandlineOptions()
    {
      $options['version'] = new Option('V','version',Getopt::NO_ARGUMENT);
      $options['version']->setDescription('print the version of Xinc');
      return $options;
    }

    /**
     * Handle command line arguments.
     */
    protected function parseCliOptions($args=null)
    {
        $getopt = $this->setupGetopt();
        $getopt->parse($args);
        $options = $getopt->getIterator('long');
        return $options;
    }

    /**
     * Static main function to run Xinc is not cruise control.
     */
    public static function execute()
    {
        $self = new self();
        try {
            $xinc = $self->setupXinc();
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit(1);
        }
        try {
            $xinc->prepare();
            exit($xinc->run());
        } catch (\Exception $e) {
            $xinc->logException($e);
            exit(1);
        }
    }

    /**
     * Prints the version of xinc.
     */
    public static function printVersion()
    {
        echo 'Xinc version '.(new Xinc())->getVersion()."\n";
    }
}
