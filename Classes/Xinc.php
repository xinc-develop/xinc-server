<?php
/**
 * Xinc - Continuous Integration.
 *
 * @author    David Ellis
 * @author    Gavin Foster
 * @author    Jamie Talbot
 * @author    Alexander Opitz <opitz.alexander@gmail.com>
 * @author    Sebastian Knapp <news@young-workers.de>
 * @copyright 2007 David Ellis, One Degree Square
 * @copyright 2015-2016 Xinc Development Team <https://github.com/xinc-develop>
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
 * @homepage  http://xinc-develop.github.io/
 */
namespace Xinc\Server;

use Xinc\Getopt\Getopt;
use Xinc\Getopt\Option;
use Xinc\Core\Config\ConfigLoaderInterface;
use Xinc\Core\Registry\XincRegistryInterface;
use Xinc\Core\Build\BuildQueue;
use Xinc\Core\Config\Config;
use Xinc\Core\Config\Xml;
use Xinc\Core\Logger;
use Xinc\Core\Registry\Registry;
use Xinc\Core\Exception\Mistake;
use Xinc\Core\Traits\Logger as LoggerTrait;
use Xinc\Core\Traits\Config as ConfigTrait;
use Xinc\Core\Project\Config\Xml as ProjectXml;

/**
 * The main control class.
 */
class Xinc
{
    const VERSION = '2.5.4';

    const DEFAULT_PROJECT_DIR = 'projects';
    const DEFAULT_STATUS_DIR = 'status';

    use ConfigTrait;
    use LoggerTrait;
    /**
     * Object which loads the main configuration.
     */
    private $configLoader;
    /**
     * Registry for various parts of Xinc.
     */
    private $registry;
    private $buildQueue;

    public function __construct()
    {
        $this->config = new Config();
        # default Config Loader
        $this->setConfigLoader( new Xml() );
        $this->registry = new Registry();
        $this->registry->setConfig($this->config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setCommandlineOptions($options)
    {
        foreach($options as $name => $val) {
            if($val->getIsDefault()) {
                $this->config->setSetting($name,$val->getValue());
            }
            else {
                $this->config->setOption($name,$val->getValue());
            }
        }
    }

    public function getConfigLoader()
    {
      return $this->configLoader;
    }

    public function setConfigLoader(ConfigLoaderInterface $loader)
    {
        $this->configLoader = $loader;
    }

    protected function initialize()
    {
        $this->initLogger();
        $this->registry->setLogger($this->log);
        $this->configLoader->setLogger($this->log);
    }

    public function prepare()
    {
        $this->initialize();
        $this->validateOptions();
        $this->applyOptions();
        $this->logVersion();
        $this->logStartupSettings();
    }

    /**
     * The commandline option for which the main class is responsible.
     * @todo maybe find a better place for project-dir option
     */
    public function getCommandlineOptions()
    {
      $wd = './';
      $options['workingdir'] = new Option('w','working-dir',Getopt::REQUIRED_ARGUMENT);
      $options['workingdir']->setDescription('the working directory');
      $options['workingdir']->setDefaultValue($wd);
      $options['once'] = new Option('o','once',Getopt::IS_FLAG);
      $options['once']->setDescription("run once and exit");
      $options['once']->setDefaultValue(false);
      $options['verbose'] = new Option('v','verbose',Getopt::OPTIONAL_ARGUMENT);
      $options['verbose']->setDescription("the level of information to log");
      $loglevel = getenv('XINC_LOGLEVEL');
      if($loglevel === false) {
          $loglevel = Logger::DEFAULT_LOG_LEVEL;
      }
      else {
          $loglevel = intval($loglevel);
      }
      $options['verbose']->setDefaultValue($loglevel);
      $options['projectdir'] = new Option('p','project-dir',Getopt::REQUIRED_ARGUMENT);
      $options['projectdir']->setDescription('directory with project configurations');
      $options['statusdir'] = new Option('s','status-dir',Getopt::REQUIRED_ARGUMENT);
      $options['statusdir']->setDescription('internal (writable) status directory');
      $options['statusdir']->setDefaultValue("$wd".Xinc::DEFAULT_STATUS_DIR."/");
      $options['logfile'] = new Option('l','log-file',Getopt::REQUIRED_ARGUMENT);
      $options['logfile']->setDescription('the main log file');
      $options['logfile']->setDefaultValue("$wd".'xinc.log');
      $options['pidfile'] = new Option('i','pid-file',Getopt::REQUIRED_ARGUMENT);
      $options['pidfile']->setDescription('place to store the process id');
      $options['pidfile']->setDefaultValue("$wd".Xinc::DEFAULT_STATUS_DIR."/".'.xinc.pid');
      $options['heartbeat'] = new Option(null,'heartbeat',Getopt::REQUIRED_ARGUMENT);
      $options['heartbeat']->setDescription('Interval in which build queue is checked (in seconds)');
      $options['heartbeat']->setDefaultValue(30);
      return $options;
    }

    private function loadConfig()
    {
        $this->configLoader->load($this->config, $this->registry);
    }

    private function applyConfig()
    {
        $conf = $this->config;
        if($conf->hasSetting('verbose')) {
            $this->log->setLogLevel($conf->get('verbose'));
        }
    }

    private function loadProjects()
    {
        $pro = new ProjectXml();
        $pro->setLogger($this->log);
        $pro->load($this->config, $this->registry);
    }

    protected function setupBuildQueue()
    {
        $this->buildQueue = new BuildQueue();
        $this->buildQueue->setLogger($this->log);
        $projects = $this->registry->getProjectIterator();
        foreach($projects as $project) {
            $engine = $this->registry->getEngine($project->getEngineName());
            $build = $engine->setupBuild($project);
            $this->buildQueue->addBuild($build);
        }
    }

    public function run()
    {
        try {
            $this->loadConfig();
            $this->applyConfig();
            $this->loadProjects();
            $this->setupBuildQueue();
            $this->start();
        } catch (Exception $e) {
            // we need to catch everything here
            $this->log->error(
                'Xinc stopped due to an uncaught exception: '
                .$e->getMessage().' in File : '.$e->getFile().' on line '
                .$e->getLine().$e->getTraceAsString(),
                STDERR
            );
        }

        $this->shutDown();
    }

    /**
     * Starts the continuous loop.
     */
    protected function start()
    {
        if (!$this->config->get('once')) {
            $res = register_tick_function(array($this, 'checkShutdown'));
            $this->log->info('Registering shutdown function: '.
                ($res ? 'OK' : 'NOT OK'));
            $this->processBuildsDaemon();
        } else {
            $this->log->info('Run-once mode.');
            $this->processBuildsRunOnce();
        }
    }

    /**
     * Processes the projects that have been configured
     * in the config-file and executes each project
     * if the scheduled time has expired
     */
    public function processBuildsDaemon()
    {
        $pidfile = $this->getPidFile();
        if (file_exists($pidfile)) {
            $oldPid = file_get_contents($pidfile);
            if ($this->_isProcessRunning($oldPid)) {
                $this->log->error('Xinc Instance with PID ' . $oldPid .
                    ' still running. Check pidfile ' . $pidfile . ".\n" .
                    'Shutting down.');
                exit(-1);
            } else {
                $this->log->warn('Cleaning up old pidFile.');
            }
        }
        file_put_contents($pidfile, getmypid());

        $this->log->verbose('Start main loop.');

        while (true) {
            declare(ticks=2);
            $now = time();
            $nextBuildTime = $this->buildQueue->getNextBuildTime();

            if ($nextBuildTime !== null) {
                $this->log->info('Next buildtime: ' . date('Y-m-d H:i:s', $nextBuildTime));
                $sleep = $nextBuildTime - $now;
            } else {
                $sleep = $this->config->get('heartbeat');
            }
            if ($sleep > 0) {
                $this->buildActive=false;
                $this->log->info('Sleeping: ' . $sleep . ' seconds');
                $start = time() + microtime(true);
                while(((time()+microtime(true)) - $start)<=$sleep) {
                    usleep(10000);
                }
            }
            while (($nextBuild = $this->buildQueue->getNextBuild()) !== null) {
                $this->buildActive=true;
                $nextBuild->build();
            }
        }
    }

    /**
     * Validates the given options (working-dir, status-dir, project-dir).
     *
     * @throws Xinc::Core::Exception::IOException
     */
    protected function validateOptions()
    {
        $config = $this->config;
        $this->checkDirectory($config->get('workingdir'));
        $this->checkDirectory($config->get('projectdir'));
        $this->checkDirectory($config->get('statusdir'));
    }

    protected function applyOptions()
    {
    }

    /**
     * Checks if the directory is available otherwise tries to create it.
     * Returns the realpath of the directory afterwards.
     *
     * @param string $strDirectory Directory to check for.
     *
     * @return string The realpath of given directory.
     *
     * @throws Xinc\Core\Exception\IOException
     */
    protected function checkDirectory($strDirectory)
    {
        if (!is_dir($strDirectory)) {
            $this->log->verbose(
                'Directory "'.$strDirectory.'" does not exist. Trying to create'
            );
            $bCreated = @mkdir($strDirectory, 0755, true);
            if (!$bCreated) {
                $arError = error_get_last();
                $this->log->warn(
                    'Directory "'.$strDirectory.'" could not be created.'
                );
                throw new \Xinc\Core\Exception\IOException(
                    $strDirectory, null, $arError['message']);
            }
        } elseif (!is_writeable($strDirectory)) {
            $this->log->warn(
                'Directory "'.$strDirectory.'" is not writeable.'
            );
            throw new \Xinc\Core\Exception\IOException(
                $strDirectory,
                null,
                null,
                \Xinc\Core\Exception\IOException::FAILURE_NOT_WRITEABLE
            );
        }

        return realpath($strDirectory);
    }

    /**
     * Prints the startup information of xinc.
     */
    public function logStartupSettings()
    {
        $logger = $this->log;
        $config = $this->config;

        $logger->info('Starting up Xinc');
        $logger->info('- Version:    '.self::VERSION);
        $logger->info('- Workingdir: '.$config->get('working-dir'));
        $logger->info('- Configdir:  '.$config->get('config-dir'));
        $logger->info('- Projectdir: '.$config->get('project-dir'));
        $logger->info('- Statusdir:  '.$config->get('status-dir'));
        $logger->info('- Log Level:  '.$config->get('verbose'));
        $logger->info('- Pid File:   '.$config->get('pid-file'));
    }

    /**
     * Prints the version of xinc.
     */
    public function logVersion()
    {
        $this->log->info('Xinc version '.self::VERSION);
    }

    /**
     * Initialize the logger with path to file and verbosity.
     */
    public function initLogger()
    {
        $this->log = $logger = new Logger();
        $logger->setLogLevel($this->getConfig()->get('verbose'));
        $logger->setXincLogFile($this->getConfig()->get('log-file'));
    }

    /**
     * Returns the Version of Xinc.
     *
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * Checks if a special shutdown file exists
     * and exits if it does.
     */
    public function checkShutdown()
    {
        $file = $this->getShutdownFlag();
        if (file_exists($file) && $this->buildActive == false) {
            $this->log->info('Preparing to shutdown');
            $statInfo = stat($file);
            $fileUid = $statInfo['uid'];
            /*
             * Only the user running xinc cann issue a shutdown
             */
            if ($fileUid == getmyuid()) {
                $this->shutDown(true);
            } else {
                // delete the file
                unlink($file);
            }
        }
    }

    /**
     * shutsdown the xinc instance and cleans up pidfile etc.
     *
     * @param bool $exit
     */
    private function shutDown($exit = false)
    {
        $file = $this->getShutdownFlag();
        if (file_exists($file)) {
            unlink($file);
        }
        $pidFile = $this->getPidFile();
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        $this->log->info('Goodbye. Shutting down Xinc');
        if ($exit) {
            exit();
        }
    }

    private function getShutdownFlag()
    {
        return $this->config->get('status-dir').DIRECTORY_SEPARATOR.'.shutdown';
    }

    protected function getPidFile()
    {
        return $this->config->get('pid-file');
    }

    public function logException(\Exception $e)
    {
        $this->log->error($e->getMessage());
        $this->log->error($e->getTraceAsString());
    }

    private function _isProcessRunning($pid)
    {
        if (isset($_SERVER['SystemRoot']) && DIRECTORY_SEPARATOR != '/') {
            /**
             * winserv is handling that
             */
            return false;
        } else {
            exec('ps --no-heading -p ' . $pid, $out, $res);
            if ($res!=0) {
                return false;
            } else {
                if (count($out)>0) {
                    return true;
                } else {
                    return false;
                }
            }
        }
    }

}
