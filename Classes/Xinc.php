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

/**
 * The main control class.
 */
class Xinc
{
    const VERSION = '3.0.3';

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
        $this->setConfig(new Config());
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getConfigLoader()
    {
      return $this->configLoader;
    }

    public function initialize()
    {
        $this->initConfigLoader();
        $this->initRegistry();
    }

    public function prepare()
    {
        $this->initLogger();
        $this->validateOptions();
        $this->applyOptions();
        $this->logVersion();
        $this->logStartupSettings();
        $this->buildQueue = new BuildQueue();
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
      $options['pidfile']->setDefaultValue("$wd".'.xinc.pid');
      return $options;
    }

    private function loadConfig()
    {
        $this->configLoader->load($this->config, $this->registry);
    }

    private function loadProjects()
    {
        $pd = $this->options['project-dir'];
        if (isset($this->options['project-file'])) {
        } else {
        }
    }

    protected function initAttributeOnce($attr, $obj, $construct)
    {
        if (empty($this->$attr)) {
            if ($obj === null) {
                $this->$attr = $construct();
                $this->$attr->setLogger($this->log);
            } else {
                $this->$attr = $c;
            }
        } else {
            throw new Mistake("A {$attr} was already set.");
        }
    }

    public function initConfigLoader(ConfigLoaderInterface $c = null)
    {
        $this->initAttributeOnce('configLoader', $c,
            function () { return new Xml(); });
    }

    public function initRegistry(XincRegistryInterface $reg = null)
    {
        $this->initAttributeOnce('registry', $reg,
            function () { return new Registry(); });
    }

    /**
     * Add a projectfile to the xinc processing.
     *
     * @param string $fileName
     */
    private function addProjectFile($fileName)
    {
        try {
            $config = new Xinc_Project_Config($fileName);
            $engineName = $config->getEngineName();

            $engine = Xinc_Engine_Repository::getInstance()->getEngine($engineName);

            $builds = $engine->parseProjects($config->getProjects());

            self::$_buildQueue->addBuilds($builds);
        } catch (Xinc_Project_Config_Exception_FileNotFound $notFound) {
            Xinc_Logger::getInstance()->error('Project Config File '.$fileName.' cannot be found');
        } catch (Xinc_Project_Config_Exception_InvalidEntry $invalid) {
            Xinc_Logger::getInstance()->error('Project Config File has an invalid entry: '.$invalid->getMessage());
        } catch (Xinc_Engine_Exception_NotFound $engineNotFound) {
            Xinc_Logger::getInstance()->error('Project Config File references an unknown Engine: '
                                             .$engineNotFound->getMessage());
        }
    }

    public function run()
    {
        try {
            $this->prepare();
            $this->loadConfig();
            $this->loadProjects();

            // get the project config files
            if (isset($arguments['projectFiles'])) {
                /*
                 * pre-process projectFiles
                 */
                $merge = array();
                for ($i = 0; $i < count($arguments['projectFiles']); ++$i) {
                    $projectFile = $arguments['projectFiles'][$i];
                    if (!file_exists($projectFile) && strstr($projectFile, '*')) {
                        // we are probably under windows and the command line does not
                        // autoexpand *.xml
                        $array = glob($projectFile);
                        /*
                         * get rid of the not expanded file
                         */
                        unset($arguments['projectFiles'][$i]);
                        /*
                         * merge the glob'ed files
                         */
                        $merge = array_merge($merge, $array);
                    } else {
                        $arguments['projectFiles'][$i] = realpath($projectFile);
                    }
                }
                /*
                 * merge all the autoglobbed files with the original ones
                 */
                $arguments['projectFiles'] = array_merge($arguments['projectFiles'], $merge);

                foreach ($arguments['projectFiles'] as $projectFile) {
                    $logger->info('Loading Project-File: '.$projectFile);
                    self::$_instance->_addProjectFile($projectFile);
                }
            }
            $this->start();
        } catch (Xinc_Build_Status_Exception_NoDirectory $statusNoDir) {
            $logger->error(
                'Xinc stopped: '.'Status Dir: "'
                .$statusNoDir->getDirectory().'" is not a directory',
                STDERR
            );
        } catch (Xinc_Exception_IO $ioException) {
            $logger->error(
                'Xinc stopped: '.$ioException->getMessage(),
                STDERR
            );
        } catch (Xinc_Config_Exception_FileNotFound $configFileNotFound) {
            $logger->error(
                'Xinc stopped: '.'Config File "'
                .$configFileNotFound->getFileName().'" not found',
                STDERR
            );
        } catch (Exception $e) {
            // we need to catch everything here
            $logger->error(
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
        if (!$this->options['once']) {
            $res = register_tick_function(array($this, 'checkShutdown'));
            $this->log->info('Registering shutdown function: '.($res ? 'OK' : 'NOK'));
            $this->processBuildsDaemon();
        } else {
            $this->log->info('Run-once mode '
                                            .'(project interval is negative)');
            //Xinc_Logger::getInstance()->flush();
            $this->processBuildsRunOnce();
        }
    }

    /**
     * Validates the given options (working-dir, status-dir, project-dir).
     *
     * @throws Xinc::Core::Exception::IOException
     */
    protected function validateOptions()
    {
        $this->checkDirectory($this->options['workingdir']);
        $this->checkDirectory($this->options['projectdir']);
        $this->checkDirectory($this->options['statusdir']);
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

        $logger->info('Starting up Xinc');
        $logger->info('- Version:    '.self::VERSION);
        $logger->info('- Workingdir: '.$this->options['working-dir']);
        $logger->info('- Projectdir: '.$this->options['project-dir']);
        $logger->info('- Statusdir:  '.$this->options['status-dir']);
        $logger->info('- Log Level:  '.$this->options['verbose']);
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
        $logger->setLogLevel($this->getConfig()->getOption('verbose'));
	$logger->setXincLogFile($this->getConfig()->getOption('log-file'));
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
        $file = $this->shutdownFlag();
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
        return $this->options['status-dir'].DIRECTORY_SEPARATOR.'.shutdown';
    }

    protected function getPidFile()
    {
        return $this->options['pid-file'];
    }

    public function logException(\Exception $e)
    {
        $this->log->error($e->getMessage());
        $this->log->error($e->getTraceAsString());
    }
}
