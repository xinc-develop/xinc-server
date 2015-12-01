<?php
/**
 * Xinc - Continuous Integration.
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

use Xinc\Core\Build\BuildQueue;
use Xinc\Core\Properties;
use Xinc\Core\Plugin\Repository as PluginRepository;

/**
 * The main control class.
 */
class Xinc
{
    const VERSION = '3.0.1';

    const DEFAULT_PROJECT_DIR = 'projects';
    const DEFAULT_STATUS_DIR = 'status';

    private $buildQueue;
    public $options;
    public $log;

    public function __construct()
    {
		$this->options = new Properties();
		$this->setOptions(array(
		    'project-file' => null,
		    'once' => false
		));
	}
	
    public function setOptions($opts)
    {
		$this->options->set($opts);
	}
	
	public function setOption($key,$value)
	{
		$this->options->set($key,$value);
	}
	
	public function initialize()
	{
		$this->validateOptions();
		$this->initLogger();
		$this->logVersion();
		$this->logStartupSettings();
		
        $this->buildQueue = new BuildQueue();
	}

    public function run($args = '')
    {
        try {
            // get the project config files
            if (isset($arguments['projectFiles'])) {
                /**
                 * pre-process projectFiles
                 */
                $merge = array();
                for ($i = 0; $i<count($arguments['projectFiles']); $i++) {
                    $projectFile = $arguments['projectFiles'][$i];
                    if (!file_exists($projectFile) && strstr($projectFile, '*')) {
                        // we are probably under windows and the command line does not
                        // autoexpand *.xml
                        $array = glob($projectFile);
                        /**
                         * get rid of the not expanded file
                         */
                        unset($arguments['projectFiles'][$i]);
                        /**
                         * merge the glob'ed files
                         */
                        $merge = array_merge($merge, $array);
                    } else {
                        $arguments['projectFiles'][$i] = realpath($projectFile);
                    }
                }
                /**
                 * merge all the autoglobbed files with the original ones
                 */
                $arguments['projectFiles'] = array_merge($arguments['projectFiles'], $merge);

                foreach ($arguments['projectFiles'] as $projectFile) {
                    $logger->info('Loading Project-File: ' . $projectFile);
                    self::$_instance->_addProjectFile($projectFile);
                }
            }
            $this->start();
        } catch (Xinc_Build_Status_Exception_NoDirectory $statusNoDir) {
            $logger->error(
                'Xinc stopped: ' . 'Status Dir: "'
                . $statusNoDir->getDirectory() . '" is not a directory',
                STDERR
            );
        } catch (Xinc_Exception_IO $ioException) {
            $logger->error(
                'Xinc stopped: ' . $ioException->getMessage(),
                STDERR
            );
        } catch (Xinc_Config_Exception_FileNotFound $configFileNotFound) {
            $logger->error(
                'Xinc stopped: ' . 'Config File "'
                . $configFileNotFound->getFileName() . '" not found',
                STDERR
            );
        } catch (Exception $e) {
            // we need to catch everything here
            $logger->error(
                'Xinc stopped due to an uncaught exception: ' 
                . $e->getMessage() . ' in File : ' . $e->getFile() . ' on line '
                . $e->getLine() . $e->getTraceAsString(),
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
            $res=register_tick_function(array($this, 'checkShutdown'));
            $this->log->info('Registering shutdown function: ' . ($res?'OK':'NOK'));
            $this->processBuildsDaemon();
        } else {
            $this->log->info('Run-once mode '
                                            . '(project interval is negative)');
            //Xinc_Logger::getInstance()->flush();
            $this->processBuildsRunOnce();
        }
    }

    /**
     * Validates the given options (working-dir, status-dir, project-dir)
     *
     * @throws Xinc::Core::Exception::IOException
     */
    protected function validateOptions()
    {
        $this->checkDirectory($this->options['working-dir']);
        $this->checkDirectory($this->options['project-dir']);
        $this->checkDirectory($this->options['status-dir']);
    }

    /**
     * Checks if the directory is available otherwise tries to create it.
     * Returns the realpath of the directory afterwards.
     *
     * @param string $strDirectory Directory to check for.
     *
     * @return string The realpath of given directory.
     * @throws Xinc\Core\Exception\IOException
     */
    protected function checkDirectory($strDirectory)
    {
        if (!is_dir($strDirectory)) {
            $this->log->verbose(
                'Directory "' . $strDirectory . '" does not exist. Trying to create'
            );
            $bCreated = @mkdir($strDirectory, 0755, true);
            if (!$bCreated) {
                $arError = error_get_last();
                \Xinc\Core\Logger::getInstance()->verbose(
                    'Directory "' . $strDirectory . '" could not be created.'
                );
                throw new \Xinc\Core\Exception\IOException($strDirectory, null, $arError['message']);
            }
        } elseif (!is_writeable($strDirectory)) {
            \Xinc\Core\Logger::getInstance()->verbose(
                'Directory "' . $strDirectory . '" is not writeable.'
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
     * Prints the startup information of xinc
     *
     * @return void
     */
    public function logStartupSettings()
    {
        $logger = $this->log;

        $logger->info('Starting up Xinc');
        $logger->info('- Version:    ' . Xinc::VERSION);
        $logger->info('- Workingdir: ' . $this->options['working-dir']);
        $logger->info('- Projectdir: ' . $this->options['project-dir']);
        $logger->info('- Statusdir:  ' . $this->options['status-dir']);
        $logger->info('- Log Level:  ' . $this->options['verbose']);
    }

    /**
     * Prints the version of xinc
     */
    public function logVersion()
    {
        $this->log->info('Xinc version ' . Xinc::VERSION);
    }

    /**
     * Initialize the logger with path to file and verbosity
     *
     * @return void
     */
    public function initLogger()
    {
        $this->log = $logger = \Xinc\Core\Logger::getInstance();

        $logger->setLogLevel($this->options['verbose']);
        $logger->setXincLogFile($this->options['log-file']);
    }

    /**
     * Returns the Version of Xinc
     *
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
    }
    
        /**
     * Checks if a special shutdown file exists 
     * and exits if it does
     *
     */
    public function checkShutdown()
    {
        $file = $this->shutdownFlag();
        if (file_exists($file) && $this->buildActive == false) {
            $this->log->info('Preparing to shutdown');
            $statInfo = stat($file);
            $fileUid = $statInfo['uid'];
            /**
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
     * shutsdown the xinc instance and cleans up pidfile etc
     *
     * @param boolean $exit
     */
    private function shutDown($exit=false)
    {
        $file = $this->shutdownFlag();
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
    
    private function shutdownFlag()
    {
		return $this->options['status-dir'] . DIRECTORY_SEPARATOR . '.shutdown';
	}
	
	protected function getPidFile()
	{
		return $this->options['pid-file'];
	}
}
